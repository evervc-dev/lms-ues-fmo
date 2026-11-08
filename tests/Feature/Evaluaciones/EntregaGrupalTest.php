<?php

use App\Modules\Evaluaciones\Livewire\Entregas;
use App\Modules\Evaluaciones\Livewire\NuevaTarea;
use App\Modules\Evaluaciones\Livewire\Tarea;
use App\Modules\Evaluaciones\Services\ActividadService;
use App\Modules\Evaluaciones\Services\EquipoService;
use App\Modules\Reportes\Services\PanelService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake());

/**
 * Entrega grupal (RF15, CP-RF15-05): el docente arma los equipos, la entrega de un integrante cuenta para todo el
 * equipo y la nota se copia a cada uno, con ajuste individual (decidido por el usuario el 31/10/2026).
 */

/**
 * Escenario con una tarea grupal abierta y un segundo estudiante en la sección.
 *
 * @return array<string, mixed>
 */
function escenarioGrupal(): array
{
    $e = escenario();
    $e['companero'] = usuarioCon('estudiante');
    $e['matriculaCompanero'] = DbContext::system(function () use ($e) {
        DB::table('student_profiles')->insert(['user_id' => $e['companero']->id, 'student_code' => 'GS21005', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('grading_components')->insert(['section_id' => $e['seccion'], 'number' => 1, 'name' => 'Cómputo 1', 'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11',
            'weight_percent' => 100, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('enrollments')->insertGetId(['student_id' => $e['companero']->id, 'section_id' => $e['seccion'], 'period_id' => $e['periodo'], 'subject_id' => $e['prn'],
            'status' => 'active', 'enrolled_via' => 'key', 'enrolled_by' => $e['companero']->id, 'created_at' => now(), 'updated_at' => now()]);
    });

    actingAs($e['docente']);
    Livewire::test(NuevaTarea::class, ['seccion' => $e['ulid']])->set('titulo', 'Proyecto en equipo')
        ->set('componente', (int) DB::table('grading_components')->where('section_id', $e['seccion'])->value('id'))->set('peso', 100)
        ->set('abre', now()->subDay()->format('Y-m-d\TH:i'))->set('cierra', now()->addDay()->format('Y-m-d\TH:i'))->set('extensiones', 'pdf')
        ->set('grupal', true)->call('guardar')->assertHasNoErrors();
    $e['tarea'] = (string) DB::table('assessments')->where('title', 'Proyecto en equipo')->value('ulid');
    $e['tareaId'] = (int) DB::table('assessments')->where('title', 'Proyecto en equipo')->value('id');

    return $e;
}

/** Arma el equipo «Alfa» con los dos estudiantes del escenario. */
function equipoAlfa(array $e): int
{
    return app(EquipoService::class)->crear($e['docente'], $e['seccion'], $e['tareaId'], 'Alfa', [$e['matricula'], $e['matriculaCompanero']]);
}

it('CP-RF15-05 la entrega de un integrante cuenta para todo el equipo', function () {
    $e = escenarioGrupal();
    equipoAlfa($e);

    actingAs($e['estudiante']);
    Livewire::test(Tarea::class, ['seccion' => $e['ulid'], 'tarea' => $e['tarea']])
        ->set('archivo', UploadedFile::fake()->create('proyecto.pdf', 100))->call('entregar')->assertHasNoErrors();

    $entrega = DB::table('submissions')->sole();
    expect($entrega->team_id)->not->toBeNull()->and($entrega->enrollment_id)->toBeNull();

    // El compañero ve la entrega como suya y ya no la tiene pendiente.
    $delCompanero = app(ActividadService::class)->tarea($e['companero'], $e['seccion'], $e['tarea']);
    expect($delCompanero['entrega']->id)->toBe($entrega->id)
        ->and($delCompanero['equipo']->name)->toBe('Alfa')
        ->and(app(ActividadService::class)->porComputo($e['companero'], $e['seccion'])->first()->actividades->first()->hecha)->toBeTrue()
        ->and(app(PanelService::class)->estudiante($e['companero'])['pendientes']->pluck('title'))->not->toContain('Proyecto en equipo');

    // Los intentos son del equipo: el compañero no puede sumar otra entrega con un intento.
    actingAs($e['companero']);
    Livewire::test(Tarea::class, ['seccion' => $e['ulid'], 'tarea' => $e['tarea']])
        ->set('archivo', UploadedFile::fake()->create('otra.pdf', 100))->call('entregar')->assertHasErrors(['archivo']);
    expect(DB::table('submissions')->count())->toBe(1);
});

it('CP-RF15-05 sin equipo asignado el estudiante no puede entregar una tarea grupal', function () {
    $e = escenarioGrupal();
    actingAs($e['estudiante']);

    Livewire::test(Tarea::class, ['seccion' => $e['ulid'], 'tarea' => $e['tarea']])
        ->assertSee('todavía no tiene equipo')
        ->set('archivo', UploadedFile::fake()->create('proyecto.pdf', 100))->call('entregar')->assertHasErrors(['archivo']);
    expect(DB::table('submissions')->count())->toBe(0);
});

it('CP-RF15-05 la nota del equipo se copia a cada integrante y se puede ajustar la de uno con motivo', function () {
    $e = escenarioGrupal();
    equipoAlfa($e);
    actingAs($e['estudiante']);
    app(ActividadService::class)->entregar($e['estudiante'], $e['seccion'], $e['tarea'], UploadedFile::fake()->create('proyecto.pdf', 100));
    $entrega = (int) DB::table('submissions')->value('id');
    $item = (int) DB::table('assessments')->where('id', $e['tareaId'])->value('grade_item_id');
    $notaDe = fn (int $matricula) => (float) DB::table('grades')->where('grade_item_id', $item)->where('enrollment_id', $matricula)->value('score');

    actingAs($e['docente']);
    app(ActividadService::class)->calificarEntrega($e['docente'], $e['seccion'], $entrega, 8.5, [], 'Buen trabajo.');
    expect($notaDe($e['matricula']))->toBe(8.5)->and($notaDe($e['matriculaCompanero']))->toBe(8.5);

    expect(fn () => app(ActividadService::class)->ajustarIntegrante($e['docente'], $e['seccion'], $entrega, $e['matriculaCompanero'], 5.0, ' '))
        ->toThrow(ValidationException::class, 'exige un motivo');
    Livewire::test(Entregas::class, ['seccion' => $e['ulid'], 'tarea' => $e['tarea']])
        ->call('abrirAjuste', $entrega, $e['matriculaCompanero'])->set('notaIntegrante', 5)->set('motivoAjuste', 'No participó en la defensa.')
        ->call('ajustar')->assertHasNoErrors()->assertDispatched('aviso', tipo: 'exito');

    expect($notaDe($e['matricula']))->toBe(8.5)->and($notaDe($e['matriculaCompanero']))->toBe(5.0)
        ->and(DB::table('grades')->where('enrollment_id', $e['matriculaCompanero'])->value('comment'))->toBe('No participó en la defensa.');

    // Ya calificada, la entrega del equipo sale de la bandeja de pendientes del docente.
    expect(app(ActividadService::class)->pendientes($e['docente']))->toBeEmpty();
});

it('CP-RF15-05 el docente arma los equipos: nadie en dos equipos, solo de la sección, y no se elimina uno que ya entregó', function () {
    $e = escenarioGrupal();
    $equipos = app(EquipoService::class);
    actingAs($e['docente']);

    Livewire::test(Entregas::class, ['seccion' => $e['ulid'], 'tarea' => $e['tarea']])
        ->assertSee('Equipos')->set('nombreEquipo', 'Alfa')->set('integrantes', [$e['matricula']])->call('crearEquipo')->assertHasNoErrors()
        ->assertSee('Alfa');

    expect(fn () => $equipos->crear($e['docente'], $e['seccion'], $e['tareaId'], 'Beta', [$e['matricula']]))
        ->toThrow(ValidationException::class, 'dos equipos de la misma tarea');
    expect(fn () => $equipos->crear($e['docente'], $e['seccion'], $e['tareaId'], 'Beta', [$e['matricula'] + 999]))
        ->toThrow(ValidationException::class, 'matriculados en esta sección');

    $alfa = (int) DB::table('teams')->value('id');
    actingAs($e['estudiante']);
    app(ActividadService::class)->entregar($e['estudiante'], $e['seccion'], $e['tarea'], UploadedFile::fake()->create('proyecto.pdf', 100));
    actingAs($e['docente']);
    expect(fn () => $equipos->eliminar($e['docente'], $e['seccion'], $e['tareaId'], $alfa))->toThrow(ValidationException::class, 'ya entregó');
});

it('CP-RF15-05 solo el docente de la sección arma equipos, y solo en tareas grupales', function () {
    $e = escenarioGrupal();
    $equipos = app(EquipoService::class);

    foreach (['estudiante', 'coordinador', 'jefe'] as $rol) {
        actingAs($e[$rol]);
        expect(fn () => $equipos->crear($e[$rol], $e['seccion'], $e['tareaId'], 'Alfa', [$e['matricula']]))
            ->toThrow(HttpException::class);
    }

    actingAs($e['docente']);
    DbContext::system(fn () => DB::table('assignment_settings')->where('assessment_id', $e['tareaId'])->update(['is_group' => false]));
    expect(fn () => $equipos->crear($e['docente'], $e['seccion'], $e['tareaId'], 'Alfa', [$e['matricula']]))
        ->toThrow(ValidationException::class, 'no es grupal');
});
