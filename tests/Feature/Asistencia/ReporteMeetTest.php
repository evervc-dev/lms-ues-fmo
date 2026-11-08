<?php

use App\Models\User;
use App\Modules\Asistencia\Jobs\EvaluarAlertasAsistencia;
use App\Modules\Asistencia\Livewire\ClasesVirtuales;
use App\Modules\Asistencia\Services\ReporteMeetService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\RecordNotFoundException;
use Illuminate\Database\RecordsNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\AssertionFailedError;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\HojaXlsx;

use function Pest\Laravel\actingAs;

/*
 * RF21 · entrega 1: asistencia virtual con el reporte de Meet que sube el docente (ADR-003, ADR-048).
 * La clase de prueba es el viernes 23 de octubre de 2026 a las 07:00, de 100 minutos: así cada minuto es un
 * punto porcentual. «Ahora» son las 10:00 del mismo día, con la clase ya finalizada.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-23 10:00'));
    Storage::fake('local');
    Queue::fake();
});

function servicioMeet(): ReporteMeetService
{
    return app(ReporteMeetService::class);
}

/**
 * Escenario base con tres matriculados de correo conocido: el estudiante del escenario (Ana, `matricula`),
 * Mario (`b`) y Lucía (`c`).
 *
 * @return array<string, mixed>
 */
function escenarioMeet(): array
{
    $e = escenario();
    DbContext::system(fn () => $e['estudiante']->forceFill(['institutional_email' => 'gs21004@ues.edu.sv', 'first_name' => 'Ana', 'last_name' => 'Gómez'])->save());
    $e['b'] = matricularParaMeet($e, 'mr21011@ues.edu.sv', 'Mario', 'Rivas');
    $e['c'] = matricularParaMeet($e, 'lp21020@ues.edu.sv', 'Lucía', 'Pérez');

    return $e;
}

/** @param  array<string, mixed>  $e */
function matricularParaMeet(array $e, string $correo, string $nombre, string $apellido): int
{
    return DbContext::system(function () use ($e, $correo, $nombre, $apellido) {
        $estudiante = User::factory()->create(['institutional_email' => $correo, 'first_name' => $nombre, 'last_name' => $apellido])->assignRole('estudiante');

        return DB::table('enrollments')->insertGetId(['student_id' => $estudiante->id, 'section_id' => $e['seccion'], 'period_id' => $e['periodo'], 'subject_id' => $e['prn'],
            'status' => 'active', 'enrolled_via' => 'key', 'enrolled_by' => $estudiante->id, 'created_at' => now(), 'updated_at' => now()]);
    });
}

/**
 * Una clase virtual de la sección; por defecto, la del 23 de octubre a las 07:00, de 100 minutos y finalizada.
 *
 * @param  array<string, mixed>  $e
 * @param  array<string, mixed>  $datos
 */
function claseVirtual(array $e, array $datos = []): int
{
    return DbContext::system(fn () => DB::table('video_sessions')->insertGetId($datos + [
        'section_id' => $e['seccion'], 'topic' => 'Recursividad', 'starts_at' => '2026-10-23 07:00:00', 'duration_minutes' => 100,
        'status' => 'finished', 'meet_url' => 'https://meet.google.com/abc-defg-hij', 'created_at' => now(), 'updated_at' => now(),
    ]));
}

/**
 * CSV como el que exporta Google Sheets. Cada fila es [nombre, correo, duración]; la hora de entrada se agrega
 * si los encabezados la incluyen.
 *
 * @param  list<array{0: string, 1: string, 2: string}>  $filas
 * @param  list<string>  $encabezados
 */
function reporteCsv(array $filas, array $encabezados = ['Nombre', 'Correo electrónico', 'Duración', 'Hora en que se unió'], string $entrada = '2026-10-23 07:02:00'): UploadedFile
{
    $salida = fopen('php://temp', 'r+');
    fputcsv($salida, $encabezados, escape: '');
    foreach ($filas as $fila) {
        fputcsv($salida, array_slice([...$fila, $entrada], 0, count($encabezados)), escape: '');
    }
    rewind($salida);

    return UploadedFile::fake()->createWithContent('reporte.csv', (string) stream_get_contents($salida));
}

/**
 * Excel con metadatos arriba (la fecha de la reunión) y la hora de entrada sin fecha. Cada fila es
 * [nombre completo, correo, duración]; un correo vacío deja la celda sin escribir y una duración float queda como
 * número (fracción de día).
 *
 * @param  list<array{0: string, 1: string, 2: string|float}>  $filas
 */
function reporteXlsx(array $filas, string $fecha = '23/10/2026'): UploadedFile
{
    $hoja = [['Asistencia a la reunión · Programación I'], ['Fecha de la reunión', $fecha], ['Nombre', 'Apellido', 'Correo electrónico', 'Duración', 'Hora de entrada']];
    foreach ($filas as [$nombre, $correo, $duracion]) {
        [$primero, $apellido] = array_pad(explode(' ', $nombre, 2), 2, '');
        $hoja[] = [$primero, $apellido, $correo === '' ? null : $correo, $duracion, '07:02'];
    }
    $ruta = HojaXlsx::crear([$hoja]);
    $archivo = UploadedFile::fake()->createWithContent('reporte.xlsx', (string) file_get_contents($ruta));
    unlink($ruta);

    return $archivo;
}

/** @param  array<string, mixed>  $e */
function subirMeet(array $e, int $clase, UploadedFile $archivo): int
{
    actingAs($e['docente']);

    return servicioMeet()->subir($e['docente'], $e['seccion'], $clase, $archivo);
}

/** @param  array<string, mixed>  $e */
function confirmarMeet(array $e, int $reporte): string
{
    actingAs($e['docente']);

    return servicioMeet()->confirmar($e['docente'], $e['seccion'], $reporte);
}

function filaMeet(int $reporte, string $nombre): int
{
    return (int) DB::table('meet_report_rows')->where('meet_report_id', $reporte)->where('name', 'like', $nombre.'%')->value('id');
}

/** @param  Collection<int, mixed>  $entradas */
function entradaDeMatricula(Collection $entradas, int $matricula): mixed
{
    return $entradas->first(fn ($entrada) => (int) data_get($entrada, 'matricula') === $matricula);
}

/**
 * @return array<string, array<int, string>>
 */
function erroresDeValidacion(callable $accion): array
{
    try {
        $accion();
    } catch (ValidationException $error) {
        return $error->errors();
    }

    throw new AssertionFailedError('Se esperaba una ValidationException.');
}

function registroMeet(int $matricula): ?object
{
    return DB::table('attendance_records')->where('enrollment_id', $matricula)->first();
}

it('CP-RF21-02 lee el reporte en CSV y en Excel, suma las reconexiones y deja un borrador con vista previa antes de confirmar', function (string $formato) {
    $e = escenarioMeet();
    $clase = claseVirtual($e);
    $filas = [['Ana Gómez', 'gs21004@ues.edu.sv', '45 min'], ['Ana Gómez', 'GS21004@ues.edu.sv', '25 min'], ['Mario Rivas', 'mr21011@ues.edu.sv', '30 min']];

    $reporte = subirMeet($e, $clase, $formato === 'csv' ? reporteCsv($filas) : reporteXlsx($filas));

    $borrador = DB::table('meet_reports')->where('id', $reporte)->sole();
    expect($borrador->confirmed_at)->toBeNull()
        ->and((bool) $borrador->is_current)->toBeFalse()
        ->and((int) $borrador->video_session_id)->toBe($clase)
        ->and((int) $borrador->uploaded_by)->toBe($e['docente']->id)
        ->and($borrador->file_path)->toStartWith("meet/{$clase}/")->toEndWith(".{$formato}");
    Storage::disk('local')->assertExists($borrador->file_path);

    $previa = servicioMeet()->vistaPrevia($e['docente'], $e['seccion'], $reporte);
    $ana = entradaDeMatricula($previa['presentes'], $e['matricula']);
    $mario = entradaDeMatricula($previa['ausentes'], $e['b']);
    expect(data_get($ana, 'minutos'))->toEqual(70)
        ->and(data_get($ana, 'porcentaje'))->toEqual(70.0)
        ->and(data_get($ana, 'estado'))->toBe('present')
        ->and(data_get($mario, 'minutos'))->toEqual(30)
        ->and(data_get($mario, 'estado'))->toBe('absent')
        ->and(DB::table('meet_report_rows')->where('meet_report_id', $reporte)->where('enrollment_id', $e['matricula'])->pluck('minutes')->map(fn ($m) => (int) $m)->all())->toBe([70]);

    expect(DB::table('attendance_sessions')->count())->toBe(0)
        ->and(DB::table('attendance_records')->count())->toBe(0);
})->with(['csv', 'xlsx']);

it('CP-RF21-03 asocia automáticamente la fila con un correo @ues.edu.sv matriculado', function () {
    $e = escenarioMeet();

    $reporte = subirMeet($e, claseVirtual($e), reporteCsv([['Ana Gómez', 'gs21004@ues.edu.sv', '1 h 15 min']]));

    $fila = DB::table('meet_report_rows')->where('meet_report_id', $reporte)->sole();
    expect($fila->status)->toBe('matched')
        ->and((int) $fila->enrollment_id)->toBe($e['matricula'])
        ->and((int) $fila->minutes)->toBe(75)
        ->and(servicioMeet()->vistaPrevia($e['docente'], $e['seccion'], $reporte)['sin_asociar'])->toBeEmpty();
});

it('CP-RF21-04 deja sin asociar la fila sin correo o con un correo ajeno a la sección hasta que el docente la asigna o la descarta', function () {
    $e = escenarioMeet();
    $ajeno = usuarioCon('estudiante');
    $reporte = subirMeet($e, claseVirtual($e), reporteCsv([
        ['Invitado celular', '', '1 h 20 min'],
        ['Personal Gmail', 'ana.gomez.personal@gmail.com', '75 min'],
        ['Fuera Seccion', $ajeno->institutional_email, '80 min'],
        ['Ana Gómez', 'gs21004@ues.edu.sv', '70 min'],
    ]));

    expect(servicioMeet()->vistaPrevia($e['docente'], $e['seccion'], $reporte)['sin_asociar'])->toHaveCount(3)
        ->and(DB::table('meet_report_rows')->where('meet_report_id', $reporte)->where('status', 'unmatched')->whereNull('enrollment_id')->count())->toBe(3);

    expect(erroresDeValidacion(fn () => confirmarMeet($e, $reporte)))->toHaveKey('reporte')
        ->and(DB::table('meet_reports')->where('id', $reporte)->value('confirmed_at'))->toBeNull()
        ->and(DB::table('attendance_records')->count())->toBe(0);

    servicioMeet()->asignar($e['docente'], $e['seccion'], filaMeet($reporte, 'Invitado celular'), $e['b']);
    servicioMeet()->descartar($e['docente'], $e['seccion'], filaMeet($reporte, 'Personal Gmail'));
    servicioMeet()->descartar($e['docente'], $e['seccion'], filaMeet($reporte, 'Fuera Seccion'));

    $previa = servicioMeet()->vistaPrevia($e['docente'], $e['seccion'], $reporte);
    expect($previa['sin_asociar'])->toBeEmpty()
        ->and($previa['descartadas'])->toHaveCount(2)
        ->and(data_get(entradaDeMatricula($previa['presentes'], $e['b']), 'minutos'))->toEqual(80)
        ->and(DB::table('meet_report_rows')->where('id', filaMeet($reporte, 'Invitado celular'))->first())
        ->status->toBe('matched')->enrollment_id->toEqual($e['b']);

    confirmarMeet($e, $reporte);

    expect(registroMeet($e['b'])->status)->toBe('present');
});

it('CP-RF21-05 deja Ausente al matriculado que no aparece en el reporte', function () {
    $e = escenarioMeet();
    $reporte = subirMeet($e, claseVirtual($e), reporteCsv([['Ana Gómez', 'gs21004@ues.edu.sv', '80 min']]));

    $ausentes = servicioMeet()->vistaPrevia($e['docente'], $e['seccion'], $reporte)['ausentes'];
    expect(data_get(entradaDeMatricula($ausentes, $e['b']), 'minutos'))->toEqual(0)
        ->and(data_get(entradaDeMatricula($ausentes, $e['c']), 'estado'))->toBe('absent');

    confirmarMeet($e, $reporte);

    foreach ([$e['b'], $e['c']] as $matricula) {
        expect(registroMeet($matricula))
            ->status->toBe('absent')
            ->method->toBe('meet')
            ->and((float) registroMeet($matricula)->meet_percent)->toEqual(0.0);
    }
});

it('CP-RF21-06 queda Presente con 70 % o más de la duración programada y Ausente con menos', function () {
    $e = escenarioMeet();
    $clase = claseVirtual($e);
    $reporte = subirMeet($e, $clase, reporteCsv([
        ['Ana Gómez', 'gs21004@ues.edu.sv', '1 h 10 min'],
        ['Mario Rivas', 'mr21011@ues.edu.sv', '1:09:00'],
        ['Lucía Pérez', 'lp21020@ues.edu.sv', '2 h'],
    ]));

    $ulid = confirmarMeet($e, $reporte);

    expect(DB::table('attendance_sessions')->where('ulid', $ulid)->sole())
        ->modality->toBe('virtual')
        ->status->toBe('closed')
        ->video_session_id->toEqual($clase)
        ->opened_by->toEqual($e['docente']->id)
        ->section_id->toEqual($e['seccion'])
        ->and((string) DB::table('attendance_sessions')->where('ulid', $ulid)->value('session_date'))->toStartWith('2026-10-23');

    $porcentajes = [$e['matricula'] => ['present', 70.0], $e['b'] => ['absent', 69.0], $e['c'] => ['present', 100.0]];
    foreach ($porcentajes as $matricula => [$estado, $porcentaje]) {
        $registro = registroMeet($matricula);
        expect($registro->status)->toBe($estado)
            ->and($registro->method)->toBe('meet')
            ->and((float) $registro->meet_percent)->toEqual($porcentaje);
    }

    expect(DB::table('meet_reports')->where('id', $reporte)->first())
        ->confirmed_at->not->toBeNull()
        ->and((bool) DB::table('meet_reports')->where('id', $reporte)->value('is_current'))->toBeTrue();
});

it('CP-RF21-07 el segundo reporte reemplaza al primero, actualiza la asistencia y no toca la justificada', function () {
    $e = escenarioMeet();
    $clase = claseVirtual($e);
    $primero = subirMeet($e, $clase, reporteCsv([['Ana Gómez', 'gs21004@ues.edu.sv', '70 min'], ['Mario Rivas', 'mr21011@ues.edu.sv', '30 min']]));
    confirmarMeet($e, $primero);
    $registroAna = registroMeet($e['matricula'])->id;
    DbContext::system(fn () => DB::table('attendance_records')->where('enrollment_id', $e['c'])->update([
        'status' => 'justified', 'corrected_by' => $e['docente']->id, 'correction_reason' => 'Justificación aceptada: cita médica.', 'updated_at' => now(),
    ]));

    $segundo = subirMeet($e, $clase, reporteCsv([
        ['Ana Gómez', 'gs21004@ues.edu.sv', '20 min'],
        ['Mario Rivas', 'mr21011@ues.edu.sv', '1 h 30 min'],
        ['Lucía Pérez', 'lp21020@ues.edu.sv', '100 min'],
    ]));
    confirmarMeet($e, $segundo);

    expect(DB::table('meet_reports')->where('video_session_id', $clase)->where('is_current', true)->pluck('id')->map(fn ($id) => (int) $id)->all())->toBe([$segundo])
        ->and(DB::table('meet_reports')->where('id', $primero)->value('confirmed_at'))->not->toBeNull()
        ->and(DB::table('attendance_sessions')->count())->toBe(1)
        ->and(DB::table('attendance_records')->count())->toBe(3);

    expect(registroMeet($e['matricula']))
        ->id->toBe($registroAna)
        ->status->toBe('absent')
        ->and((float) registroMeet($e['matricula'])->meet_percent)->toEqual(20.0)
        ->and(registroMeet($e['b'])->status)->toBe('present')
        ->and(registroMeet($e['c']))
        ->status->toBe('justified')
        ->correction_reason->toBe('Justificación aceptada: cita médica.');
});

it('CP-RF21-07 el reemplazo del reporte queda en la auditoría como meet_report.replaced', function () {
    $e = escenarioMeet();
    $clase = claseVirtual($e);
    $primero = subirMeet($e, $clase, reporteCsv([['Ana Gómez', 'gs21004@ues.edu.sv', '70 min']]));
    confirmarMeet($e, $primero);
    $segundo = subirMeet($e, $clase, reporteCsv([['Ana Gómez', 'gs21004@ues.edu.sv', '20 min']]));
    confirmarMeet($e, $segundo);

    DbContext::auditScope(all: true);
    $eventos = DB::table('audit_logs')->where('entity_type', 'meet_reports')->orderBy('id')->get(['entity_id', 'action', 'event_type', 'actor_user_id']);
    expect($eventos->pluck('event_type')->unique()->values()->all())->toContain('meet_report.uploaded', 'meet_report.confirmed', 'meet_report.replaced')
        ->and($eventos->where('event_type', 'meet_report.replaced')->where('action', 'update')->pluck('entity_id')->map(fn ($id) => (int) $id)->sort()->values()->all())->toBe([$primero, $segundo])
        ->and($eventos->pluck('actor_user_id')->map(fn ($id) => (int) $id)->unique()->values()->all())->toBe([$e['docente']->id]);
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'La auditoría la escriben los triggers, solo en PostgreSQL (ADR-033).');

it('CP-RF21-08 rechaza con un aviso el reporte cuya fecha no coincide con la de la clase', function (string $formato) {
    $e = escenarioMeet();
    $filas = [['Ana Gómez', 'gs21004@ues.edu.sv', '80 min']];
    $archivo = $formato === 'csv' ? reporteCsv($filas, entrada: '2026-10-22 07:02:00') : reporteXlsx($filas, '22/10/2026');

    expect(erroresDeValidacion(fn () => subirMeet($e, claseVirtual($e), $archivo)))->toHaveKey('archivo')
        ->and(DB::table('meet_reports')->count())->toBe(0)
        ->and(DB::table('meet_report_rows')->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('meet'))->toBeEmpty();
})->with(['csv', 'xlsx']);

it('rechaza un reporte que no trae la fecha de la reunión', function () {
    $e = escenarioMeet();
    $archivo = reporteCsv([['Ana Gómez', 'gs21004@ues.edu.sv', '80 min']], ['Nombre', 'Correo electrónico', 'Duración']);

    expect(erroresDeValidacion(fn () => subirMeet($e, claseVirtual($e), $archivo)))->toHaveKey('archivo')
        ->and(DB::table('meet_reports')->count())->toBe(0);
});

it('rechaza un archivo sin las columnas del reporte de Meet e indica las que espera', function () {
    $e = escenarioMeet();
    $archivo = reporteCsv([['Ana Gómez', 'gs21004@ues.edu.sv', '80']], ['Alumno', 'Cuenta', 'Minutos conectado']);

    $errores = erroresDeValidacion(fn () => subirMeet($e, claseVirtual($e), $archivo));

    expect($errores)->toHaveKey('archivo')
        ->and(mb_strtolower(implode(' ', $errores['archivo'])))->toContain('duraci', 'correo')
        ->and(DB::table('meet_reports')->count())->toBe(0);
});

it('rechaza el reporte de una clase que no ha finalizado o que no es de la sección', function (string $caso) {
    $e = escenarioMeet();
    $clase = $caso === 'no finalizada'
        ? claseVirtual($e, ['starts_at' => '2026-10-23 11:00:00', 'duration_minutes' => 60, 'status' => 'scheduled'])
        : claseVirtual($e, ['section_id' => $e['otra']]);

    expect(erroresDeValidacion(fn () => subirMeet($e, $clase, reporteCsv([['Ana Gómez', 'gs21004@ues.edu.sv', '80 min']]))))->toHaveKey('archivo')
        ->and(DB::table('meet_reports')->count())->toBe(0);
})->with(['no finalizada', 'de otra sección']);

it('redondea a minutos la duración del CSV y lee la fracción de día de una celda numérica de Excel', function (string|float $duracion, int $minutos) {
    $e = escenarioMeet();
    $fila = [['Ana Gómez', 'gs21004@ues.edu.sv', $duracion]];
    $reporte = subirMeet($e, claseVirtual($e), is_float($duracion) ? reporteXlsx($fila) : reporteCsv($fila));

    $previa = servicioMeet()->vistaPrevia($e['docente'], $e['seccion'], $reporte);

    expect(data_get(entradaDeMatricula($previa['presentes']->concat($previa['ausentes']), $e['matricula']), 'minutos'))->toEqual($minutos);
})->with([
    'h:m:s en CSV' => ['1:23:45', 84],
    'fracción de día en Excel' => [0.0576388889, 83],
]);

it('reemplaza el borrador sin confirmar y su archivo cuando se sube otro reporte', function () {
    $e = escenarioMeet();
    $clase = claseVirtual($e);
    $primero = subirMeet($e, $clase, reporteCsv([['Ana Gómez', 'gs21004@ues.edu.sv', '80 min']]));
    $archivoAnterior = DB::table('meet_reports')->where('id', $primero)->value('file_path');

    $segundo = subirMeet($e, $clase, reporteCsv([['Ana Gómez', 'gs21004@ues.edu.sv', '40 min']]));

    expect(DB::table('meet_reports')->pluck('id')->map(fn ($id) => (int) $id)->all())->toBe([$segundo])
        ->and(DB::table('meet_report_rows')->where('meet_report_id', $primero)->count())->toBe(0);
    Storage::disk('local')->assertMissing($archivoAnterior);
});

it('encola la evaluación de alertas de inasistencia de la sección al confirmar el reporte', function () {
    $e = escenarioMeet();
    $reporte = subirMeet($e, claseVirtual($e), reporteCsv([['Ana Gómez', 'gs21004@ues.edu.sv', '80 min']]));
    Queue::assertNotPushed(EvaluarAlertasAsistencia::class);

    confirmarMeet($e, $reporte);

    Queue::assertPushed(EvaluarAlertasAsistencia::class, fn (EvaluarAlertasAsistencia $job) => $job->seccionId === $e['seccion']);
});

it('rechaza programar, subir, revisar y confirmar a un docente de otra sección y a un estudiante, sin escribir nada', function (string $quien) {
    $e = escenarioMeet();
    $clase = claseVirtual($e);
    $reporte = subirMeet($e, $clase, reporteCsv([['Invitado celular', '', '80 min']]));
    $fila = filaMeet($reporte, 'Invitado celular');
    $actor = $quien === 'docente ajeno' ? docenteAjeno($e) : $e['estudiante'];
    actingAs($actor);
    $meet = servicioMeet();

    expect(fn () => $meet->programar($actor, $e['seccion'], 'Repaso', '2026-10-30 07:00', 60, null))->toThrow(AuthorizationException::class)
        ->and(fn () => $meet->subir($actor, $e['seccion'], $clase, reporteCsv([['Ana Gómez', 'gs21004@ues.edu.sv', '80 min']])))->toThrow(AuthorizationException::class)
        ->and(fn () => $meet->vistaPrevia($actor, $e['seccion'], $reporte))->toThrow(AuthorizationException::class)
        ->and(fn () => $meet->asignar($actor, $e['seccion'], $fila, $e['matricula']))->toThrow(AuthorizationException::class)
        ->and(fn () => $meet->descartar($actor, $e['seccion'], $fila))->toThrow(AuthorizationException::class)
        ->and(fn () => $meet->confirmar($actor, $e['seccion'], $reporte))->toThrow(AuthorizationException::class)
        ->and(fn () => $meet->grabacion($actor, $e['seccion'], $clase, 'https://drive.google.com/file/d/abc/view'))->toThrow(AuthorizationException::class);

    expect(DB::table('video_sessions')->count())->toBe(1)
        ->and(DB::table('video_sessions')->value('recording_url'))->toBeNull()
        ->and(DB::table('meet_reports')->pluck('id')->map(fn ($id) => (int) $id)->all())->toBe([$reporte])
        ->and(DB::table('meet_report_rows')->where('id', $fila)->value('status'))->toBe('unmatched')
        ->and(DB::table('attendance_records')->count())->toBe(0);
})->with(['docente ajeno', 'estudiante']);

it('no deja a un docente operar el reporte de otra sección indicando la sección que sí imparte', function () {
    $e = escenarioMeet();
    $clase = claseVirtual($e);
    $reporte = subirMeet($e, $clase, reporteCsv([['Invitado celular', '', '80 min']]));
    $fila = filaMeet($reporte, 'Invitado celular');
    $ajeno = docenteAjeno($e);
    actingAs($ajeno);
    $meet = servicioMeet();
    $rechazado = function (callable $accion): bool {
        try {
            $accion();
        } catch (AuthorizationException|ValidationException|RecordsNotFoundException|RecordNotFoundException|HttpException) {
            return true;
        }

        return false;
    };

    expect($rechazado(fn () => $meet->subir($ajeno, $e['otra'], $clase, reporteCsv([['Ana Gómez', 'gs21004@ues.edu.sv', '80 min']]))))->toBeTrue()
        ->and($rechazado(fn () => $meet->vistaPrevia($ajeno, $e['otra'], $reporte)))->toBeTrue()
        ->and($rechazado(fn () => $meet->asignar($ajeno, $e['otra'], $fila, $e['matricula'])))->toBeTrue()
        ->and($rechazado(fn () => $meet->descartar($ajeno, $e['otra'], $fila)))->toBeTrue()
        ->and($rechazado(fn () => $meet->confirmar($ajeno, $e['otra'], $reporte)))->toBeTrue()
        ->and($rechazado(fn () => $meet->grabacion($ajeno, $e['otra'], $clase, 'https://drive.google.com/file/d/abc/view')))->toBeTrue();

    expect(DB::table('meet_reports')->pluck('id')->map(fn ($id) => (int) $id)->all())->toBe([$reporte])
        ->and(DB::table('meet_reports')->where('id', $reporte)->value('confirmed_at'))->toBeNull()
        ->and(DB::table('meet_report_rows')->where('id', $fila)->value('status'))->toBe('unmatched')
        ->and(DB::table('video_sessions')->value('recording_url'))->toBeNull();
});

it('deja ver las clases virtuales al estudiante matriculado y al coordinador, y no a quien no tiene la sección', function () {
    $e = escenarioMeet();
    claseVirtual($e, ['topic' => 'Recursividad en Python']);

    expect(servicioMeet()->clases($e['estudiante'], $e['seccion'])->pluck('topic')->all())->toBe(['Recursividad en Python'])
        ->and(servicioMeet()->clases($e['coordinador'], $e['seccion']))->toHaveCount(1)
        ->and(fn () => servicioMeet()->clases(docenteAjeno($e), $e['seccion']))->toThrow(AuthorizationException::class)
        ->and(fn () => servicioMeet()->clases(usuarioCon('estudiante'), $e['seccion']))->toThrow(AuthorizationException::class);
});

it('actualiza y guarda el estado de cada clase según la hora al listarlas', function () {
    $e = escenarioMeet();
    $finalizada = claseVirtual($e, ['starts_at' => '2026-10-23 07:00:00', 'duration_minutes' => 100, 'status' => 'scheduled']);
    $enCurso = claseVirtual($e, ['starts_at' => '2026-10-23 09:30:00', 'duration_minutes' => 60, 'status' => 'scheduled']);
    $programada = claseVirtual($e, ['starts_at' => '2026-10-24 07:00:00', 'duration_minutes' => 60, 'status' => 'scheduled']);
    actingAs($e['docente']);

    $estados = servicioMeet()->clases($e['docente'], $e['seccion'])->mapWithKeys(fn ($c) => [(int) $c->id => $c->status])->all();

    $esperado = [$finalizada => 'finished', $enCurso => 'in_progress', $programada => 'scheduled'];
    expect($estados)->toEqual($esperado)
        ->and(DB::table('video_sessions')->pluck('status', 'id')->mapWithKeys(fn ($s, $id) => [(int) $id => $s])->all())->toEqual($esperado);
});

it('muestra en la lista el borrador y, al confirmar, el reporte vigente, la sesión de asistencia y los presentes', function () {
    $e = escenarioMeet();
    $clase = claseVirtual($e);
    $reporte = subirMeet($e, $clase, reporteCsv([['Ana Gómez', 'gs21004@ues.edu.sv', '80 min'], ['Mario Rivas', 'mr21011@ues.edu.sv', '75 min']]));

    $antes = servicioMeet()->clases($e['docente'], $e['seccion'])->sole();
    expect($antes->borrador_id)->toEqual($reporte)
        ->and($antes->reporte_id)->toBeNull()
        ->and($antes->sesion_ulid)->toBeNull();

    $ulid = confirmarMeet($e, $reporte);

    expect(servicioMeet()->clases($e['docente'], $e['seccion'])->sole())
        ->reporte_id->toEqual($reporte)
        ->borrador_id->toBeNull()
        ->sesion_ulid->toBe($ulid)
        ->presentes->toEqual(2);
});

it('programa una clase virtual con tema, inicio, duración y enlace de Meet', function () {
    $e = escenarioMeet();
    actingAs($e['docente']);

    $id = servicioMeet()->programar($e['docente'], $e['seccion'], 'Recursividad', '2026-10-30 07:00', 90, 'https://meet.google.com/abc-defg-hij');

    $clase = DB::table('video_sessions')->where('id', $id)->sole();
    expect($clase)
        ->section_id->toEqual($e['seccion'])
        ->topic->toBe('Recursividad')
        ->duration_minutes->toEqual(90)
        ->status->toBe('scheduled')
        ->meet_url->toBe('https://meet.google.com/abc-defg-hij')
        ->and(Carbon::parse($clase->starts_at)->format('Y-m-d H:i'))->toBe('2026-10-30 07:00');
});

it('rechaza programar una clase de duración fuera de 15 a 300 minutos o fuera del período', function (string $inicio, int $duracion) {
    $e = escenarioMeet();
    actingAs($e['docente']);

    expect(fn () => servicioMeet()->programar($e['docente'], $e['seccion'], 'Recursividad', $inicio, $duracion, null))->toThrow(ValidationException::class)
        ->and(DB::table('video_sessions')->count())->toBe(0);
})->with([
    'menos de 15 minutos' => ['2026-10-30 07:00', 14],
    'más de 300 minutos' => ['2026-10-30 07:00', 301],
    'después del período' => ['2026-12-20 07:00', 60],
]);

it('asocia la grabación a la clase y el estudiante matriculado la ve en la lista', function () {
    $e = escenarioMeet();
    $clase = claseVirtual($e);
    actingAs($e['docente']);

    servicioMeet()->grabacion($e['docente'], $e['seccion'], $clase, 'https://drive.google.com/file/d/abc/view');

    expect(servicioMeet()->clases($e['estudiante'], $e['seccion'])->sole()->recording_url)->toBe('https://drive.google.com/file/d/abc/view');
});

it('muestra al docente el botón para subir el reporte de una clase finalizada y al estudiante solo la lista', function () {
    $e = escenarioMeet();
    claseVirtual($e, ['topic' => 'Recursividad en Python']);

    actingAs($e['docente']);
    Livewire::test(ClasesVirtuales::class, ['seccionId' => $e['seccion']])->assertSee('Recursividad en Python')->assertSee('Subir reporte');

    actingAs($e['estudiante']);
    Livewire::test(ClasesVirtuales::class, ['seccionId' => $e['seccion']])->assertSee('Recursividad en Python')->assertDontSee('Subir reporte');
});

it('audita la programación de la clase virtual con el docente como actor', function () {
    $e = escenarioMeet();
    actingAs($e['docente']);

    $id = servicioMeet()->programar($e['docente'], $e['seccion'], 'Recursividad', '2026-10-30 07:00', 90, null);

    DbContext::auditScope(all: true);
    $evento = DB::table('audit_logs')->where('entity_type', 'video_sessions')->where('entity_id', $id)->where('action', 'insert')->sole();
    expect($evento->event_type)->toBe('video_session.scheduled')
        ->and((int) $evento->actor_user_id)->toBe($e['docente']->id);
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'La auditoría la escriben los triggers, solo en PostgreSQL (ADR-033).');
