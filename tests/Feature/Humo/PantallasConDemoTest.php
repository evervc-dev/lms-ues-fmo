<?php

use App\Models\User;
use App\Support\Navegacion\Vistas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class)->group('humo');

/**
 * Prueba de humo con la demo completa (DatabaseSeeder): cada cuenta *.dev abre cada vista del registro
 * con ids reales de su alcance. Ninguna puede responder 500.
 */
function parametrosReales(User $usuario, string $uri): array
{
    $seccion = DB::table('sections')->join('academic_periods', 'academic_periods.id', '=', 'sections.period_id')->where('academic_periods.status', 'active')
        ->where(fn ($q) => $q->whereIn('sections.id', DB::table('teaching_assignments')->where('teacher_id', $usuario->id)->whereNull('ended_at')->select('section_id'))
            ->orWhereIn('sections.id', DB::table('enrollments')->where('student_id', $usuario->id)->where('status', 'active')->select('section_id')))
        ->value('sections.id') ?? DB::table('sections')->join('academic_periods', 'academic_periods.id', '=', 'sections.period_id')->where('academic_periods.status', 'active')->value('sections.id');
    $ulid = DB::table('sections')->where('id', $seccion)->value('ulid');
    $evaluacion = fn (string $tipo) => DB::table('assessments')->where('section_id', $seccion)->where('assessment_type', $tipo)->value('ulid') ?? 'x';

    preg_match_all('/\{(\w+)\??\}/', $uri, $m);

    return collect($m[1])->mapWithKeys(fn ($p) => [$p => match ($p) {
        'seccion' => $ulid,
        'recurso' => DB::table('resources')->join('units', 'units.id', '=', 'resources.unit_id')->where('units.section_id', $seccion)->whereNull('resources.deleted_at')->value('resources.ulid') ?? 'x',
        'tarea' => $evaluacion('assignment'),
        'cuestionario' => $evaluacion('quiz'),
        'sesion' => DB::table('attendance_sessions')->where('section_id', $seccion)->value('ulid') ?? 'x',
        'hilo' => DB::table('forum_threads')->where('section_id', $seccion)->value('id') ?? 999999,
        'intento' => DB::table('quiz_attempts')->join('enrollments', 'enrollments.id', '=', 'quiz_attempts.enrollment_id')->where('enrollments.student_id', $usuario->id)->value('quiz_attempts.ulid') ?? 'x',
        'banco' => DB::table('question_banks')->where('owner_id', $usuario->id)->value('id') ?? 999999,
        'usuario' => $usuario->id,
        'periodo' => DB::table('academic_periods')->value('id'),
        'lote' => DB::table('import_batches')->value('id') ?? 999999,
        // La bienvenida es solo del estudiante; el resto abre el manual de su rol.
        'manual' => collect(['estudiante' => 'bienvenida', 'docente' => 'docente', 'coordinador' => 'coordinador', 'jefe_departamento' => 'jefe-de-departamento',
            'administrador' => 'administrador', 'soporte_tecnico' => 'soporte-tecnico'])->first(fn (string $slug, string $rol) => $usuario->hasRole($rol)) ?? 'bienvenida',
        default => 'x',
    }])->all();
}

it('opens every screen with every development account on the demo data without errors', function () {
    $this->seed();
    $this->artisan('ayuda:sincronizar');
    $fallas = [];

    foreach (['admin', 'jefe', 'coordinador', 'docente', 'estudiante', 'soporte'] as $cuenta) {
        $usuario = User::query()->where('institutional_email', "{$cuenta}.dev@ues.edu.sv")->firstOrFail();
        foreach (['inicio' => ['uri' => '/inicio']] + Vistas::todas() as $nombre => $vista) {
            $respuesta = $this->actingAs($usuario)->get(route($nombre, parametrosReales($usuario, $vista['uri'])));
            if ($respuesta->status() >= 500) {
                $fallas[] = "{$cuenta} en {$nombre}: ".($respuesta->exception?->getMessage() ?? $respuesta->status());
            }
        }
    }

    expect($fallas)->toBe([]);
});

it('siembra datos de demo coherentes: cada evaluación cierra dentro de su cómputo y no hay entregas a tiempo después del cierre', function () {
    $this->seed();

    $fueraDelComputo = DB::table('assessments as a')->join('grade_items as g', 'g.id', '=', 'a.grade_item_id')->join('grading_components as c', 'c.id', '=', 'g.component_id')
        ->where(fn ($q) => $q->whereDate('a.closes_at', '<', DB::raw('c.starts_on'))->orWhereDate('a.closes_at', '>', DB::raw('c.ends_on')))
        ->pluck('a.title')->unique()->values()->all();
    $aTiempoDespuesDelCierre = DB::table('submissions as s')->join('assessments as a', 'a.id', '=', 's.assessment_id')
        ->where('s.is_late', false)->whereColumn('s.submitted_at', '>', 'a.closes_at')->count();

    expect($fueraDelComputo)->toBe([])->and($aTiempoDespuesDelCierre)->toBe(0);
});

it('siembra los planes de ISI con materias propias y ninguna matrícula fuera del plan del estudiante', function () {
    $this->seed();
    $planesDe = fn (string $codigo) => DB::table('career_subjects as cs')->join('study_plans as sp', 'sp.id', '=', 'cs.study_plan_id')
        ->join('subjects as s', 's.id', '=', 'cs.subject_id')->where('s.code', $codigo)->orderBy('sp.name')->pluck('sp.name')->all();

    expect($planesDe('PRN115'))->toBe(['Plan 25', 'Plan 98'])
        ->and($planesDe('ISI115'))->toBe(['Plan 25'])
        ->and($planesDe('HDP115'))->toBe(['Plan 98']);

    // Cada matrícula sembrada es de una materia del plan del estudiante.
    $fueraDelPlan = DB::table('enrollments as e')->join('student_careers as sc', fn ($j) => $j->on('sc.student_id', '=', 'e.student_id')->where('sc.status', 'active'))
        ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('career_subjects as cs')
            ->whereColumn('cs.study_plan_id', 'sc.study_plan_id')->whereColumn('cs.subject_id', 'e.subject_id'))
        ->count();
    expect($fueraDelPlan)->toBe(0);
});

it('siembra las fechas del calendario de evaluaciones sin marcarlas como agendadas en Google', function () {
    $this->seed();

    expect(DB::table('period_calendar_events')->count())->toBe(4)
        ->and(DB::table('google_sync_refs')->where('entity_type', 'period_calendar_event')->exists())->toBeFalse();
});
