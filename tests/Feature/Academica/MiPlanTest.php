<?php

use App\Modules\Academica\Livewire\MiPlan;
use App\Modules\Academica\Services\PlanEstudianteService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
| Mi plan (RF37). El escenario tiene PRN115 (ciclo 1 del Plan 25 de ISI) en curso en 2026-II. Se agregan al plan:
| CAL115 aprobada en 2026-I con 8.0, FIS115 reprobada en 2025-II con 4.0 y aprobada en 2026-I con 7.0, QUI115
| reprobada con 5.0 y ALG215 sin cursar.
*/

/**
 * @return array<string, int>
 */
function miPlanHistorial(array $e): array
{
    return DbContext::system(function () use ($e) {
        $t = fn (array $fila) => $fila + ['created_at' => now(), 'updated_at' => now()];
        $materia = function (string $codigo, int $ciclo, int $uv) use ($e, $t) {
            $id = DB::table('subjects')->insertGetId($t(['code' => $codigo, 'name' => 'Materia '.$codigo, 'credits' => $uv, 'department_id' => $e['dia']]));
            DB::table('career_subjects')->insert(['study_plan_id' => $e['plan'], 'subject_id' => $id, 'cycle' => $ciclo]);

            return $id;
        };
        $ids = ['MAT' => $materia('CAL115', 1, 4), 'FIS' => $materia('FIS115', 2, 4), 'QUI' => $materia('QUI115', 2, 3), 'ALG' => $materia('ALG215', 3, 4)];
        DB::table('subject_prerequisites')->insert(['subject_id' => $ids['ALG'], 'prerequisite_id' => $ids['MAT']]);

        $cursar = function (string $periodoNombre, string $inicio, array $notas) use ($e, $t) {
            $periodo = DB::table('academic_periods')->insertGetId($t(['name' => $periodoNombre, 'start_date' => $inicio, 'end_date' => date('Y-m-d', strtotime($inicio.' +4 months')), 'status' => 'active']));
            foreach ($notas as [$asignatura, $nota, $resultado]) {
                $seccion = DB::table('sections')->insertGetId($t(['ulid' => (string) str()->ulid(), 'period_id' => $periodo, 'subject_id' => $asignatura, 'code' => '01', 'max_capacity' => 40]));
                $matricula = DB::table('enrollments')->insertGetId($t(['student_id' => $e['estudiante']->id, 'section_id' => $seccion, 'period_id' => $periodo, 'subject_id' => $asignatura,
                    'status' => 'active', 'enrolled_via' => 'key', 'enrolled_by' => $e['estudiante']->id]));
                DB::table('enrollment_results')->insert(['enrollment_id' => $matricula, 'final_score' => $nota, 'result' => $resultado, 'policy_snapshot' => json_encode(['nota_minima' => 6.0]), 'frozen_at' => now()]);
            }
            // Se cierra después de guardar las notas: en PostgreSQL un período cerrado no admite escrituras.
            DB::table('academic_periods')->where('id', $periodo)->update(['status' => 'closed']);
        };
        // Un solo período activo a la vez: el del escenario se pausa mientras se cargan los anteriores.
        DB::table('academic_periods')->where('id', $e['periodo'])->update(['status' => 'planned']);
        $cursar('2025-II', '2025-07-28', [[$ids['FIS'], 4.0, 'failed'], [$ids['QUI'], 5.0, 'failed']]);
        $cursar('2026-I', '2026-02-02', [[$ids['MAT'], 8.0, 'passed'], [$ids['FIS'], 7.0, 'passed']]);
        DB::table('academic_periods')->where('id', $e['periodo'])->update(['status' => 'active']);

        return $ids;
    });
}

it('CP-RF37-01 CP-RF37-02 muestra cada materia en su ciclo con el color y la nota de su estado', function () {
    $e = escenario();
    $ids = miPlanHistorial($e);
    actingAs($e['estudiante']);

    $plan = app(PlanEstudianteService::class)->plan($e['estudiante']);
    $estado = $plan['ciclos']->flatten(1)->keyBy('code');

    expect($plan['ciclos']->keys()->all())->toBe([1, 2, 3])
        ->and($estado['CAL115']->estado)->toBe('aprobada')->and($estado['CAL115']->nota)->toBe(8.0)
        // Reprobada en 2025-II y aprobada en 2026-I: cuenta la aprobada.
        ->and($estado['FIS115']->estado)->toBe('aprobada')->and($estado['FIS115']->nota)->toBe(7.0)
        ->and($estado['QUI115']->estado)->toBe('reprobada')->and($estado['QUI115']->nota)->toBe(5.0)
        ->and($estado['PRN115']->estado)->toBe('en_curso')
        ->and($estado['ALG215']->estado)->toBe('pendiente')->and($estado['ALG215']->codigosPrerrequisitos)->toBe(['CAL115'])
        ->and($estado['CAL115']->dependientes)->toBe([$ids['ALG']]);

    Livewire::test(MiPlan::class)
        ->assertSeeText(['Mi plan', 'Ciclo 1', 'Ciclo 2', 'Ciclo 3', 'Aprobada', 'En curso', 'Reprobada', 'Pendiente', 'Requiere CAL115'])
        ->assertSee('bg-amber-50', false)->assertSee('bg-sky-50', false)->assertSee('bg-red-50', false);
});

it('CP-RF37-04 el resumen cuenta lo aprobado y calcula el CUM ponderado por UV', function () {
    $e = escenario();
    miPlanHistorial($e);
    actingAs($e['estudiante']);

    $resumen = app(PlanEstudianteService::class)->plan($e['estudiante'])['resumen'];

    // CAL115 (8.0 × 4 UV) y FIS115 (7.0 × 4 UV): CUM 7.5 sobre 8 UV.
    expect($resumen['aprobadas'])->toBe(2)->and($resumen['total'])->toBe(5)
        ->and($resumen['uvAprobadas'])->toBe(8)->and($resumen['cum'])->toBe(7.5)->and($resumen['enCurso'])->toBe(1);
});

it('CP-RF37-03 con el selector ve otro plan de su carrera con lo ya aprobado marcado', function () {
    $e = escenario();
    $ids = miPlanHistorial($e);
    $otro = DbContext::system(function () use ($e, $ids) {
        $plan = DB::table('study_plans')->insertGetId(['career_id' => $e['isi'], 'name' => 'Plan 98', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('career_subjects')->insert(['study_plan_id' => $plan, 'subject_id' => $ids['MAT'], 'cycle' => 2]);

        return $plan;
    });
    actingAs($e['estudiante']);

    $plan = app(PlanEstudianteService::class)->plan($e['estudiante'], $otro);
    expect($plan['plan']->name)->toBe('Plan 98')->and($plan['planes'])->toHaveCount(2)
        ->and($plan['ciclos'][2]->first()->estado)->toBe('aprobada');

    Livewire::withQueryParams(['plan' => $otro])->test(MiPlan::class)->assertSeeText('Está viendo el Plan 98, que no es su plan.');
});

it('CP-RF37-05 abre Mi plan solo al estudiante', function (string $rol) {
    $e = escenario();

    actingAs($e['estudiante'])->get(route('mi-plan'))->assertOk();
    actingAs($e[$rol])->get(route('mi-plan'))->assertForbidden();
})->with(['docente', 'coordinador', 'jefe', 'administrador', 'soporte']);
