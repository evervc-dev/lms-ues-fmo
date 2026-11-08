<?php

use App\Modules\Evaluaciones\Livewire\Entregas;
use App\Modules\Evaluaciones\Livewire\NuevaTarea;
use App\Modules\Evaluaciones\Livewire\Tarea;
use App\Modules\Evaluaciones\Services\RubricaService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake());

/**
 * Rúbrica dentro de la tarea (RF15, CP-RF15-06, ADR-065): se arma al crear la tarea o en su pantalla de entregas, no
 * en una lista aparte; los exámenes no la llevan, y queda fija desde la primera calificación (decidido por el usuario
 * el 01/11/2026).
 */

/**
 * Crea como el docente la tarea «Informe» en un cómputo único y devuelve su ulid.
 *
 * @param  array<string, mixed>  $cambios
 */
function tareaInforme(array $e, array $cambios = []): string
{
    $componente = DbContext::system(fn () => DB::table('grading_components')->insertGetId(['section_id' => $e['seccion'], 'number' => 1, 'name' => 'Cómputo 1',
        'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11', 'weight_percent' => 100, 'created_at' => now(), 'updated_at' => now()]));
    actingAs($e['docente']);
    $prueba = Livewire::test(NuevaTarea::class, ['seccion' => $e['ulid']])->set('titulo', 'Informe')->set('componente', $componente)->set('peso', 50)
        ->set('abre', now()->subDay()->format('Y-m-d\TH:i'))->set('cierra', now()->addDay()->format('Y-m-d\TH:i'))->set('extensiones', 'pdf');
    foreach ($cambios as $propiedad => $valor) {
        $prueba->set($propiedad, $valor);
    }
    $prueba->call('guardar')->assertHasNoErrors();

    return (string) DB::table('assessments')->where('title', 'Informe')->value('ulid');
}

/** El estudiante del escenario entrega el informe. */
function entregaInforme(array $e, string $tarea): int
{
    actingAs($e['estudiante']);
    Livewire::test(Tarea::class, ['seccion' => $e['ulid'], 'tarea' => $tarea])->set('archivo', UploadedFile::fake()->create('informe.pdf', 50))->call('entregar')->assertHasNoErrors();

    return (int) DB::table('submissions')->value('id');
}

it('CP-RF15-06 el docente arma la rúbrica al crear la tarea y la nota es la suma de los niveles', function () {
    $e = escenario();
    $tarea = tareaInforme($e, ['conRubrica' => true, 'criterios' => [['nombre' => 'Contenido', 'maximo' => 6], ['nombre' => 'Forma', 'maximo' => 4]]]);

    $rubrica = DB::table('assessments')->where('ulid', $tarea)->value('rubric_id');
    expect(DB::table('rubrics')->where('id', $rubrica)->value('title'))->toBe('Informe')
        ->and(DB::table('rubric_criteria')->where('rubric_id', $rubrica)->orderBy('position')->pluck('max_points')->map(fn ($p) => (float) $p)->all())->toBe([6.0, 4.0])
        ->and(DB::table('rubric_levels')->join('rubric_criteria', 'rubric_criteria.id', '=', 'rubric_levels.criterion_id')->where('rubric_criteria.name', 'Contenido')
            ->orderByDesc('points')->pluck('points')->map(fn ($p) => (float) $p)->all())->toBe([6.0, 4.2, 2.4]);

    $entrega = entregaInforme($e, $tarea);
    actingAs($e['docente']);
    $bueno = DB::table('rubric_levels')->where('name', 'Bueno')->pluck('id', 'criterion_id')->all();
    Livewire::test(Entregas::class, ['seccion' => $e['ulid'], 'tarea' => $tarea])->call('abrir', $entrega)->set('niveles', $bueno)->call('calificar');

    expect((float) DB::table('grades')->value('score'))->toEqual(7.0);
});

it('CP-RF15-06 rechaza una rúbrica cuyos puntos no suman 10', function () {
    $e = escenario();
    $componente = DbContext::system(fn () => DB::table('grading_components')->insertGetId(['section_id' => $e['seccion'], 'number' => 1, 'name' => 'Cómputo 1',
        'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11', 'weight_percent' => 100, 'created_at' => now(), 'updated_at' => now()]));

    actingAs($e['docente']);
    Livewire::test(NuevaTarea::class, ['seccion' => $e['ulid']])->set('titulo', 'Informe')->set('componente', $componente)->set('peso', 50)->set('extensiones', 'pdf')
        ->set('conRubrica', true)->set('criterios', [['nombre' => 'Contenido', 'maximo' => 6], ['nombre' => 'Forma', 'maximo' => 3]])
        ->call('guardar')->assertHasErrors('criterios');

    expect(DB::table('assessments')->count())->toBe(0)->and(DB::table('rubrics')->count())->toBe(0);
});

it('CP-RF15-06 la rúbrica se agrega, se cambia o se quita en la tarea hasta la primera calificación', function () {
    $e = escenario();
    $tarea = tareaInforme($e);
    $rubricaDe = fn () => DB::table('assessments')->where('ulid', $tarea)->value('rubric_id');

    actingAs($e['docente']);
    $pantalla = Livewire::test(Entregas::class, ['seccion' => $e['ulid'], 'tarea' => $tarea])->assertSee('Agregar rúbrica')
        ->call('abrirRubrica')->set('criteriosRubrica', [['nombre' => 'Contenido', 'maximo' => 10]])->call('guardarRubrica')->assertHasNoErrors();
    expect($rubricaDe())->not->toBeNull();

    // Al cambiarla se reemplaza: la anterior no queda suelta.
    $pantalla->call('abrirRubrica')->assertSet('criteriosRubrica', [['nombre' => 'Contenido', 'maximo' => 10.0]])
        ->set('criteriosRubrica', [['nombre' => 'Contenido', 'maximo' => 7], ['nombre' => 'Forma', 'maximo' => 3]])->call('guardarRubrica')->assertHasNoErrors();
    expect(DB::table('rubrics')->count())->toBe(1)->and(DB::table('rubric_criteria')->where('rubric_id', $rubricaDe())->count())->toBe(2);

    $pantalla->call('quitarRubrica');
    expect($rubricaDe())->toBeNull()->and(DB::table('rubrics')->count())->toBe(0);

    // Con la primera calificación queda fija.
    $pantalla->call('abrirRubrica')->set('criteriosRubrica', [['nombre' => 'Contenido', 'maximo' => 10]])->call('guardarRubrica');
    $entrega = entregaInforme($e, $tarea);
    actingAs($e['docente']);
    $excelente = DB::table('rubric_levels')->where('name', 'Excelente')->pluck('id', 'criterion_id')->all();
    Livewire::test(Entregas::class, ['seccion' => $e['ulid'], 'tarea' => $tarea])->call('abrir', $entrega)->set('niveles', $excelente)->call('calificar')
        ->assertSee('la rúbrica quedó fija')->assertDontSee('Editar rúbrica');

    expect(fn () => app(RubricaService::class)->guardar($e['docente'], $e['seccion'], $tarea, [['nombre' => 'Otro', 'maximo' => 10]]))
        ->toThrow(ValidationException::class, 'La tarea ya tiene calificaciones: la rúbrica no se puede cambiar.');
});

it('CP-RF15-06 solo el docente de la sección arma la rúbrica, y un examen no la lleva', function () {
    $e = escenario();
    $tarea = tareaInforme($e);
    $criterios = [['nombre' => 'Contenido', 'maximo' => 10]];

    expect(fn () => app(RubricaService::class)->guardar(docenteAjeno($e), $e['seccion'], $tarea, $criterios))->toThrow(HttpException::class)
        ->and(fn () => app(RubricaService::class)->guardar($e['estudiante'], $e['seccion'], $tarea, $criterios))->toThrow(HttpException::class);

    $examen = (string) Str::ulid();
    DbContext::system(fn () => DB::table('assessments')->insert(['ulid' => $examen, 'section_id' => $e['seccion'], 'assessment_type' => 'quiz', 'title' => 'Parcial 1',
        'opens_at' => now(), 'closes_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now()]));
    try {
        app(RubricaService::class)->guardar($e['docente'], $e['seccion'], $examen, $criterios);
        $this->fail('Un examen no debería aceptar rúbrica.');
    } catch (HttpException $error) {
        expect($error->getStatusCode())->toBe(404);
    }
    expect(DB::table('rubrics')->count())->toBe(0);
});

it('CP-RF15-06 ya no hay página ni permiso de rúbricas aparte', function () {
    $e = escenario();

    expect(Route::has('rubricas'))->toBeFalse()
        ->and(Permission::query()->where('name', 'rubricas.gestionar')->exists())->toBeFalse();
    actingAs($e['docente'])->get(route('secciones.tareas.crear', ['seccion' => $e['ulid']]))->assertOk()
        ->assertSeeText('Calificar con rúbrica')->assertDontSeeText('Sin rúbrica (nota directa)');
});
