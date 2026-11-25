<?php

use App\Modules\Academica\Services\PeriodoService;
use App\Modules\Calificaciones\Services\CalificacionService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Correcciones de la revisión de flujos del 21/11/2026 (cierre y notas, RF17, RF34, RF35).
 */
beforeEach(function () {
    $this->e = escenario();
    $this->notas = app(CalificacionService::class);
    $e = $this->e;
    $this->actividad = DbContext::system(function () use ($e) {
        $computo = DB::table('grading_components')->insertGetId(['section_id' => $e['seccion'], 'number' => 1, 'name' => 'Cómputo 1',
            'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11', 'weight_percent' => 100, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('grade_items')->insertGetId(['component_id' => $computo, 'section_id' => $e['seccion'], 'item_type' => 'manual', 'title' => 'Parcial',
            'weight_percent' => 100, 'is_published' => true, 'created_at' => now(), 'updated_at' => now()]);
    });
});

/** Acta de cada grupo del período en el estado dado y, si se pide, la autorización de ambos departamentos. */
function cierreFijarActas(array $e, string $estado, bool $autorizar): void
{
    DbContext::system(function () use ($e, $estado, $autorizar): void {
        foreach ([$e['seccion'], $e['otra']] as $seccion) {
            DB::table('grade_sheets')->updateOrInsert(['section_id' => $seccion], ['status' => $estado, 'created_at' => now(), 'updated_at' => now()]);
        }
        if ($autorizar) {
            foreach ([$e['dia'], $e['dcnm']] as $departamento) {
                DB::table('department_closures')->insert(['period_id' => $e['periodo'], 'department_id' => $departamento, 'authorized_by' => $e['jefe']->id,
                    'authorized_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    });
}

it('CP-RF34-11 devolver un acta anula la autorización de su departamento y el período no se cierra con ella devuelta', function () {
    cierreFijarActas($this->e, 'validated', autorizar: true);
    actingAs($this->e['coordinador']);

    $this->notas->devolverActa($this->e['coordinador'], $this->e['seccion'], 'Hay que corregir una nota.');

    expect(DB::table('department_closures')->where('department_id', $this->e['dia'])->exists())->toBeFalse()
        ->and(DB::table('department_closures')->where('department_id', $this->e['dcnm'])->exists())->toBeTrue();

    // Aunque alguien reponga la autorización, cerrar() vuelve a exigir todas las actas validadas.
    DbContext::system(fn () => DB::table('department_closures')->insert(['period_id' => $this->e['periodo'], 'department_id' => $this->e['dia'],
        'authorized_by' => $this->e['jefe']->id, 'authorized_at' => now(), 'created_at' => now(), 'updated_at' => now()]));
    actingAs($this->e['administrador']);
    expect(fn () => app(PeriodoService::class)->cerrar($this->e['administrador'], $this->e['periodo']))->toThrow(ValidationException::class, 'actas sin validar');
});

it('CP-RF34-12 no publica el acta con notas en borrador ni con ensayos por calificar', function () {
    $e = $this->e;
    DbContext::system(fn () => DB::table('grades')->insert(['grade_item_id' => $this->actividad, 'enrollment_id' => $e['matricula'], 'section_id' => $e['seccion'],
        'score' => 8, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]));
    actingAs($e['docente']);
    expect(fn () => $this->notas->publicarActa($e['docente'], $e['seccion']))->toThrow(ValidationException::class, 'sin publicar');

    DbContext::system(function () use ($e) {
        DB::table('grades')->update(['status' => 'published', 'published_at' => now()]);
        $cuestionario = DB::table('assessments')->insertGetId(['ulid' => (string) str()->ulid(), 'section_id' => $e['seccion'], 'assessment_type' => 'quiz',
            'title' => 'Quiz 1', 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('quiz_attempts')->insert(['ulid' => (string) str()->ulid(), 'assessment_id' => $cuestionario, 'enrollment_id' => $e['matricula'], 'attempt_number' => 1,
            'deadline_at' => now(), 'status' => 'pending_grading', 'created_at' => now(), 'updated_at' => now()]);
    });
    expect(fn () => $this->notas->publicarActa($e['docente'], $e['seccion']))->toThrow(ValidationException::class, 'ensayos por calificar');
});

it('CP-RF17-14 al congelar solo entran las notas publicadas', function () {
    $e = $this->e;
    DbContext::system(fn () => DB::table('grades')->insert(['grade_item_id' => $this->actividad, 'enrollment_id' => $e['matricula'], 'section_id' => $e['seccion'],
        'score' => 9, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]));

    DbContext::system(fn () => $this->notas->congelarResultados($e['periodo']));

    expect((float) DB::table('enrollment_results')->where('enrollment_id', $e['matricula'])->value('final_score'))->toEqual(0.0);
});

it('CP-RF17-15 el Coordinador corrige tras el cierre una nota que faltaba, y recalcula el resultado', function () {
    $e = $this->e;
    actingAs($e['coordinador']);
    expect(fn () => $this->notas->corregirFaltante($e['coordinador'], $this->actividad, $e['matricula'], 7, 'Nota entregada tarde por el docente.'))
        ->toThrow(ValidationException::class, 'período está abierto');

    DbContext::system(function () use ($e) {
        $this->notas->congelarResultados($e['periodo']);
        DB::table('academic_periods')->where('id', $e['periodo'])->update(['status' => 'closed']);
    });
    $this->notas->corregirFaltante($e['coordinador'], $this->actividad, $e['matricula'], 7, 'Nota entregada tarde por el docente.');

    expect((float) DB::table('grades')->where('grade_item_id', $this->actividad)->value('score'))->toEqual(7.0)
        ->and((float) DB::table('enrollment_results')->where('enrollment_id', $e['matricula'])->value('final_score'))->toEqual(7.0);
});

it('CP-RF17-16 el redondeo sigue la regla de system_settings', function () {
    expect(CalificacionService::redondear(5.95))->toBe(6.0);

    DbContext::system(fn () => DB::table('system_settings')->updateOrInsert(['key' => 'grade_rounding'], ['value' => json_encode(['decimals' => 2, 'mode' => 'half_up'])]));

    expect(CalificacionService::redondear(5.955))->toBe(5.96);
});

it('CP-RF35-09 recalificar una nota publicada no reinicia el plazo de revisión', function () {
    $e = $this->e;
    actingAs($e['docente']);
    $this->notas->calificar($e['docente'], $this->actividad, $e['matricula'], 6);
    $primera = DB::table('grades')->value('published_at');

    $this->travel(3)->days();
    $this->notas->calificar($e['docente'], $this->actividad, $e['matricula'], 8);

    expect(DB::table('grades')->value('published_at'))->toBe($primera);
});
