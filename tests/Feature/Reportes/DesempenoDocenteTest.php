<?php

use App\Modules\Reportes\Livewire\DesempenoDocente;
use App\Modules\Reportes\Services\DesempenoService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Indicadores de desempeño docente (RF33): retraso al abrir la asistencia respecto al horario y tiempo desde la
 * entrega hasta la publicación de la nota (cálculo decidido por el usuario el 01/11/2026).
 */

/** Indicadores del docente del escenario, tal como los ve el Jefe. */
function indicadoresDelDocente(array $e): stdClass
{
    return app(DesempenoService::class)->indicadores($e['jefe'])->firstWhere('id', $e['docente']->id);
}

/** Una sesión de asistencia de la sección del escenario abierta por su docente en ese momento. */
function sesionAbiertaEl(array $e, string $momento): void
{
    DbContext::system(fn () => DB::table('attendance_sessions')->insert(['ulid' => (string) Str::ulid(), 'section_id' => $e['seccion'], 'session_date' => substr($momento, 0, 10),
        'status' => 'closed', 'opened_at' => $momento, 'closed_at' => $momento, 'opened_by' => $e['docente']->id, 'created_at' => now(), 'updated_at' => now()]));
}

it('CP-RF33-02 la puntualidad son los minutos entre el inicio de la clase y la apertura de la asistencia', function () {
    $e = escenario();
    // El escenario trae la clase del lunes a las 07:00; se agrega otra el lunes a las 10:00.
    DbContext::system(fn () => DB::table('section_schedules')->insert(['section_id' => $e['seccion'], 'period_id' => $e['periodo'], 'weekday' => 1,
        'start_time' => '10:00', 'end_time' => '11:40', 'created_at' => now(), 'updated_at' => now()]));

    sesionAbiertaEl($e, '2026-08-03 06:55:00'); // lunes, 5 min antes: cuenta 0
    sesionAbiertaEl($e, '2026-08-10 07:10:00'); // 10 min tarde
    sesionAbiertaEl($e, '2026-08-17 07:40:00'); // 40 min tarde
    sesionAbiertaEl($e, '2026-08-24 09:50:00'); // antes del bloque de las 10:00, el más cercano: cuenta 0
    sesionAbiertaEl($e, '2026-08-04 09:00:00'); // martes, sin clase en el horario: no cuenta

    $docente = indicadoresDelDocente($e);
    expect($docente->retraso)->toBe(12.5)
        ->and($docente->aTiempo)->toBe(75.0);

    actingAs($e['jefe']);
    Livewire::test(DesempenoDocente::class)->assertSee('12.5 min')->assertSee('75 %')->assertSee('Cómo se calculan')->assertDontSee('Indicadores provisionales');
});

it('CP-RF33-03 la revisión se mide desde la entrega hasta la publicación de la nota', function () {
    $e = escenario();
    $otro = usuarioCon('estudiante');
    DbContext::system(function () use ($e, $otro) {
        $t = fn (array $fila) => $fila + ['created_at' => now(), 'updated_at' => now()];
        $componente = DB::table('grading_components')->insertGetId($t(['section_id' => $e['seccion'], 'number' => 1, 'name' => 'Cómputo 1', 'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11', 'weight_percent' => 100]));
        $item = DB::table('grade_items')->insertGetId($t(['component_id' => $componente, 'section_id' => $e['seccion'], 'item_type' => 'assignment', 'title' => 'Informe', 'weight_percent' => 50, 'is_published' => false]));
        $tarea = DB::table('assessments')->insertGetId($t(['ulid' => (string) Str::ulid(), 'section_id' => $e['seccion'], 'grade_item_id' => $item, 'assessment_type' => 'assignment',
            'title' => 'Informe', 'opens_at' => '2026-07-30 07:00', 'closes_at' => '2026-08-20 23:59', 'max_attempts' => 2]));
        $matriculaOtro = DB::table('enrollments')->insertGetId($t(['student_id' => $otro->id, 'section_id' => $e['seccion'], 'period_id' => $e['periodo'], 'subject_id' => $e['prn'],
            'status' => 'active', 'enrolled_via' => 'key', 'enrolled_by' => $otro->id]));
        $entrega = fn (int $matricula, int $intento, string $cuando) => DB::table('submissions')->insert($t(['ulid' => (string) Str::ulid(), 'assessment_id' => $tarea, 'section_id' => $e['seccion'],
            'enrollment_id' => $matricula, 'attempt_number' => $intento, 'status' => 'submitted', 'is_late' => false, 'late_penalty_applied' => 0, 'submitted_at' => $cuando]));

        // El primer intento no cuenta: se mide el último, el que se calificó.
        $entrega($e['matricula'], 1, '2026-08-01 08:00');
        $entrega($e['matricula'], 2, '2026-08-10 08:00');
        DB::table('grades')->insert($t(['grade_item_id' => $item, 'enrollment_id' => $e['matricula'], 'section_id' => $e['seccion'], 'score' => 8, 'status' => 'published', 'published_at' => '2026-08-10 20:00']));
        // Calificada pero sin publicar: va a «Sin publicar», no al promedio.
        $entrega($matriculaOtro, 1, '2026-08-10 09:00');
        DB::table('grades')->insert($t(['grade_item_id' => $item, 'enrollment_id' => $matriculaOtro, 'section_id' => $e['seccion'], 'score' => 7, 'status' => 'draft']));
    });

    $docente = indicadoresDelDocente($e);
    expect($docente->revisionHoras)->toBe(12.0)
        ->and($docente->sinPublicar)->toBe(1);
});

it('CP-RF33-02 CP-RF33-03 sin sesiones ni entregas los indicadores quedan vacíos', function () {
    $e = escenario();

    $docente = indicadoresDelDocente($e);
    expect($docente->retraso)->toBeNull()
        ->and($docente->aTiempo)->toBeNull()
        ->and($docente->revisionHoras)->toBeNull()
        ->and($docente->sinPublicar)->toBe(0);
});
