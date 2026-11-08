<?php

use App\Modules\Contenidos\Jobs\RegistrarAccesoRecurso;
use App\Modules\Contenidos\Livewire\Analitica;
use App\Modules\Contenidos\Livewire\Recurso;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake());

/**
 * Seguimiento de acceso a recursos (RF13): apertura y descarga con fecha y hora, en cola; tiempo con la pestaña
 * visible por latidos de 30 s; volver antes de 5 min es la misma apertura (decidido por el usuario el 01/11/2026).
 */

/** Aperturas y descargas del estudiante del escenario. */
function accesos(array $e): Collection
{
    return DB::table('resource_access_logs')->where('enrollment_id', $e['matricula'])->orderBy('occurred_at')->orderBy('id')->get();
}

it('CP-RF13-01 registra la apertura en cola una sola vez, aunque el estudiante interactúe con la página', function () {
    $e = escenario();
    $recurso = recursoEnUnidad(unidadDeContenidos($e['seccion']));
    Queue::fake();

    actingAs($e['estudiante']);
    Livewire::test(Recurso::class, ['seccion' => $e['ulid'], 'recurso' => $recurso['ulid']])->call('marcarLeido')->call('marcarLeido');

    Queue::assertPushedOn('default', RegistrarAccesoRecurso::class);
    Queue::assertPushed(RegistrarAccesoRecurso::class, 1);
    Queue::assertPushed(RegistrarAccesoRecurso::class, fn (RegistrarAccesoRecurso $job) => $job->evento === 'open' && $job->recursoId === $recurso['id'] && $job->matriculaId === $e['matricula']);
});

it('CP-RF13-01 volver al recurso antes de 5 minutos es la misma apertura; después cuenta otra', function () {
    $e = escenario();
    $recurso = recursoEnUnidad(unidadDeContenidos($e['seccion']));
    $url = route('secciones.recurso', ['seccion' => $e['ulid'], 'recurso' => $recurso['ulid']]);

    actingAs($e['estudiante'])->get($url)->assertOk();
    $this->travel(4)->minutes();
    $this->get($url)->assertOk();
    expect(accesos($e))->toHaveCount(1);

    $this->travel(2)->minutes();
    $this->get($url)->assertOk();
    expect(accesos($e)->pluck('event_type')->all())->toBe(['open', 'open']);
});

it('CP-RF13-01 registra la descarga del archivo y del .zip, pero no el PDF que se ve en la plataforma', function () {
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    Storage::put("contenidos/{$e['seccion']}/guia.pdf", '%PDF-1.4');
    Storage::put("contenidos/{$e['seccion']}/datos.xlsx", 'xlsx');
    Storage::put("contenidos/{$e['seccion']}/carpetas/c1/a.txt", 'a');
    $visor = recursoEnUnidad($unidad, ['resource_type' => 'file', 'title' => 'Guía', 'file_path' => "contenidos/{$e['seccion']}/guia.pdf", 'pdf_mode' => 'viewer']);
    $archivo = recursoEnUnidad($unidad, ['resource_type' => 'file', 'title' => 'Datos', 'file_path' => "contenidos/{$e['seccion']}/datos.xlsx", 'pdf_mode' => 'download', 'position' => 2]);
    $carpeta = recursoEnUnidad($unidad, ['resource_type' => 'folder', 'title' => 'Carpeta', 'file_path' => "contenidos/{$e['seccion']}/carpetas/c1", 'position' => 3]);
    DbContext::system(fn () => DB::table('resource_files')->insert(['resource_id' => $carpeta['id'], 'path' => 'a.txt', 'name' => 'a.txt', 'bytes' => 1, 'created_at' => now(), 'updated_at' => now()]));
    $ruta = fn (array $r, string $tipo) => route("secciones.recurso.{$tipo}", ['seccion' => $e['ulid'], 'recurso' => $r['ulid']]);

    actingAs($e['estudiante']);
    $this->get($ruta($visor, 'archivo'))->assertOk();
    $this->get($ruta($archivo, 'archivo'))->assertOk();
    $this->get($ruta($carpeta, 'zip'))->assertOk();

    $registros = accesos($e);
    expect($registros->pluck('event_type')->all())->toBe(['download', 'download'])
        ->and($registros->pluck('resource_id')->all())->toBe([$archivo['id'], $carpeta['id']])
        ->and($registros->pluck('occurred_at')->filter()->count())->toBe(2);
});

it('CP-RF13-01 no registra nada a quien no está matriculado, como el docente', function () {
    $e = escenario();
    $recurso = recursoEnUnidad(unidadDeContenidos($e['seccion']));
    Queue::fake();

    actingAs($e['docente']);
    Livewire::test(Recurso::class, ['seccion' => $e['ulid'], 'recurso' => $recurso['ulid']])->call('latido', 30);

    Queue::assertNothingPushed();
});

it('CP-RF13-02 suma el tiempo con la pestaña visible a la apertura, con un tope por envío', function () {
    $e = escenario();
    $recurso = recursoEnUnidad(unidadDeContenidos($e['seccion']));

    actingAs($e['estudiante']);
    $pagina = Livewire::test(Recurso::class, ['seccion' => $e['ulid'], 'recurso' => $recurso['ulid']])
        ->assertSeeHtml('x-on:visibilitychange.document')
        ->call('latido', 30)->call('latido', 12)
        ->call('latido', 600);

    expect(accesos($e))->toHaveCount(1)
        ->and((int) accesos($e)->first()->duration_seconds)->toBe(30 + 12 + 35);
    $pagina->call('latido', 0)->assertHasErrors('segundos');
    expect((int) accesos($e)->first()->duration_seconds)->toBe(77);
});

it('CP-RF13-02 el docente no lleva el contador de tiempo en la página', function () {
    $e = escenario();
    $recurso = recursoEnUnidad(unidadDeContenidos($e['seccion']));

    actingAs($e['docente']);
    Livewire::test(Recurso::class, ['seccion' => $e['ulid'], 'recurso' => $recurso['ulid']])->assertDontSeeHtml('x-on:visibilitychange.document');
});

it('CP-RF13-03 el docente ve el mapa de calor con aperturas y tiempo, y lo colorea por cualquiera de los dos', function () {
    $e = escenario();
    $recurso = recursoEnUnidad(unidadDeContenidos($e['seccion']));
    actingAs($e['estudiante']);
    Livewire::test(Recurso::class, ['seccion' => $e['ulid'], 'recurso' => $recurso['ulid']])->call('latido', 30)->call('latido', 30)->call('latido', 30);

    actingAs($e['docente']);
    Livewire::test(Analitica::class, ['seccion' => $e['ulid']])
        ->assertSee('Lectura guiada: 1 aperturas · 1 min')
        ->assertSee('Tiempo con el recurso a la vista')
        ->assertSeeHtml('aria-pressed="true"')
        ->set('medida', 'tiempo')
        ->assertSee('Tiempo con la pestaña visible, por estudiante y recurso');

    expect(Analitica::duracion(0))->toBe('0 min')
        ->and(Analitica::duracion(40))->toBe('menos de 1 min')
        ->and(Analitica::duracion(3900))->toBe('1 h 5 min');
});

it('CP-RF13-03 solo el docente de la sección ve el mapa de calor', function () {
    $e = escenario();

    actingAs($e['estudiante'])->get(route('secciones.analitica', ['seccion' => $e['ulid']]))->assertForbidden();
    actingAs(docenteAjeno($e))->get(route('secciones.analitica', ['seccion' => $e['ulid']]))->assertForbidden();
});
