<?php

use App\Modules\Academica\Services\PeriodoService;
use App\Modules\Calificaciones\Services\CalificacionService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(fn () => Notification::fake());

/**
 * CP-RF17-11 · Las notas llegan al cierre del período por el acta de la sección (RF17 → RF34), de punta a punta
 * con los Services reales: el docente califica y publica, publica el acta, el Coordinador la valida, el Jefe
 * autoriza su departamento y el Administrador cierra el período.
 */

/**
 * Sección PRN115-01 calificada por el docente con el servicio: dos cómputos (40/60) cuyas actividades suman 100
 * en cada uno, tres matrículas activas y todas las actividades publicadas. Saca del período la sección de MAT115
 * (DCNM) para que el único departamento por autorizar sea el del Jefe del escenario.
 *
 * @param  array<string, mixed>  $e
 * @return array<string, int> id de la matrícula de cada caso
 */
function flujoSeccionCalificada(array $e): array
{
    // Los estudiantes se crean antes: usuarioCon() abre su propio DbContext::system() y, anidado, limpiaría el
    // contexto del externo en PostgreSQL.
    $estudiantes = ['aprobado por redondeo' => usuarioCon('estudiante'), 'reprobado con nota faltante' => usuarioCon('estudiante')];

    [$actividades, $matriculas] = DbContext::system(function () use ($e, $estudiantes) {
        DB::table('sections')->where('id', $e['otra'])->delete();

        $actividades = [];
        foreach ([[1, 40, [50, 50]], [2, 60, [100]]] as [$numero, $peso, $pesosActividades]) {
            $computo = DB::table('grading_components')->insertGetId([
                'section_id' => $e['seccion'], 'number' => $numero, 'name' => "Cómputo {$numero}", 'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11',
                'weight_percent' => $peso, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($pesosActividades as $i => $pesoActividad) {
                $actividades[] = DB::table('grade_items')->insertGetId([
                    'component_id' => $computo, 'section_id' => $e['seccion'], 'item_type' => 'manual', 'title' => "Actividad {$numero}.".($i + 1),
                    'weight_percent' => $pesoActividad, 'is_published' => false, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        $matriculas = ['aprobado' => $e['matricula']];
        foreach ($estudiantes as $caso => $estudiante) {
            $matriculas[$caso] = DB::table('enrollments')->insertGetId([
                'student_id' => $estudiante->id, 'section_id' => $e['seccion'], 'period_id' => $e['periodo'], 'subject_id' => $e['prn'],
                'status' => 'active', 'enrolled_via' => 'admin', 'enrolled_by' => $e['administrador']->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return [$actividades, $matriculas];
    });

    // Notas por actividad (1.1, 1.2, 2.1); null deja la actividad sin nota.
    $notas = [
        'aprobado' => [9, 8, 7],
        'aprobado por redondeo' => [5, 6, 6.25],
        'reprobado con nota faltante' => [10, null, 4],
    ];

    actingAs($e['docente']);
    $calificaciones = app(CalificacionService::class);
    foreach ($notas as $caso => $deLaMatricula) {
        foreach ($deLaMatricula as $i => $nota) {
            if ($nota !== null) {
                $calificaciones->calificar($e['docente'], $actividades[$i], $matriculas[$caso], $nota);
            }
        }
    }
    foreach ($actividades as $actividad) {
        $calificaciones->publicar($e['docente'], $actividad);
    }

    return $matriculas;
}

/**
 * Ejecuta la acción como el actor y devuelve los errores de validación (vacío si no hubo).
 *
 * @return array<string, array<int, string>>
 */
function flujoErrores(object $actor, Closure $accion): array
{
    actingAs($actor);
    try {
        $accion();
    } catch (ValidationException $excepcion) {
        return $excepcion->errors();
    }

    return [];
}

it('CP-RF17-11 las notas publicadas pasan por el acta validada y autorizada y se congelan al cerrar el período', function () {
    $e = escenario();
    $matriculas = flujoSeccionCalificada($e);
    $calificaciones = app(CalificacionService::class);

    actingAs($e['docente']);
    $calificaciones->publicarActa($e['docente'], $e['seccion']);

    actingAs($e['coordinador']);
    $calificaciones->validarActa($e['coordinador'], $e['seccion']);
    $acta = $calificaciones->acta($e['coordinador'], $e['seccion']);

    actingAs($e['jefe']);
    $calificaciones->autorizarCierre($e['jefe']);

    actingAs($e['administrador']);
    $congeladas = app(PeriodoService::class)->cerrar($e['administrador'], $e['periodo']);

    expect($congeladas)->toBe(3)
        ->and(DB::table('academic_periods')->where('id', $e['periodo'])->value('status'))->toBe('closed')
        ->and(DB::table('grade_sheets')->where('section_id', $e['seccion'])->value('status'))->toBe('validated')
        ->and(DB::table('department_closures')->where('period_id', $e['periodo'])->get(['department_id', 'authorized_by'])->map(fn ($c) => [(int) $c->department_id, (int) $c->authorized_by])->all())
        ->toBe([[$e['dia'], $e['jefe']->id]]);

    // Cómputo 1 (40 %) = promedio 50/50 de 1.1 y 1.2; cómputo 2 (60 %) = 2.1. Faltante = 0; 1 decimal, mitad hacia arriba.
    $esperado = [
        'aprobado' => [7.6, 'passed'],                       // 0.4 × 8.5 + 0.6 × 7 = 7.6
        'aprobado por redondeo' => [6.0, 'passed'],          // 0.4 × 5.5 + 0.6 × 6.25 = 5.95 → 6.0
        'reprobado con nota faltante' => [4.4, 'failed'],    // 0.4 × 5 + 0.6 × 4 = 4.4
    ];
    $finalesDelActa = $acta['filas']->mapWithKeys(fn ($f) => [(int) $f->id => (float) $f->final]);

    foreach ($esperado as $caso => [$final, $resultado]) {
        $fila = DB::table('enrollment_results')->where('enrollment_id', $matriculas[$caso])->first();
        expect((float) $fila->final_score)->toEqual($final, $caso)
            ->and($fila->result)->toBe($resultado, $caso)
            ->and($finalesDelActa[$matriculas[$caso]])->toEqual($final, "{$caso}: la nota del acta validada");
    }
});

it('CP-RF17-11 sin el acta validada el Jefe no autoriza el departamento y el período no se cierra', function (bool $publicar, bool $devolver) {
    $e = escenario();
    flujoSeccionCalificada($e);
    $calificaciones = app(CalificacionService::class);
    if ($publicar) {
        actingAs($e['docente']);
        $calificaciones->publicarActa($e['docente'], $e['seccion']);
    }
    if ($devolver) {
        actingAs($e['coordinador']);
        $calificaciones->devolverActa($e['coordinador'], $e['seccion'], 'Revise el cómputo 2.');
    }

    $delJefe = flujoErrores($e['jefe'], fn () => $calificaciones->autorizarCierre($e['jefe']));
    $delAdministrador = flujoErrores($e['administrador'], fn () => app(PeriodoService::class)->cerrar($e['administrador'], $e['periodo']));

    expect($delJefe['cierre'][0] ?? null)->toContain('PRN115-01')
        ->and(DB::table('department_closures')->count())->toBe(0)
        ->and($delAdministrador['cierre'][0] ?? null)->toContain('Ingeniería y Arquitectura')
        ->and(DB::table('academic_periods')->where('id', $e['periodo'])->value('status'))->toBe('active')
        ->and(DB::table('enrollment_results')->count())->toBe(0);
})->with([
    'sin acta' => [false, false],
    'publicada sin validar' => [true, false],
    'devuelta por el Coordinador' => [true, true],
]);
