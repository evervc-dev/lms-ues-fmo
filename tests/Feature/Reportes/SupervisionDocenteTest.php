<?php

use App\Models\User;
use App\Modules\Asistencia\Livewire\SesionAsistencia;
use App\Modules\Asistencia\Services\AsistenciaService;
use App\Modules\Comunicacion\Livewire\Foro;
use App\Modules\Comunicacion\Livewire\HiloForo;
use App\Modules\Comunicacion\Services\ForoService;
use App\Support\DbContext;
use App\Support\Navegacion\Vistas;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/*
 * RF39 · Supervisión del trabajo docente (ADR-093). El Jefe (D) y el Coordinador (C) ven en solo lectura las pestañas
 * de la Vista de sección, el detalle de cada sesión de asistencia y el foro. El escenario() tiene un bloque de clase
 * el lunes de 07:00 a 08:40; el 19/10/2026 es lunes y el 24/10/2026, sábado.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['lms.asistencia.bypass_horario' => false]);
    $this->travelTo(Carbon::parse('2026-10-19 10:00'));
    $this->e = escenario();
});

/** Jefe de DCNM: tiene los permisos de supervisión, pero no el alcance sobre PRN115 (DIA). */
function rf39JefeAjeno(array $e): User
{
    $jefe = usuarioCon('jefe_departamento');
    DbContext::system(fn () => DB::table('department_heads')->insert(['user_id' => $jefe->id, 'department_id' => $e['dcnm'], 'valid_from' => '2025-01-01', 'created_at' => now(), 'updated_at' => now()]));

    return $jefe;
}

/** Coordinador de Licenciatura en Matemática (DCNM), cuyo plan lleva MAT115 y no PRN115. */
function rf39CoordinadorAjeno(array $e): User
{
    $coordinador = usuarioCon('coordinador');
    DbContext::system(function () use ($coordinador, $e): void {
        $carrera = DB::table('careers')->insertGetId(['department_id' => $e['dcnm'], 'code' => 'LMA', 'name' => 'Licenciatura en Matemática', 'created_at' => now(), 'updated_at' => now()]);
        $plan = DB::table('study_plans')->insertGetId(['career_id' => $carrera, 'name' => 'Plan 25', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('career_subjects')->insert(['study_plan_id' => $plan, 'subject_id' => $e['mat'], 'cycle' => 1]);
        DB::table('career_coordinators')->insert(['user_id' => $coordinador->id, 'career_id' => $carrera, 'valid_from' => '2025-01-01', 'created_at' => now(), 'updated_at' => now()]);
    });

    return $coordinador;
}

/**
 * Sesión de asistencia de la sección del escenario, insertada tal como la deja el docente.
 *
 * @param  array<string, mixed>  $datos
 */
function rf39Sesion(array $e, array $datos = []): string
{
    return DbContext::system(function () use ($e, $datos): string {
        $ulid = (string) str()->ulid();
        DB::table('attendance_sessions')->insert($datos + [
            'ulid' => $ulid, 'section_id' => $e['seccion'], 'session_date' => '2026-10-19', 'modality' => 'in_person', 'status' => 'closed',
            'opened_at' => Carbon::parse('2026-10-19 07:05'), 'closed_at' => Carbon::parse('2026-10-19 07:20'), 'qr_validity_minutes' => 15,
            'opened_by' => $e['docente']->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $ulid;
    });
}

/**
 * Registro de asistencia de una matrícula en la sesión.
 *
 * @param  array<string, mixed>  $datos
 */
function rf39Registro(array $e, string $sesion, int $matricula, array $datos = []): void
{
    DbContext::system(fn () => DB::table('attendance_records')->insert($datos + [
        'session_id' => DB::table('attendance_sessions')->where('ulid', $sesion)->value('id'), 'section_id' => $e['seccion'], 'enrollment_id' => $matricula,
        'status' => 'present', 'method' => 'qr', 'recorded_at' => Carbon::parse('2026-10-19 07:08'), 'created_at' => now(), 'updated_at' => now(),
    ]));
}

/** Un segundo estudiante matriculado en la sección del escenario; devuelve su matrícula. */
function rf39OtroMatriculado(array $e, string $nombre, string $apellido): int
{
    $estudiante = usuarioCon('estudiante');

    return DbContext::system(function () use ($e, $estudiante, $nombre, $apellido): int {
        $estudiante->forceFill(['first_name' => $nombre, 'last_name' => $apellido])->save();

        return DB::table('enrollments')->insertGetId(['student_id' => $estudiante->id, 'section_id' => $e['seccion'], 'period_id' => $e['periodo'], 'subject_id' => $e['prn'],
            'status' => 'active', 'enrolled_via' => 'key', 'enrolled_by' => $estudiante->id, 'created_at' => now(), 'updated_at' => now()]);
    });
}

function rf39UrlSesion(array $e, string $sesion, ?string $seccion = null): string
{
    return route('secciones.asistencia.sesion', ['seccion' => $seccion ?? $e['ulid'], 'sesion' => $sesion]);
}

it('CP-RF39-01 el Jefe ve en su departamento las pestañas de supervisión y todas abren en lectura', function () {
    $e = $this->e;
    $pestanas = collect(Vistas::pestanas($e['jefe'], $e['ulid'], 'secciones.contenidos'))->pluck('nombre')->all();

    expect($pestanas)->toContain('secciones.contenidos', 'secciones.actividades', 'secciones.notas', 'secciones.revisiones', 'secciones.asistencia',
        'secciones.progreso', 'secciones.analitica', 'secciones.foro', 'secciones.estudiantes', 'secciones.participantes');

    foreach (['secciones.contenidos', 'secciones.actividades', 'secciones.notas', 'secciones.revisiones', 'secciones.asistencia', 'secciones.progreso', 'secciones.analitica', 'secciones.foro'] as $ruta) {
        actingAs($e['jefe'])->get(route($ruta, ['seccion' => $e['ulid']]))->assertOk();
    }

    actingAs($e['jefe'])->get(route('secciones.contenidos', ['seccion' => $e['ulid']]))
        ->assertSeeTextInOrder(['Contenidos', 'Actividades', 'Notas', 'Revisiones', 'Asistencia', 'Foro', 'Estudiantes', 'Participantes', 'Progreso', 'Analítica']);
});

it('CP-RF39-02 el Jefe de otro departamento recibe 403 en las pestañas de la sección', function () {
    $e = $this->e;
    $ajeno = rf39JefeAjeno($e);
    $sesion = rf39Sesion($e);

    expect(Vistas::pestanas($ajeno, $e['ulid'], 'secciones.contenidos'))->toBe([]);
    foreach (['secciones.contenidos', 'secciones.notas', 'secciones.asistencia', 'secciones.progreso', 'secciones.analitica', 'secciones.foro'] as $ruta) {
        actingAs($ajeno)->get(route($ruta, ['seccion' => $e['ulid']]))->assertForbidden();
    }
    actingAs($ajeno)->get(rf39UrlSesion($e, $sesion))->assertForbidden();
    // Y el Jefe del escenario, a la inversa, no entra a la sección de DCNM.
    actingAs($e['jefe'])->get(route('secciones.notas', ['seccion' => $e['otraUlid']]))->assertForbidden();
});

it('CP-RF39-03 el Coordinador ve la pestaña Foro de su carrera y la abre en solo lectura', function () {
    $e = $this->e;
    $pestanas = collect(Vistas::pestanas($e['coordinador'], $e['ulid'], 'secciones.contenidos'))->pluck('nombre')->all();

    expect($pestanas)->toContain('secciones.contenidos', 'secciones.actividades', 'secciones.notas', 'secciones.revisiones', 'secciones.asistencia',
        'secciones.progreso', 'secciones.analitica', 'secciones.foro');

    actingAs($e['coordinador'])->get(route('secciones.foro', ['seccion' => $e['ulid']]))
        ->assertOk()->assertSeeText('Foro del grupo teórico')->assertDontSeeText('Nuevo hilo');
});

it('CP-RF39-04 el Coordinador de otra carrera recibe 403 en la sección', function () {
    $e = $this->e;
    $ajeno = rf39CoordinadorAjeno($e);
    $sesion = rf39Sesion($e);

    expect(Vistas::pestanas($ajeno, $e['ulid'], 'secciones.contenidos'))->toBe([]);
    foreach (['secciones.contenidos', 'secciones.notas', 'secciones.asistencia', 'secciones.foro'] as $ruta) {
        actingAs($ajeno)->get(route($ruta, ['seccion' => $e['ulid']]))->assertForbidden();
    }
    actingAs($ajeno)->get(rf39UrlSesion($e, $sesion))->assertForbidden();
    // En la sección de su carrera sí entra: el 403 es por alcance, no por falta de permiso.
    actingAs($ajeno)->get(route('secciones.foro', ['seccion' => $e['otraUlid']]))->assertOk();
});

it('CP-RF39-05 el Coordinador ve fecha, modalidad, estado, apertura, cierre y si fue dentro del horario', function () {
    $e = $this->e;
    $dentro = rf39Sesion($e);
    $fuera = rf39Sesion($e, ['session_date' => '2026-10-24', 'opened_at' => Carbon::parse('2026-10-24 10:00'), 'closed_at' => Carbon::parse('2026-10-24 10:15')]);

    actingAs($e['coordinador'])->get(rf39UrlSesion($e, $dentro))->assertOk()
        ->assertSeeText(['Sesión del 19/10/2026', 'Presencial', 'Cerrada', 'Apertura', '19/10/2026 07:05', 'Cierre', '19/10/2026 07:20', 'Dentro del horario'])
        ->assertDontSeeText('Fuera de horario');

    actingAs($e['coordinador'])->get(rf39UrlSesion($e, $fuera))->assertOk()
        ->assertSeeText(['Sesión del 24/10/2026', '24/10/2026 10:00', '24/10/2026 10:15', 'Fuera de horario']);
});

it('CP-RF39-06 el Jefe ve la misma información del detalle en su departamento', function () {
    $e = $this->e;
    $dentro = rf39Sesion($e);
    $fuera = rf39Sesion($e, ['session_date' => '2026-10-24', 'opened_at' => Carbon::parse('2026-10-24 10:00'), 'closed_at' => Carbon::parse('2026-10-24 10:15')]);

    actingAs($e['jefe'])->get(rf39UrlSesion($e, $dentro))->assertOk()
        ->assertSeeText(['Sesión del 19/10/2026', 'Presencial', 'Cerrada', '19/10/2026 07:05', '19/10/2026 07:20', 'Dentro del horario']);
    actingAs($e['jefe'])->get(rf39UrlSesion($e, $fuera))->assertOk()->assertSeeText('Fuera de horario');
});

it('CP-RF39-07 el Coordinador y el Jefe ven a cada estudiante con estado, entrada, salida y método', function (string $rol) {
    $e = $this->e;
    $sesion = rf39Sesion($e, ['exit_opened_at' => Carbon::parse('2026-10-19 08:30')]);
    DbContext::system(fn () => $e['estudiante']->forceFill(['first_name' => 'Ana', 'last_name' => 'Gómez'])->save());
    rf39Registro($e, $sesion, $e['matricula'], ['exit_recorded_at' => Carbon::parse('2026-10-19 08:35')]);
    $tarde = rf39OtroMatriculado($e, 'Mario', 'Rivas');
    rf39Registro($e, $sesion, $tarde, ['status' => 'late', 'method' => 'manual', 'recorded_at' => Carbon::parse('2026-10-19 07:31')]);

    actingAs($e[$rol])->get(rf39UrlSesion($e, $sesion))->assertOk()
        ->assertSeeTextInOrder(['Estudiante', 'Estado', 'Entrada', 'Método', 'Salida'])
        ->assertSeeTextInOrder(['Gómez, Ana', 'Presente', '07:08', 'QR', '08:35'])
        ->assertSeeTextInOrder(['Rivas, Mario', 'Tarde', '07:31', 'Manual']);
})->with(['coordinador', 'jefe']);

it('CP-RF39-08 en una sesión virtual el Coordinador y el Jefe ven el porcentaje de Meet', function (string $rol) {
    $e = $this->e;
    $sesion = rf39Sesion($e, ['modality' => 'virtual']);
    rf39Registro($e, $sesion, $e['matricula'], ['method' => 'meet', 'meet_percent' => 85]);
    $poco = rf39OtroMatriculado($e, 'Mario', 'Rivas');
    rf39Registro($e, $sesion, $poco, ['status' => 'absent', 'method' => 'meet', 'meet_percent' => 42.5]);

    actingAs($e[$rol])->get(rf39UrlSesion($e, $sesion))->assertOk()
        ->assertSeeText(['Virtual', '85.0 %', '42.5 %'])->assertSee('<th>Meet</th>', false);

    // En presencial no hay columna Meet.
    $presencial = rf39Sesion($e, ['session_date' => '2026-10-12', 'opened_at' => Carbon::parse('2026-10-12 07:05'), 'closed_at' => Carbon::parse('2026-10-12 07:20')]);
    actingAs($e[$rol])->get(rf39UrlSesion($e, $presencial))->assertOk()->assertDontSee('<th>Meet</th>', false);
})->with(['coordinador', 'jefe']);

it('CP-RF39-09 el Coordinador y el Jefe no ven los botones de operar la sesión; el docente sí', function (string $rol) {
    $e = $this->e;
    $sesion = rf39Sesion($e, ['status' => 'open', 'opened_at' => now()->subMinutes(2), 'closed_at' => null, 'session_date' => now()->toDateString()]);
    $marcado = rf39OtroMatriculado($e, 'Mario', 'Rivas');
    rf39Registro($e, $sesion, $marcado, ['recorded_at' => now()->subMinute()]);
    $acciones = ['Proyectar QR', 'Cerrar asistencia', 'Abrir marca de salida', 'Solicitar cancelación', 'Corregir', 'Acciones'];
    $botones = ['wire:click="marcarRapido', 'wire:click="generarCodigo', 'wire:click="abrirCorreccion', 'wire:click="cerrar"'];

    $pagina = actingAs($e[$rol])->get(rf39UrlSesion($e, $sesion))->assertOk()->assertSeeText('Abierta')->assertDontSeeText($acciones);
    foreach ($botones as $boton) {
        $pagina->assertDontSee($boton, false);
    }
    // Ver la sesión vencida no la cierra: solo el docente la cierra.
    $this->travel(20)->minutes();
    actingAs($e[$rol])->get(rf39UrlSesion($e, $sesion))->assertOk();
    expect(DB::table('attendance_sessions')->where('ulid', $sesion)->value('status'))->toBe('open');

    // Y llamar a la acción directamente lo rechaza el Service.
    actingAs($e[$rol]);
    Livewire::test(SesionAsistencia::class, ['seccion' => $e['ulid'], 'sesion' => $sesion])->call('cerrar')->assertForbidden();
    Livewire::test(SesionAsistencia::class, ['seccion' => $e['ulid'], 'sesion' => $sesion])->call('marcarRapido', $e['matricula'], 'present')->assertForbidden();
    expect(DB::table('attendance_records')->where('enrollment_id', $e['matricula'])->exists())->toBeFalse();
})->with(['coordinador', 'jefe']);

it('CP-RF39-09 el docente responsable conserva los botones de operar la sesión', function () {
    $e = $this->e;
    $sesion = rf39Sesion($e, ['status' => 'open', 'opened_at' => now()->subMinutes(2), 'closed_at' => null, 'session_date' => now()->toDateString()]);
    $marcado = rf39OtroMatriculado($e, 'Mario', 'Rivas');
    rf39Registro($e, $sesion, $marcado, ['recorded_at' => now()->subMinute()]);

    actingAs($e['docente'])->get(rf39UrlSesion($e, $sesion))->assertOk()
        ->assertSeeText(['Proyectar QR', 'Cerrar asistencia', 'Abrir marca de salida', 'Solicitar cancelación', 'Corregir'])
        ->assertSee('wire:click="marcarRapido', false);
});

it('CP-RF39-10 sin asistencia.sesion.ver ni asistencia.sesion.abrir el detalle da 403', function () {
    $e = $this->e;
    $sesion = rf39Sesion($e);

    actingAs($e['estudiante'])->get(rf39UrlSesion($e, $sesion))->assertForbidden();
    actingAs($e['administrador'])->get(rf39UrlSesion($e, $sesion))->assertForbidden();
    actingAs($e['estudiante']);
    expect(fn () => app(AsistenciaService::class)->sesion($e['estudiante'], $e['seccion'], $sesion))->toThrow(AuthorizationException::class);
    // Con el permiso de lectura (Coordinador) sí entra.
    actingAs($e['coordinador'])->get(rf39UrlSesion($e, $sesion))->assertOk();
});

it('CP-RF39-11 el Coordinador ve hilos y respuestas del foro, sin formularios para crear ni responder', function () {
    $e = $this->e;
    $foro = app(ForoService::class);
    actingAs($e['estudiante']);
    $hilo = $foro->crearHilo($e['estudiante'], $e['seccion'], 'Duda de recursividad', '¿Cómo se detiene la recursión?');
    actingAs($e['docente']);
    $foro->responder($e['docente'], $e['seccion'], $hilo, 'Con el caso base.');

    actingAs($e['coordinador'])->get(route('secciones.foro', ['seccion' => $e['ulid']]))->assertOk()
        ->assertSeeText('Duda de recursividad')->assertDontSeeText('Nuevo hilo')->assertDontSee('wire:submit="crear"', false);
    actingAs($e['coordinador'])->get(route('secciones.foro.hilo', ['seccion' => $e['ulid'], 'hilo' => $hilo]))->assertOk()
        ->assertSeeText(['¿Cómo se detiene la recursión?', 'Con el caso base.'])
        ->assertDontSeeText(['Responder al hilo', 'Responder', 'Ocultar', 'Fijar', 'Cerrar hilo'])
        ->assertDontSee('wire:submit="responder"', false);

    // Llamar a crear o responder directamente lo rechaza el Service (exige foros.participar).
    actingAs($e['coordinador']);
    expect(fn () => $foro->crearHilo($e['coordinador'], $e['seccion'], 'Intruso', 'Mensaje'))->toThrow(AuthorizationException::class)
        ->and(fn () => $foro->responder($e['coordinador'], $e['seccion'], $hilo, 'Respuesta'))->toThrow(AuthorizationException::class);
    Livewire::test(Foro::class, ['seccion' => $e['ulid']])->set('titulo', 'Intruso')->set('mensaje', 'Mensaje')->call('crear')->assertForbidden();
    Livewire::test(HiloForo::class, ['seccion' => $e['ulid'], 'hilo' => $hilo])->set('mensaje', 'Respuesta')->call('responder')->assertForbidden();
    expect(DB::table('forum_threads')->count())->toBe(1)->and(DB::table('forum_posts')->count())->toBe(2);

    // El docente, que participa, sí ve los formularios.
    actingAs($e['docente'])->get(route('secciones.foro', ['seccion' => $e['ulid']]))->assertOk()->assertSeeText('Nuevo hilo');
});

it('CP-RF39-12 sin foros.ver ni foros.participar en la sección el foro da 403', function () {
    $e = $this->e;
    $sinMatricula = usuarioCon('estudiante');

    actingAs($e['administrador'])->get(route('secciones.foro', ['seccion' => $e['ulid']]))->assertForbidden();
    actingAs($e['soporte'])->get(route('secciones.foro', ['seccion' => $e['ulid']]))->assertForbidden();
    actingAs($sinMatricula)->get(route('secciones.foro', ['seccion' => $e['ulid']]))->assertForbidden();
    actingAs($e['estudiante'])->get(route('secciones.foro', ['seccion' => $e['otraUlid']]))->assertForbidden();
    // El estudiante matriculado sí participa.
    actingAs($e['estudiante'])->get(route('secciones.foro', ['seccion' => $e['ulid']]))->assertOk()->assertSeeText('Nuevo hilo');
});

it('CP-RF39-13 el Jefe ve las Notas de su departamento en solo lectura', function () {
    $e = $this->e;

    actingAs($e['jefe'])->get(route('secciones.notas', ['seccion' => $e['ulid']]))->assertOk()
        ->assertSeeText('Libro de calificaciones')
        ->assertDontSeeText(['Pesos', 'Mensajes enviados', 'Escribir a estudiantes según su entrega o nota', 'Corregir nota (período cerrado)'])
        ->assertDontSee('href="'.route('secciones.acta', ['seccion' => $e['ulid']]).'"', false)
        ->assertDontSee('wire:click="publicar', false)
        ->assertDontSee('wire:click="abrirCorreccion', false);
    // El docente, que gestiona, sí tiene Pesos y Acta.
    actingAs($e['docente'])->get(route('secciones.notas', ['seccion' => $e['ulid']]))->assertOk()->assertSeeText(['Pesos', 'Acta']);
    // Fuera de su departamento, 403.
    actingAs($e['jefe'])->get(route('secciones.notas', ['seccion' => $e['otraUlid']]))->assertForbidden();
});

it('CP-RF39-14 el Coordinador y el Jefe ven el motivo auditado de abrir fuera del horario', function (string $rol) {
    $e = $this->e;
    $this->travelTo(Carbon::parse('2026-10-24 10:00'));
    actingAs($e['docente']);
    $sesion = app(AsistenciaService::class)->abrir($e['docente'], $e['seccion'], 10, 'in_person', 'Clase adelantada');
    app(AsistenciaService::class)->cerrar($e['docente'], $e['seccion'], $sesion);

    actingAs($e[$rol])->get(rf39UrlSesion($e, $sesion))->assertOk()
        ->assertSeeText(['Fuera de horario', 'Motivo:', 'Clase adelantada']);
})->with(['coordinador', 'jefe'])
    ->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'El motivo vive en audit_logs, que solo llenan los triggers de PostgreSQL.');
