<?php

use App\Modules\Reportes\Services\PanelService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Panel del estudiante (RF24, maqueta 02): materias en recuadros, agenda de 7 días, avance por recursos
 * y sesión de asistencia abierta.
 */
function panelDelEstudiante(array $e): array
{
    return app(PanelService::class)->estudiante($e['estudiante']);
}

/** Una unidad con dos recursos, uno de ellos ya leído por el estudiante. */
function unidadConAvance(array $e): void
{
    DbContext::system(function () use ($e) {
        $unidad = DB::table('units')->insertGetId(['section_id' => $e['seccion'], 'title' => 'Unidad 1', 'position' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $recursos = collect(['Lectura', 'Video'])->map(fn ($t, $i) => DB::table('resources')->insertGetId([
            'ulid' => (string) str()->ulid(), 'unit_id' => $unidad, 'title' => $t, 'resource_type' => 'page',
            'completion_mode' => 'manual', 'position' => $i + 1, 'created_at' => now(), 'updated_at' => now(),
        ]));
        DB::table('resource_progress')->insert(['resource_id' => $recursos->first(), 'enrollment_id' => $e['matricula'], 'origin' => 'manual', 'completed_at' => now()]);
    });
}

it('CP-RF24-01 muestra los pendientes por fecha y la agenda de la semana', function () {
    $e = escenario();
    actingAs($e['estudiante']);

    $panel = panelDelEstudiante($e);

    expect($panel)->toHaveKeys(['pendientes', 'materias', 'agenda', 'asistencia', 'notas', 'clasesVirtuales', 'sesionAbierta']);

    // El escenario deja una clase los lunes: la agenda la ubica en el día que toca, dentro de los 7.
    $dias = $panel['agenda']->keys();
    expect($dias->count())->toBeGreaterThan(0)
        ->and($dias->every(fn (string $d) => $d >= now()->toDateString() && $d <= now()->addDays(7)->toDateString()))->toBeTrue();
});

it('arma los recuadros de materia con docente, horario y avance por recursos completados', function () {
    $e = escenario();
    unidadConAvance($e);
    actingAs($e['estudiante']);

    $materia = panelDelEstudiante($e)['materias']->firstWhere('code', 'PRN115');

    expect($materia->name)->toBe('Programación I')
        ->and($materia->docente)->toBe($e['docente']->fullName())
        ->and($materia->avance)->toBe(50)                 // 1 de 2 recursos
        ->and($materia->recursos)->toBe(['hechos' => 1, 'total' => 2])
        ->and($materia->horario)->toHaveCount(1);
});

it('deja el avance sin dato cuando la materia aún no publica recursos', function () {
    $e = escenario();
    actingAs($e['estudiante']);

    expect(panelDelEstudiante($e)['materias']->firstWhere('code', 'PRN115')->avance)->toBeNull();
});

it('junta en la agenda las entregas que vencen dentro de los 7 días y deja fuera las posteriores', function () {
    $e = escenario();
    DbContext::system(function () use ($e) {
        $computo = DB::table('grading_components')->insertGetId(['section_id' => $e['seccion'], 'number' => 1, 'name' => 'Cómputo 1',
            'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11', 'weight_percent' => 100, 'created_at' => now(), 'updated_at' => now()]);
        $item = DB::table('grade_items')->insertGetId(['component_id' => $computo, 'section_id' => $e['seccion'], 'item_type' => 'assignment',
            'title' => 'Tarea 1', 'weight_percent' => 10, 'is_published' => false, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['Entrega cercana', now()->addDays(2)], ['Entrega lejana', now()->addDays(20)]] as [$titulo, $cierra]) {
            DB::table('assessments')->insert(['ulid' => (string) str()->ulid(), 'section_id' => $e['seccion'], 'grade_item_id' => $item,
                'title' => $titulo, 'assessment_type' => 'assignment', 'opens_at' => now()->subDay(), 'closes_at' => $cierra,
                'max_attempts' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
    });
    actingAs($e['estudiante']);

    $titulos = panelDelEstudiante($e)['agenda']->flatten()->pluck('titulo');

    expect($titulos)->toContain('Entrega cercana')
        ->and($titulos)->not->toContain('Entrega lejana');
});

it('no deja que un estudiante vea las materias ni la agenda de otro', function () {
    $e = escenario();
    $ajeno = usuarioCon('estudiante');
    actingAs($ajeno);

    $panel = app(PanelService::class)->estudiante($ajeno);

    expect($panel['materias'])->toBeEmpty()
        ->and($panel['agenda'])->toBeEmpty();
});

it('anuncia la sesión de asistencia abierta de una de sus materias', function () {
    $e = escenario();
    actingAs($e['estudiante']);

    expect(panelDelEstudiante($e)['sesionAbierta'])->toBeNull();

    DbContext::system(fn () => DB::table('attendance_sessions')->insert(['ulid' => (string) str()->ulid(), 'section_id' => $e['seccion'],
        'opened_by' => $e['docente']->id, 'modality' => 'in_person', 'status' => 'open', 'session_date' => now()->toDateString(),
        'opened_at' => now(), 'qr_validity_minutes' => 10, 'created_at' => now(), 'updated_at' => now()]));

    expect(panelDelEstudiante($e)['sesionAbierta']->code)->toBe('PRN115');
});

it('dibuja el acordeón solo cuando el usuario tiene más de un rol', function () {
    $e = escenario();

    actingAs($e['estudiante'])->get(route('inicio'))->assertOk()->assertDontSeeText('Pasos del formulario');
    $this->get(route('inicio'))->assertDontSee('acordeon-', false);

    DbContext::system(fn () => $e['estudiante']->assignRole('docente'));
    actingAs($e['estudiante']->fresh())->get(route('inicio'))->assertSee('acordeon-', false);
});
