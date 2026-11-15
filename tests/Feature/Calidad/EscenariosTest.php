<?php

use App\Modules\Identidad\Services\EscenarioService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * El entorno que necesitan los escenarios: el `escenario()` global más las asignaturas que copian los
 * constructores, las plantillas de cómputos del período y el área de asistencia del campus.
 *
 * @return array<string, mixed>
 */
function baseDeEscenarios(): array
{
    $e = escenario();

    return DbContext::system(function () use ($e) {
        $ahora = now();
        $t = fn (array $fila) => $fila + ['created_at' => $ahora, 'updated_at' => $ahora];

        // Las materias que copian los constructores (PRN115 y MAT115 ya vienen del helper global).
        foreach ([
            'BAD115' => ['Bases de Datos I', $e['dia']],
            'ESD115' => ['Estructuras de Datos', $e['dia']],
            'RED115' => ['Redes de Computadoras', $e['dia']],
            'DPW115' => ['Desarrollo Web', $e['dia']],
            'DSI115' => ['Diseño de Sistemas I', $e['dia']],
            'ISI115' => ['Introducción a la Informática', $e['dia']],
            'IDI115' => ['Idioma Inglés I', $e['dia']],
            'PSI115' => ['Psicología Social', $e['dia']],
        ] as $codigo => [$nombre, $departamento]) {
            $id = DB::table('subjects')->insertGetId($t(['code' => $codigo, 'name' => $nombre, 'credits' => 4, 'department_id' => $departamento]));
            DB::table('career_subjects')->insert(['study_plan_id' => $e['plan'], 'subject_id' => $id, 'cycle' => 1]);
        }

        // Los tres cómputos del período (30 %, 30 %, 40 %).
        foreach ([[1, 30, '2026-07-27', '2026-09-06'], [2, 30, '2026-09-07', '2026-10-18'], [3, 40, '2026-10-19', '2026-12-11']] as [$numero, $peso, $desde, $hasta]) {
            DB::table('grading_component_templates')->insert($t([
                'period_id' => $e['periodo'], 'number' => $numero, 'name' => "Cómputo {$numero}",
                'starts_on' => $desde, 'ends_on' => $hasta, 'weight_percent' => $peso,
            ]));
        }

        DB::table('attendance_areas')->insert($t([
            'name' => 'Campus UES FMO', 'area_type' => 'circle', 'center_lat' => 13.4393, 'center_lng' => -88.1597, 'radius_m' => 450, 'is_active' => true,
        ]));

        return $e;
    });
}

function motor(): EscenarioService
{
    config()->set('lms.dev_login', true);

    return app(EscenarioService::class);
}

/**
 * Un reparto que satisface los mínimos de un flujo, con las personas del `escenario()` global.
 *
 * @param  array<string, mixed>  $e
 * @return array<string, array<int, int>>
 */
function repartoPara(string $flujo, array $e): array
{
    $porRol = [
        'administrador' => [$e['administrador']->id],
        'jefe_departamento' => [$e['jefe']->id],
        'coordinador' => [$e['coordinador']->id],
        'docente' => [$e['docente']->id],
        'estudiante' => [$e['estudiante']->id],
        'soporte_tecnico' => [$e['soporte']->id],
    ];

    $reparto = [];
    foreach (motor()->flujo($flujo)['roles'] as $rol => $regla) {
        if ($regla['minimo'] > 0) {
            $reparto[$rol] = array_slice($porRol[$rol], 0, max(1, $regla['minimo']));
        }
    }

    // Asistencia virtual pide dos estudiantes: el segundo se crea aparte.
    if (($reparto['estudiante'] ?? null) !== null && motor()->flujo($flujo)['roles']['estudiante']['minimo'] > 1) {
        $reparto['estudiante'][] = usuarioCon('estudiante')->id;
    }

    return $reparto;
}

it('prepara cada flujo del catálogo con enlaces, checklist y actores', function () {
    $e = baseDeEscenarios();

    foreach (array_keys(motor()->catalogo()) as $flujo) {
        $escenario = motor()->preparar($flujo, repartoPara($flujo, $e), relleno: 3);

        expect($escenario->code)->toMatch('/^E[A-Z2-9]{4}$/');

        $detalle = motor()->detalle(motor()->porCodigo($escenario->code));

        expect($detalle['enlaces'])->not->toBeEmpty("El flujo {$flujo} no dejó enlaces");
        expect($detalle['checklist'])->not->toBeEmpty("El flujo {$flujo} no dejó checklist");
        expect($detalle['actores'])->not->toBeEmpty("El flujo {$flujo} no dejó actores");
        expect($detalle['resumen'])->not->toBeEmpty("El flujo {$flujo} no dejó resumen");

        // Todo enlace resuelve a una URL de verdad: ninguna vista quedó mal nombrada.
        foreach ($detalle['enlaces'] as $rol => $enlaces) {
            foreach ($enlaces as $enlace) {
                expect($enlace['url'])->not->toBeNull("El flujo {$flujo} tiene un enlace sin URL para {$rol}");
            }
        }
    }
});

it('la checklist sale de los casos de prueba del RF que cubre el flujo', function () {
    $e = baseDeEscenarios();
    $escenario = motor()->preparar('cuestionario', repartoPara('cuestionario', $e), relleno: 0);

    $casos = DB::table('test_scenario_checks')->where('scenario_id', $escenario->id)->pluck('case_id');

    expect($casos)->not->toBeEmpty()
        ->and($casos->every(fn (string $id) => str_starts_with($id, 'CP-RF16-')))->toBeTrue();
});

it('es aditivo: no toca ni borra nada de lo que ya estaba', function () {
    $e = baseDeEscenarios();
    $antes = [
        'secciones' => DB::table('sections')->pluck('id')->all(),
        'matriculas' => DB::table('enrollments')->pluck('id')->all(),
        'usuarios' => DB::table('users')->count(),
    ];

    motor()->preparar('asistencia-qr', repartoPara('asistencia-qr', $e), relleno: 4);

    // Lo de antes sigue ahí, intacto.
    expect(DB::table('sections')->whereIn('id', $antes['secciones'])->count())->toBe(count($antes['secciones']))
        ->and(DB::table('enrollments')->whereIn('id', $antes['matriculas'])->count())->toBe(count($antes['matriculas']))
        // Y se agregó: 4 de relleno.
        ->and(DB::table('users')->count())->toBe($antes['usuarios'] + 4);
});

it('se puede preparar dos veces el mismo flujo con las mismas personas', function () {
    $e = baseDeEscenarios();
    $reparto = repartoPara('asistencia-qr', $e);

    $primero = motor()->preparar('asistencia-qr', $reparto, relleno: 2);
    $segundo = motor()->preparar('asistencia-qr', $reparto, relleno: 2);

    expect($primero->code)->not->toBe($segundo->code);

    // Dos materias y dos secciones distintas: es lo que evita el choque de matrícula única.
    $materias = DB::table('subjects')->where('code', 'like', 'PRN115-%')->count();
    expect($materias)->toBe(2);

    // El docente quedó asignado en las dos y el estudiante matriculado en las dos.
    expect(DB::table('teaching_assignments')->where('teacher_id', $e['docente']->id)->count())->toBe(3) // la del demo + 2
        ->and(DB::table('enrollments')->where('student_id', $e['estudiante']->id)->where('status', 'active')->count())->toBe(3);
});

it('limpiar retira lo que ADR-027 no deja borrar, borra el resto y deja lo demás en pie', function () {
    $e = baseDeEscenarios();
    $antesUsuarios = DB::table('users')->count();
    $antesSecciones = DB::table('sections')->count();

    $escenario = motor()->preparar('entrega-de-tarea', repartoPara('entrega-de-tarea', $e), relleno: 3);

    expect(DB::table('sections')->count())->toBe($antesSecciones + 1)
        ->and(DB::table('users')->count())->toBe($antesUsuarios + 3);

    $seccion = (int) DB::table('sections')->orderByDesc('id')->value('id');
    $cuenta = motor()->limpiar($escenario->id);

    expect($cuenta['borradas'])->toBeGreaterThan(0)
        ->and($cuenta['retiradas'])->toBeGreaterThan(0)
        // Las matrículas no se borran (AC902): quedan retiradas.
        ->and(DB::table('enrollments')->where('section_id', $seccion)->pluck('status')->unique()->all())->toBe(['withdrawn'])
        // Las cuentas de relleno tampoco: siguen ahí, desactivadas.
        ->and(DB::table('users')->count())->toBe($antesUsuarios + 3)
        ->and(DB::table('users')->where('is_active', false)->count())->toBe(3)
        ->and(DB::table('test_scenarios')->where('id', $escenario->id)->value('status'))->toBe('cleaned')
        ->and(DB::table('test_scenario_objects')->where('scenario_id', $escenario->id)->count())->toBe(0);

    // La persona del equipo sobrevive activa y con su perfil: otro escenario puede estar usándolo.
    expect(DB::table('users')->where('id', $e['docente']->id)->exists())->toBeTrue()
        ->and((bool) DB::table('users')->where('id', $e['docente']->id)->value('is_active'))->toBeTrue()
        ->and(DB::table('teacher_profiles')->where('user_id', $e['docente']->id)->exists())->toBeTrue();
});

it('exige el reparto que el flujo declara', function () {
    baseDeEscenarios();

    expect(fn () => motor()->preparar('asistencia-qr', []))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => motor()->preparar('cuestionario', ['docente' => [1], 'estudiante' => range(2, 20)]))
        ->toThrow(InvalidArgumentException::class);
});

it('no funciona sin LMS_DEV_LOGIN', function () {
    config()->set('lms.dev_login', false);

    expect(fn () => app(EscenarioService::class)->preparar('asistencia-qr', []))->toThrow(RuntimeException::class);
    expect(app(EscenarioService::class)->habilitado())->toBeFalse();
});

it('crea las cuentas de los testers del equipo una sola vez', function () {
    baseDeEscenarios();

    $primera = motor()->testers();
    $segunda = motor()->testers();

    expect($primera)->toHaveCount(20)
        ->and($primera->pluck('id')->all())->toBe($segunda->pluck('id')->all())
        ->and($primera->pluck('correo')->every(fn (string $c) => str_ends_with($c, '@ues.edu.sv')))->toBeTrue()
        ->and(DB::table('users')->where('institutional_email', 'vc21033@ues.edu.sv')->count())->toBe(1);
});

it('siembra las cuentas de los testers con actor del sistema, sin chocar con el trigger de auditoría', function () {
    baseDeEscenarios();

    // Sin `DbContext`, este insert en `users` muere con AC900: «Escritura en users sin actor».
    $testers = motor()->testers();

    expect($testers)->toHaveCount(20);

    $registro = DB::table('audit_logs')
        ->where('entity_type', 'users')
        ->where('action', 'insert')
        ->orderByDesc('id')
        ->first();

    expect($registro)->not->toBeNull()
        ->and($registro->event_type)->toBe('escenario.testers')
        ->and($registro->actor_user_id)->toBeNull();
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'La auditoría por triggers solo existe en PostgreSQL.');
