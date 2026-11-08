<?php

use App\Modules\Asistencia\Livewire\AreasAsistencia;
use App\Modules\Asistencia\Livewire\AsistenciaSeccion;
use App\Modules\Asistencia\Livewire\ClasesCanceladas;
use App\Modules\Asistencia\Livewire\EscanearQr;
use App\Modules\Asistencia\Livewire\SesionAsistencia;
use App\Modules\Asistencia\Services\AsistenciaService;
use App\Modules\Comunicacion\Notifications\Aviso;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function sesionAbierta(array $e, int $minutos = 10): string
{
    actingAs($e['docente']);

    return app(AsistenciaService::class)->abrir($e['docente'], $e['seccion'], $minutos);
}

it('opens a session between 5 and 15 minutes, only one at a time', function () {
    $e = escenario();
    actingAs($e['docente']);

    Livewire::test(AsistenciaSeccion::class, ['seccion' => $e['ulid']])->set('minutos', 20)->call('abrir')->assertHasErrors(['minutos']);
    Livewire::test(AsistenciaSeccion::class, ['seccion' => $e['ulid']])->set('minutos', 10)->call('abrir')->assertRedirect();

    expect(fn () => sesionAbierta($e))->toThrow(ValidationException::class);
});

it('CP-RF28-03 registra la asistencia con el código temporal y no deja reutilizarlo', function () {
    $e = escenario();
    $sesion = sesionAbierta($e);
    $codigo = app(AsistenciaService::class)->generarCodigo($e['docente'], $e['seccion'], $sesion, $e['matricula']);
    actingAs($e['estudiante']);

    Livewire::test(EscanearQr::class)->set('codigo', '000000')->call('usarCodigo')->assertHasErrors(['codigo']);
    Livewire::test(EscanearQr::class)->set('codigo', $codigo)->call('usarCodigo')->assertHasNoErrors()->assertSee('Asistencia registrada');
    Livewire::test(EscanearQr::class)->set('codigo', $codigo)->call('usarCodigo')->assertHasErrors(['codigo']);

    expect(DB::table('attendance_records')->where('enrollment_id', $e['matricula'])->value('method'))->toBe('code');
});

it('CP-RF28-02 rechaza el código temporal que usa otra cuenta', function () {
    $e = escenario();
    $sesion = sesionAbierta($e);
    $codigo = app(AsistenciaService::class)->generarCodigo($e['docente'], $e['seccion'], $sesion, $e['matricula']);
    $companero = usuarioCon('estudiante');
    DbContext::system(fn () => DB::table('enrollments')->insert(['student_id' => $companero->id, 'section_id' => $e['seccion'], 'period_id' => $e['periodo'], 'subject_id' => $e['prn'],
        'status' => 'active', 'enrolled_via' => 'key', 'enrolled_by' => $companero->id, 'created_at' => now(), 'updated_at' => now()]));
    actingAs($companero);

    expect(fn () => app(AsistenciaService::class)->usarCodigo($companero, $codigo))->toThrow(ValidationException::class);

    expect(DB::table('attendance_records')->count())->toBe(0)
        ->and(DB::table('temporary_attendance_codes')->whereNotNull('used_at')->count())->toBe(0);
});

it('CP-RF28-04 no genera el código para un estudiante que ya tiene asistencia en la sesión', function () {
    $e = escenario();
    $sesion = sesionAbierta($e);
    app(AsistenciaService::class)->marcar($e['docente'], $e['seccion'], $sesion, $e['matricula'], 'present');

    expect(fn () => app(AsistenciaService::class)->generarCodigo($e['docente'], $e['seccion'], $sesion, $e['matricula']))
        ->toThrow(ValidationException::class, 'ya tiene registro');

    expect(DB::table('temporary_attendance_codes')->count())->toBe(0);
});

it('CP-RF19-01 el docente marca a mano presente, ausente o tardanza', function (string $estado) {
    $e = escenario();
    $sesion = sesionAbierta($e);

    app(AsistenciaService::class)->marcar($e['docente'], $e['seccion'], $sesion, $e['matricula'], $estado);

    $registro = DB::table('attendance_records')->where('enrollment_id', $e['matricula'])->sole();
    expect($registro->status)->toBe($estado)->and($registro->method)->toBe('manual');
})->with(['present', 'absent', 'late']);

it('CP-RF28-01 rechaza un código temporal con más de 30 s', function () {
    $e = escenario();
    $sesion = sesionAbierta($e);
    $codigo = app(AsistenciaService::class)->generarCodigo($e['docente'], $e['seccion'], $sesion, $e['matricula']);
    $this->travel(31)->seconds();

    expect(fn () => app(AsistenciaService::class)->usarCodigo($e['estudiante'], $codigo))->toThrow(ValidationException::class);
});

it('CP-RF19-04 requires a reason to correct a record and closes the session marking the rest absent', function () {
    $e = escenario();
    $sesion = sesionAbierta($e);
    actingAs($e['docente']);

    Livewire::test(SesionAsistencia::class, ['seccion' => $e['ulid'], 'sesion' => $sesion])->call('cerrar');
    expect(DB::table('attendance_records')->where('enrollment_id', $e['matricula'])->value('status'))->toBe('absent');

    Livewire::test(SesionAsistencia::class, ['seccion' => $e['ulid'], 'sesion' => $sesion])
        ->call('abrirCorreccion', $e['matricula'], 'present')->call('corregir')->assertHasErrors(['motivo'])
        ->set('motivo', 'Llegó con el docente.')->call('corregir')->assertHasNoErrors();

    $registro = DB::table('attendance_records')->where('enrollment_id', $e['matricula'])->first();
    expect($registro->status)->toBe('present')->and($registro->correction_reason)->toBe('Llegó con el docente.');
});

it('CP-RF19-03 lets the student justify an absence and the teacher accept it', function () {
    $e = escenario();
    $sesion = sesionAbierta($e);
    app(AsistenciaService::class)->cerrar($e['docente'], $e['seccion'], $sesion);
    $registro = (int) DB::table('attendance_records')->value('id');

    actingAs($e['estudiante']);
    Livewire::test(AsistenciaSeccion::class, ['seccion' => $e['ulid']])->assertSee('Mi historial')
        ->call('pedirJustificacion', $registro)->set('motivoJustificacion', 'Cita médica en el ISSS.')->call('justificar')->assertHasNoErrors();

    actingAs($e['docente']);
    Livewire::test(SesionAsistencia::class, ['seccion' => $e['ulid'], 'sesion' => $sesion])->assertSee('Cita médica')
        ->call('revisar', (int) DB::table('attendance_justifications')->value('id'), true);

    expect(DB::table('attendance_records')->where('id', $registro)->value('status'))->toBe('justified');
});

it('counts a late arrival as half when computing the percentage', function () {
    $e = escenario();
    foreach (['present', 'late'] as $estado) {
        $sesion = sesionAbierta($e);
        app(AsistenciaService::class)->marcar($e['docente'], $e['seccion'], $sesion, $e['matricula'], $estado);
        app(AsistenciaService::class)->cerrar($e['docente'], $e['seccion'], $sesion);
    }

    expect(app(AsistenciaService::class)->miAsistencia($e['estudiante'])->first()->porcentaje)->toEqual(75.0);
});

it('lets the coordinator approve a class cancellation requested by the teacher', function () {
    $e = escenario();
    actingAs($e['docente']);
    app(AsistenciaService::class)->solicitarCancelacion($e['docente'], $e['seccion'], now()->addDay()->toDateString(), 'cancellation', 'Fumigación del edificio.');
    actingAs($e['coordinador']);

    Livewire::test(ClasesCanceladas::class)->assertSee('Fumigación del edificio.')->call('resolver', (int) DB::table('class_cancellations')->value('id'), true);

    expect(DB::table('class_cancellations')->value('status'))->toBe('approved');
});

it('validates the attendance area radius', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(AreasAsistencia::class)->call('nueva')->set('nombre', 'Campus')->set('latitud', '13.4393')->set('longitud', '-88.1597')->set('radio', 5)->call('guardar')->assertHasErrors(['radio']);
    Livewire::test(AreasAsistencia::class)->call('nueva')->set('nombre', 'Campus')->set('latitud', '13.4393')->set('longitud', '-88.1597')->set('radio', 450)->call('guardar')->assertHasNoErrors();
});

/** Cuadrado de unos 200 m de lado alrededor del campus FMO, como lo marcaría el Administrador en el mapa. */
function poligonoCampus(): array
{
    return [[13.4384, -88.1606], [13.4384, -88.1588], [13.4402, -88.1588], [13.4402, -88.1606]];
}

it('CP-RF26-01 el Administrador define un área por polígono y queda disponible para las sesiones', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(AreasAsistencia::class)->call('nueva')->set('nombre', 'Edificio de Ingeniería')->set('tipo', 'polygon')
        ->set('vertices', poligonoCampus())->call('guardar')->assertHasNoErrors();

    $area = DB::table('attendance_areas')->sole();
    expect($area->area_type)->toBe('polygon')
        ->and(json_decode($area->polygon, true))->toBe(poligonoCampus())
        ->and($area->radius_m)->toBeNull()
        ->and(round((float) $area->center_lat, 4))->toBe(13.4393)
        ->and(round((float) $area->center_lng, 4))->toBe(-88.1597);

    actingAs($e['administrador'])->get(route('areas-asistencia'))->assertOk()->assertSeeText('Polígono · 4 vértices');
    expect(DB::table('attendance_sessions')->where('ulid', sesionAbierta($e))->value('area_id'))->toBe($area->id);
});

it('CP-RF26-01 el Administrador sigue definiendo áreas por centro y radio, y puede cambiar la forma de un área', function () {
    $e = escenario();
    actingAs($e['administrador']);
    $asistencia = app(AsistenciaService::class);

    $asistencia->guardarArea($e['administrador'], null, 'Campus', 13.4393, -88.1597, 150);
    $id = (int) DB::table('attendance_areas')->value('id');
    $asistencia->guardarPoligono($e['administrador'], $id, 'Campus', poligonoCampus());
    expect(DB::table('attendance_areas')->sole())->area_type->toBe('polygon')->radius_m->toBeNull();

    $asistencia->guardarArea($e['administrador'], $id, 'Campus', 13.4393, -88.1597, 200);
    expect(DB::table('attendance_areas')->sole())->area_type->toBe('circle')->radius_m->toBe(200)->polygon->toBeNull();
});

it('CP-RF26-01 rechaza un polígono con pocos vértices, coordenadas fuera de rango o lados que se cruzan', function (array $vertices, string $mensaje) {
    $e = escenario();
    actingAs($e['administrador']);

    expect(fn () => app(AsistenciaService::class)->guardarPoligono($e['administrador'], null, 'Malo', $vertices))
        ->toThrow(ValidationException::class, $mensaje);
    expect(DB::table('attendance_areas')->exists())->toBeFalse();
})->with([
    'dos vértices' => [[[13.4384, -88.1606], [13.4402, -88.1588]], 'entre 3 y 50 vértices'],
    'anillo de GeoJSON con solo dos vértices' => [[[13.4384, -88.1606], [13.4402, -88.1588], [13.4384, -88.1606]], 'entre 3 y 50 vértices'],
    'latitud fuera de rango' => [[[13.4384, -88.1606], [95, -88.1588], [13.4402, -88.1588]], 'fuera de rango'],
    'vértice que no es número' => [[[13.4384, -88.1606], ['norte', -88.1588], [13.4402, -88.1588]], 'fuera de rango'],
    'en forma de ocho' => [[[13.4384, -88.1606], [13.4402, -88.1588], [13.4384, -88.1588], [13.4402, -88.1606]], 'se cruzan'],
]);

it('CP-RF26-01 pide al menos 3 vértices marcados en el formulario', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(AreasAsistencia::class)->call('nueva')->set('nombre', 'Aula')->set('tipo', 'polygon')
        ->set('vertices', [[13.4384, -88.1606]])->call('guardar')->assertHasErrors(['vertices']);
    expect(DB::table('attendance_areas')->exists())->toBeFalse();
});

it('CP-RF26-01 solo el Administrador configura las áreas', function (string $rol) {
    $e = escenario();
    actingAs($e[$rol]);

    expect(fn () => app(AsistenciaService::class)->guardarPoligono($e[$rol], null, 'Campus', poligonoCampus()))->toThrow(AuthorizationException::class);
    actingAs($e[$rol])->get(route('areas-asistencia'))->assertForbidden();
})->with(['docente', 'coordinador', 'soporte']);

it('offers to fill the area center with the device location', function () {
    $e = escenario();

    actingAs($e['administrador'])->get(route('areas-asistencia'))->assertOk()
        ->assertSeeText('Usar mi ubicación')->assertSee('getCurrentPosition', false);
});

it('renders my attendance for the student', function () {
    $e = escenario();

    actingAs($e['estudiante'])->get(route('mi-asistencia'))->assertOk()->assertSeeText(['Mi asistencia', 'PRN115']);
});

it('rejects attendance actions outside the teacher scope with an authorization error and writes nothing', function (string $quien) {
    $e = escenario();
    $sesion = sesionAbierta($e);
    $actor = $quien === 'ajeno' ? usuarioCon('docente') : $e['estudiante'];
    $asistencia = app(AsistenciaService::class);

    expect(fn () => $asistencia->marcar($actor, $e['seccion'], $sesion, $e['matricula'], 'present'))->toThrow(AuthorizationException::class)
        ->and(fn () => $asistencia->generarCodigo($actor, $e['seccion'], $sesion, $e['matricula']))->toThrow(AuthorizationException::class)
        ->and(fn () => $asistencia->cerrar($actor, $e['seccion'], $sesion))->toThrow(AuthorizationException::class);

    expect(DB::table('attendance_records')->count())->toBe(0)
        ->and(DB::table('temporary_attendance_codes')->count())->toBe(0);
})->with(['ajeno', 'estudiante']);

it('rejects an unknown attendance status as a validation error', function () {
    $e = escenario();
    $sesion = sesionAbierta($e);

    expect(fn () => app(AsistenciaService::class)->marcar($e['docente'], $e['seccion'], $sesion, $e['matricula'], 'excused_forever'))
        ->toThrow(ValidationException::class);

    expect(DB::table('attendance_records')->count())->toBe(0);
});

/**
 * Dos clases del estudiante: el lunes 7 llega y el martes 8 falta. Sin cancelaciones, su asistencia es 50 %.
 */
function asistenciaDosClases(array $e): void
{
    $asistencia = app(AsistenciaService::class);
    foreach (['2026-09-07' => 'present', '2026-09-08' => 'absent'] as $dia => $estado) {
        test()->travelTo($dia.' 08:00');
        $sesion = sesionAbierta($e);
        $asistencia->marcar($e['docente'], $e['seccion'], $sesion, $e['matricula'], $estado);
        $asistencia->cerrar($e['docente'], $e['seccion'], $sesion);
    }
    test()->travelBack();
}

it('CP-RF32-01 las clases de un día cancelado no cuentan en el porcentaje de asistencia', function (string $alcance) {
    $e = escenario();
    asistenciaDosClases($e);
    $asistencia = app(AsistenciaService::class);
    expect($asistencia->miAsistencia($e['estudiante'])->sole()->porcentaje)->toEqual(50.0);

    if ($alcance === 'sección') {
        actingAs($e['docente']);
        $asistencia->solicitarCancelacion($e['docente'], $e['seccion'], '2026-09-08', 'cancellation', 'Fumigación del edificio.');
        actingAs($e['coordinador']);
        $asistencia->resolverCancelacion($e['coordinador'], (int) DB::table('class_cancellations')->value('id'), true);
    } else {
        // El Coordinador cancela su carrera y el Administrador, toda la facultad.
        $actor = $alcance === 'carrera' ? $e['coordinador'] : $e['administrador'];
        actingAs($actor);
        $asistencia->cancelarDirecto($actor, '2026-09-08', 'Asueto.');
    }

    $materia = $asistencia->miAsistencia($e['estudiante'])->sole();
    expect($materia->porcentaje)->toEqual(100.0)
        ->and((int) $materia->clases)->toBe(1)
        ->and((int) $materia->faltas)->toBe(0)
        ->and($asistencia->historial($e['estudiante'], $e['seccion'])['porcentaje'])->toEqual(100.0);
})->with(['sección', 'carrera', 'facultad']);

it('CP-RF32-01 una solicitud sin aprobar o un cambio de modalidad no sacan la clase del porcentaje', function (string $tipo) {
    $e = escenario();
    asistenciaDosClases($e);
    $asistencia = app(AsistenciaService::class);
    actingAs($e['docente']);
    $asistencia->solicitarCancelacion($e['docente'], $e['seccion'], '2026-09-08', $tipo, 'Motivo de prueba.', 'virtual');
    if ($tipo === 'modality_change') {
        actingAs($e['coordinador']);
        $asistencia->resolverCancelacion($e['coordinador'], (int) DB::table('class_cancellations')->value('id'), true);
    }

    expect($asistencia->miAsistencia($e['estudiante'])->sole()->porcentaje)->toEqual(50.0);
})->with(['cancelación pendiente' => 'cancellation', 'cambio de modalidad aprobado' => 'modality_change']);

it('CP-RF32-01 avisa a los estudiantes y docentes de la sección cuando se aprueba la cancelación', function () {
    Notification::fake();
    $e = escenario();
    $asistencia = app(AsistenciaService::class);
    actingAs($e['docente']);
    $asistencia->solicitarCancelacion($e['docente'], $e['seccion'], '2026-09-08', 'cancellation', 'Fumigación del edificio.');
    Notification::assertNothingSent();

    actingAs($e['coordinador']);
    $asistencia->resolverCancelacion($e['coordinador'], (int) DB::table('class_cancellations')->value('id'), true);

    foreach ([$e['estudiante'], $e['docente']] as $afectado) {
        Notification::assertSentTo($afectado, Aviso::class, fn (Aviso $aviso) => $aviso->evento === 'class.cancelled'
            && str_contains($aviso->titulo, 'PRN115-01') && str_contains($aviso->cuerpo, '08/09/2026') && str_contains($aviso->cuerpo, 'Fumigación del edificio.')
            // El aviso lleva a la sección cancelada (RF23 CA4).
            && $aviso->url === route('secciones.contenidos', ['seccion' => $e['ulid']]));
    }
    Notification::assertNotSentTo($e['coordinador'], Aviso::class);
});

it('CP-RF32-01 la cancelación de toda la facultad avisa a todos los matriculados y docentes del período', function () {
    Notification::fake();
    $e = escenario();
    actingAs($e['administrador']);

    app(AsistenciaService::class)->cancelarDirecto($e['administrador'], '2026-09-15', 'Asueto.');

    foreach ([$e['estudiante'], $e['docente']] as $afectado) {
        Notification::assertSentTo($afectado, Aviso::class, fn (Aviso $aviso) => $aviso->evento === 'class.cancelled' && str_contains($aviso->cuerpo, 'toda la facultad'));
    }
});

it('CP-RF32-03 la cancelación queda en la auditoría con su motivo, desde la solicitud hasta la aprobación', function () {
    $e = escenario();
    $asistencia = app(AsistenciaService::class);
    actingAs($e['docente']);
    $asistencia->solicitarCancelacion($e['docente'], $e['seccion'], '2026-09-08', 'cancellation', 'Fumigación del edificio.');
    actingAs($e['coordinador']);
    $asistencia->resolverCancelacion($e['coordinador'], (int) DB::table('class_cancellations')->value('id'), true);

    DbContext::auditScope(all: true);
    $eventos = DB::table('audit_logs')->where('entity_type', 'class_cancellations')->orderBy('id')->get();
    expect($eventos->pluck('event_type')->all())->toBe(['class_cancellation.requested', 'class_cancellation.approved'])
        ->and($eventos->pluck('reason')->unique()->all())->toBe(['Fumigación del edificio.'])
        ->and($eventos->pluck('actor_user_id')->map(fn ($id) => (int) $id)->all())->toBe([$e['docente']->id, $e['coordinador']->id]);
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'La auditoría la escriben los triggers, solo en PostgreSQL (ADR-033).');

/** Un cambio de modalidad aprobado para hoy, del alcance indicado. */
function cambioDeModalidadHoy(array $e, string $modalidad, string $alcance = 'section'): void
{
    DbContext::system(fn () => DB::table('class_cancellations')->insert([
        'scope' => $alcance, 'section_id' => $alcance === 'section' ? $e['seccion'] : null, 'career_id' => $alcance === 'career' ? $e['isi'] : null,
        'date' => now()->toDateString(), 'kind' => 'modality_change', 'new_modality' => $modalidad, 'reason' => 'Prueba de modalidad.',
        'status' => 'approved', 'requested_by' => $e['docente']->id, 'approved_by' => $e['coordinador']->id, 'created_at' => now(), 'updated_at' => now(),
    ]));
}

it('CP-RF32-02 con un cambio a virtual aprobado para hoy no se abre QR: la asistencia va por el reporte de Meet', function (string $alcance) {
    $e = escenario();
    cambioDeModalidadHoy($e, 'virtual', $alcance);
    actingAs($e['docente']);

    expect(fn () => app(AsistenciaService::class)->abrir($e['docente'], $e['seccion'], 10))
        ->toThrow(ValidationException::class, 'reporte de Meet');
    expect(DB::table('attendance_sessions')->count())->toBe(0);

    Livewire::test(AsistenciaSeccion::class, ['seccion' => $e['ulid']])
        ->assertSee('Hoy la clase es virtual')->assertDontSee('Abrir asistencia')->assertSee('Clases virtuales');
})->with(['sección' => 'section', 'carrera' => 'career', 'facultad' => 'faculty']);

it('CP-RF32-02 con un cambio a presencial aprobado para hoy la sesión se abre presencial con QR aunque se pida virtual', function () {
    $e = escenario();
    cambioDeModalidadHoy($e, 'in_person');
    actingAs($e['docente']);

    app(AsistenciaService::class)->abrir($e['docente'], $e['seccion'], 10, 'virtual');

    expect(DB::table('attendance_sessions')->value('modality'))->toBe('in_person');
    Livewire::test(AsistenciaSeccion::class, ['seccion' => $e['ulid']])->assertSet('modalidad', 'in_person')->assertSee('Hoy la clase es presencial');
});

it('CP-RF32-02 un cambio de modalidad de otro día, pendiente o de otra sección no cambia la sesión de hoy', function () {
    $e = escenario();
    DbContext::system(fn () => DB::table('class_cancellations')->insert([
        ['scope' => 'section', 'section_id' => $e['seccion'], 'career_id' => null, 'date' => now()->addDay()->toDateString(), 'kind' => 'modality_change', 'new_modality' => 'virtual',
            'reason' => 'Mañana.', 'status' => 'approved', 'requested_by' => $e['docente']->id, 'approved_by' => $e['coordinador']->id, 'created_at' => now(), 'updated_at' => now()],
        ['scope' => 'section', 'section_id' => $e['seccion'], 'career_id' => null, 'date' => now()->toDateString(), 'kind' => 'modality_change', 'new_modality' => 'virtual',
            'reason' => 'Sin aprobar.', 'status' => 'requested', 'requested_by' => $e['docente']->id, 'approved_by' => null, 'created_at' => now(), 'updated_at' => now()],
        ['scope' => 'section', 'section_id' => $e['otra'], 'career_id' => null, 'date' => now()->toDateString(), 'kind' => 'modality_change', 'new_modality' => 'virtual',
            'reason' => 'Otra sección.', 'status' => 'approved', 'requested_by' => $e['docente']->id, 'approved_by' => $e['coordinador']->id, 'created_at' => now(), 'updated_at' => now()],
    ]));
    actingAs($e['docente']);

    app(AsistenciaService::class)->abrir($e['docente'], $e['seccion'], 10);

    expect(DB::table('attendance_sessions')->value('modality'))->toBe('in_person');
});
