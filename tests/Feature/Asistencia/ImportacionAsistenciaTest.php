<?php

use App\Modules\Asistencia\Livewire\SesionAsistencia;
use App\Modules\Asistencia\Services\AsistenciaService;
use App\Modules\Asistencia\Services\ImportacionAsistenciaService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Importación de la asistencia de una sesión desde Excel o CSV (RF19, CP-RF19-02): carné y estado por fila;
 * solo cuentan los matriculados en el grupo.
 */

/** Un CSV con las filas dadas, cada una [carné, estado], bajo el encabezado indicado. */
function asistenciaCsv(array $filas, array $encabezado = ['Carné', 'Estado'], array $antes = []): UploadedFile
{
    $salida = fopen('php://temp', 'r+');
    foreach ([...$antes, $encabezado, ...$filas] as $fila) {
        fputcsv($salida, $fila);
    }
    rewind($salida);

    return UploadedFile::fake()->createWithContent('asistencia.csv', (string) stream_get_contents($salida));
}

/** Una sesión ya cerrada, sin registros, de la sección del escenario. */
function sesionCerradaSinRegistros(array $e): string
{
    $ulid = (string) str()->ulid();
    DbContext::system(fn () => DB::table('attendance_sessions')->insert([
        'ulid' => $ulid, 'section_id' => $e['seccion'], 'session_date' => now()->toDateString(), 'modality' => 'in_person', 'status' => 'closed',
        'opened_at' => now()->subHour(), 'closed_at' => now()->subMinutes(45), 'qr_validity_minutes' => 10, 'qr_rotation_seconds' => 10,
        'late_after_minutes' => 10, 'opened_by' => $e['docente']->id, 'created_at' => now(), 'updated_at' => now(),
    ]));

    return $ulid;
}

/** Un estudiante matriculado en la otra sección del escenario (MAT115), con su carné. */
function estudianteDeOtroGrupo(array $e, string $carne = 'OT22001'): void
{
    $otro = usuarioCon('estudiante');
    DbContext::system(function () use ($e, $otro, $carne) {
        DB::table('student_profiles')->insert(['user_id' => $otro->id, 'student_code' => $carne, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('enrollments')->insert(['student_id' => $otro->id, 'section_id' => $e['otra'], 'period_id' => $e['periodo'], 'subject_id' => $e['mat'],
            'status' => 'active', 'enrolled_via' => 'key', 'enrolled_by' => $otro->id, 'created_at' => now(), 'updated_at' => now()]);
    });
}

it('CP-RF19-02 aplica solo a los matriculados del grupo y rechaza las demás filas con su motivo', function () {
    $e = escenario();
    estudianteDeOtroGrupo($e);
    $sesion = sesionCerradaSinRegistros($e);
    actingAs($e['docente']);

    $previa = app(ImportacionAsistenciaService::class)->previsualizar($e['docente'], $e['seccion'], $sesion, asistenciaCsv([
        ['gs21004', 'Tarde'], ['OT22001', 'presente'], ['', 'presente'], ['GS21004', 'ausente'], ['ZZ00000', 'P'],
    ]));

    expect($previa['aplicables'])->toHaveCount(1)
        ->and($previa['aplicables'][0])->toMatchArray(['matricula' => $e['matricula'], 'carne' => 'GS21004', 'estado' => 'late', 'anterior' => null])
        ->and(collect($previa['rechazadas'])->map(fn ($r) => [$r['fila'], $r['carne'], $r['motivo']])->all())->toBe([
            [3, 'OT22001', 'No está matriculado en este grupo.'],
            [4, '—', 'Falta el carné.'],
            [5, 'GS21004', 'El carné se repite en el archivo.'],
            [6, 'ZZ00000', 'No está matriculado en este grupo.'],
        ]);
    expect(DB::table('attendance_records')->count())->toBe(0);
});

it('CP-RF19-02 rechaza un estado que no es presente, tarde, ausente ni justificada', function () {
    $e = escenario();
    $sesion = sesionCerradaSinRegistros($e);
    actingAs($e['docente']);

    $previa = app(ImportacionAsistenciaService::class)->previsualizar($e['docente'], $e['seccion'], $sesion, asistenciaCsv([['GS21004', 'quizás']]));

    expect($previa['aplicables'])->toBe([])->and($previa['rechazadas'][0]['motivo'])->toContain('Estado no válido');
});

it('CP-RF19-02 crea el registro con el método «Importado» y corrige el que ya había con el archivo como motivo', function () {
    $e = escenario();
    $sesion = sesionCerradaSinRegistros($e);
    actingAs($e['docente']);
    $importacion = app(ImportacionAsistenciaService::class);

    expect($importacion->importar($e['docente'], $e['seccion'], $sesion, asistenciaCsv([['GS21004', 'ausente']])))->toBe(1);
    expect(DB::table('attendance_records')->first())->status->toBe('absent')->method->toBe('import');

    expect($importacion->importar($e['docente'], $e['seccion'], $sesion, asistenciaCsv([['GS21004', 'justificada']])))->toBe(1);
    $registro = DB::table('attendance_records')->first();
    expect($registro->status)->toBe('justified')
        ->and((int) $registro->corrected_by)->toBe($e['docente']->id)
        ->and($registro->correction_reason)->toBe('Importado desde el archivo «asistencia.csv».');

    // Repetir el mismo archivo no cambia nada.
    expect($importacion->importar($e['docente'], $e['seccion'], $sesion, asistenciaCsv([['GS21004', 'justificada']])))->toBe(0);
});

it('CP-RF19-02 encuentra el encabezado aunque haya un título encima, como en las exportaciones del sistema', function () {
    $e = escenario();
    $sesion = sesionCerradaSinRegistros($e);
    actingAs($e['docente']);

    $previa = app(ImportacionAsistenciaService::class)->previsualizar($e['docente'], $e['seccion'], $sesion,
        asistenciaCsv([['Pérez, Ana', 'GS21004', 'presente']], ['Nombre', 'Carnet', 'Asistencia'], [['Universidad de El Salvador'], ['Lista de asistencia']]));

    expect($previa['rechazadas'])->toBe([])->and($previa['aplicables'][0])->toMatchArray(['carne' => 'GS21004', 'estado' => 'present']);
    expect(fn () => app(ImportacionAsistenciaService::class)->previsualizar($e['docente'], $e['seccion'], $sesion, asistenciaCsv([['GS21004', 'presente']], ['Alumno', 'Nota'])))
        ->toThrow(ValidationException::class, 'una columna «Carné» y otra «Estado»');
});

it('CP-RF19-02 no importa en una sesión abierta ni deja importar a quien no imparte la sección', function () {
    $e = escenario();
    actingAs($e['docente']);
    $abierta = app(AsistenciaService::class)->abrir($e['docente'], $e['seccion'], 10);
    $importacion = app(ImportacionAsistenciaService::class);

    expect(fn () => $importacion->previsualizar($e['docente'], $e['seccion'], $abierta, asistenciaCsv([['GS21004', 'presente']])))
        ->toThrow(ValidationException::class, 'Cierre la sesión antes de importar');

    $cerrada = sesionCerradaSinRegistros($e);
    foreach (['estudiante', 'coordinador', 'jefe'] as $rol) {
        expect(fn () => $importacion->importar($e[$rol], $e['seccion'], $cerrada, asistenciaCsv([['GS21004', 'presente']])))->toThrow(AuthorizationException::class);
    }
});

it('CP-RF19-02 la pantalla de la sesión muestra la vista previa y confirma la importación', function () {
    $e = escenario();
    estudianteDeOtroGrupo($e);
    $sesion = sesionCerradaSinRegistros($e);
    actingAs($e['docente']);

    Livewire::test(SesionAsistencia::class, ['seccion' => $e['ulid'], 'sesion' => $sesion])
        ->assertSee('Importar desde Excel o CSV')
        ->set('archivo', asistenciaCsv([['GS21004', 'tarde'], ['OT22001', 'presente']]))
        ->call('previsualizar')
        ->assertSee('1 fila se aplica')->assertSee('No está matriculado en este grupo.')
        ->call('importar')
        ->assertDispatched('aviso', tipo: 'exito')
        ->assertSet('previa', null);

    expect(DB::table('attendance_records')->where('enrollment_id', $e['matricula'])->value('status'))->toBe('late');
});
