<?php

use App\Modules\Contenidos\Jobs\PurgarPapelera;
use App\Modules\Contenidos\Livewire\ContenidosSeccion;
use App\Modules\Contenidos\Livewire\NuevoRecurso;
use App\Modules\Contenidos\Livewire\Papelera;
use App\Modules\Contenidos\Livewire\Recurso;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

// Los ayudantes contenidos(), unidadDeContenidos(), recursoEnUnidad() y docenteAjeno() viven en tests/Pest.php:
// los comparten varios archivos y con `pest --parallel` cada archivo corre en un proceso aparte.

/*
|--------------------------------------------------------------------------
| CP-RF11-02 · Subida de archivos: tipo y tamaño
|--------------------------------------------------------------------------
*/

it('CP-RF11-02 publica el archivo de un tipo permitido y lo guarda en el disco', function (string $extension) {
    Storage::fake();
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    actingAs($e['docente']);

    Livewire::test(NuevoRecurso::class, ['seccion' => $e['ulid'], 'unidad' => $unidad])
        ->set('tipo', 'file')->set('tituloRecurso', 'Guía 1')
        ->set('archivo', UploadedFile::fake()->create("guia.{$extension}", 120))
        ->call('agregar')->assertHasNoErrors();

    $ruta = DB::table('resources')->where('unit_id', $unidad)->where('title', 'Guía 1')->value('file_path');
    expect($ruta)->not->toBeNull()
        ->and(Storage::exists($ruta))->toBeTrue();
})->with(['pdf', 'docx', 'xlsx', 'pptx', 'png', 'txt', 'zip']);

it('CP-RF11-02 rechaza el archivo cuya extensión no está en la lista de tipos aceptados', function (string $extension) {
    Storage::fake();
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    actingAs($e['docente']);

    Livewire::test(NuevoRecurso::class, ['seccion' => $e['ulid'], 'unidad' => $unidad])
        ->set('tipo', 'file')->set('tituloRecurso', 'Programa')
        ->set('archivo', UploadedFile::fake()->create("programa.{$extension}", 10))
        ->call('agregar')->assertHasErrors(['archivo'])->assertSee('Solo puede subir archivos');

    expect(DB::table('resources')->count())->toBe(0);
})->with(['exe', 'php']);

it('CP-RF11-02 rechaza el archivo de más de 50 MB', function () {
    Storage::fake();
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    // La subida temporal de Livewire corta en 12 MB por omisión; se amplía para llegar a la regla del formulario.
    config(['livewire.temporary_file_upload.rules' => ['file', 'max:102400']]);
    actingAs($e['docente']);

    Livewire::test(NuevoRecurso::class, ['seccion' => $e['ulid'], 'unidad' => $unidad])
        ->set('tipo', 'file')->set('tituloRecurso', 'Guía enorme')
        ->set('archivo', UploadedFile::fake()->create('enorme.pdf', 51201))
        ->call('agregar')->assertHasErrors(['archivo'])->assertSee('El archivo no puede pasar de 50 MB.');

    expect(DB::table('resources')->count())->toBe(0);
});

it('CP-RF11-02 acepta el archivo que llega justo al límite de 50 MB', function () {
    Storage::fake();
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    config(['livewire.temporary_file_upload.rules' => ['file', 'max:102400']]);
    actingAs($e['docente']);

    Livewire::test(NuevoRecurso::class, ['seccion' => $e['ulid'], 'unidad' => $unidad])
        ->set('tipo', 'file')->set('tituloRecurso', 'Guía al límite')->call('siguiente')
        ->assertSeeHtml('wire:drop.file')->assertSee('Máximo 50 MB.')
        ->set('archivo', UploadedFile::fake()->create('limite.pdf', 51200))
        ->call('agregar')->assertHasNoErrors();

    expect(DB::table('resources')->where('title', 'Guía al límite')->exists())->toBeTrue();
});

it('CP-RF11-02 impide publicar materiales al docente de otra sección y al estudiante', function (string $quien) {
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    $actor = $quien === 'ajeno' ? docenteAjeno($e) : $e['estudiante'];

    expect(fn () => contenidos()->agregarRecurso($actor, $unidad, ['tipo' => 'link', 'titulo' => 'Intruso', 'url' => 'https://www.ues.edu.sv']))
        ->toThrow(AuthorizationException::class);

    expect(DB::table('resources')->count())->toBe(0);
})->with(['ajeno', 'estudiante']);

/*
|--------------------------------------------------------------------------
| CP-RF11-03 · Modo del PDF: incrustado o descarga
|--------------------------------------------------------------------------
*/

it('CP-RF11-03 deja al docente cambiar el modo del PDF ya publicado y la vista pasa de incrustado a descarga', function () {
    Storage::fake();
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    $recurso = recursoEnUnidad($unidad, ['resource_type' => 'file', 'title' => 'Guía 1', 'file_path' => 'contenidos/guia.pdf', 'pdf_mode' => 'viewer']);
    Storage::put('contenidos/guia.pdf', 'PDF');
    $archivo = route('secciones.recurso.archivo', ['seccion' => $e['ulid'], 'recurso' => $recurso['ulid']]);
    $ver = fn () => actingAs($e['estudiante'])->get(route('secciones.recurso', ['seccion' => $e['ulid'], 'recurso' => $recurso['ulid']]))->assertOk()->content();

    expect($ver())->toMatch('~<(iframe|embed)[^>]*>~')->toContain($archivo);

    actingAs($e['docente']);
    Livewire::test(Recurso::class, ['seccion' => $e['ulid'], 'recurso' => $recurso['ulid']])
        ->set('pdf', 'download')->call('cambiarModoPdf')->assertHasNoErrors();

    expect(DB::table('resources')->where('id', $recurso['id'])->value('pdf_mode'))->toBe('download');
    $html = $ver();
    expect($html)->not->toMatch('~<(iframe|embed)[^>]*>~')
        ->and($html)->toContain($archivo)
        ->and($html)->toContain('Descargar');
});

it('CP-RF11-03 impide al estudiante cambiar el modo del PDF', function () {
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    $recurso = recursoEnUnidad($unidad, ['resource_type' => 'file', 'title' => 'Guía 1', 'file_path' => 'contenidos/guia.pdf', 'pdf_mode' => 'viewer']);

    expect(fn () => contenidos()->cambiarModoPdf($e['estudiante'], $e['seccion'], $recurso['id'], 'download'))->toThrow(AuthorizationException::class);

    expect(DB::table('resources')->where('id', $recurso['id'])->value('pdf_mode'))->toBe('viewer');
});

/*
|--------------------------------------------------------------------------
| CP-RF11-04 · Búsqueda por nombre y por tipo
|--------------------------------------------------------------------------
*/

it('CP-RF11-04 encuentra el material por su título y lo filtra por tipo con la etiqueta en español', function () {
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    recursoEnUnidad($unidad, ['resource_type' => 'file', 'title' => 'Guía de laboratorio', 'file_path' => 'contenidos/guia.pdf', 'pdf_mode' => 'download']);
    recursoEnUnidad($unidad, ['resource_type' => 'video', 'title' => 'Clase grabada', 'url' => 'https://youtu.be/dQw4w9WgXcQ', 'position' => 2]);
    actingAs($e['estudiante']);

    Livewire::test(ContenidosSeccion::class, ['seccion' => $e['ulid']])
        ->assertSee('Todos los tipos')->assertSee('Video')
        ->set('buscar', 'laboratorio')->assertSee('Guía de laboratorio')->assertDontSee('Clase grabada')
        ->set('buscar', 'Video')->assertSee('Clase grabada')->assertDontSee('Guía de laboratorio')
        ->set('buscar', 'Archivo')->assertSee('Guía de laboratorio')->assertDontSee('Clase grabada')
        ->set('buscar', '')->set('tipo', 'video')->assertSee('Clase grabada')->assertDontSee('Guía de laboratorio')
        ->set('tipo', 'file')->assertSee('Guía de laboratorio')->assertDontSee('Clase grabada');
});

/*
|--------------------------------------------------------------------------
| CP-RF11-05 · Unidades en acordeón
|--------------------------------------------------------------------------
*/

it('CP-RF11-05 muestra cada unidad como un acordeón colapsable', function () {
    $e = escenario();
    recursoEnUnidad(unidadDeContenidos($e['seccion'], 'Unidad 1 · Fundamentos'), ['title' => 'Lectura 1']);
    recursoEnUnidad(unidadDeContenidos($e['seccion'], 'Unidad 2 · Recursividad', 2), ['title' => 'Lectura 2']);
    actingAs($e['estudiante']);

    Livewire::test(ContenidosSeccion::class, ['seccion' => $e['ulid']])
        ->assertSee('Unidad 1 · Fundamentos')->assertSee('Unidad 2 · Recursividad')
        ->assertSeeHtml('aria-controls="acordeon-')
        ->assertSeeHtml(':aria-expanded="abierto.toString()"')
        ->assertSeeHtml('x-collapse');
});

/*
|--------------------------------------------------------------------------
| CP-RF11-08 · Papelera de 30 días y purga
|--------------------------------------------------------------------------
*/

it('CP-RF11-08 restaura el recurso que el docente eliminó y lo quita de la papelera', function () {
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    $recurso = recursoEnUnidad($unidad, ['title' => 'Lectura guiada']);
    actingAs($e['docente']);

    contenidos()->eliminar($e['docente'], $e['seccion'], $recurso['id']);
    expect(DB::table('resources')->where('id', $recurso['id'])->value('deleted_at'))->not->toBeNull();

    Livewire::test(Papelera::class, ['seccion' => $e['ulid']])->assertSee('Lectura guiada')
        ->call('restaurar', $recurso['id'])->assertDontSee('Lectura guiada');

    expect(DB::table('resources')->where('id', $recurso['id'])->value('deleted_at'))->toBeNull();
    Livewire::test(ContenidosSeccion::class, ['seccion' => $e['ulid']])->assertSee('Lectura guiada');
});

it('CP-RF11-08 manda la unidad a la papelera con sus recursos y la restaura', function () {
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion'], 'Unidad 1 · Fundamentos');
    $recurso = recursoEnUnidad($unidad, ['title' => 'Lectura guiada']);
    actingAs($e['docente']);

    Livewire::test(ContenidosSeccion::class, ['seccion' => $e['ulid']])
        ->assertSee('Unidad 1 · Fundamentos')->call('eliminarUnidad', $unidad)->assertDontSee('Unidad 1 · Fundamentos');

    expect(DB::table('units')->where('id', $unidad)->value('deleted_at'))->not->toBeNull();
    Livewire::test(Papelera::class, ['seccion' => $e['ulid']])->assertSee('Unidad 1 · Fundamentos');

    contenidos()->restaurarUnidad($e['docente'], $e['seccion'], $unidad);

    expect(DB::table('units')->where('id', $unidad)->value('deleted_at'))->toBeNull()
        ->and(DB::table('resources')->where('id', $recurso['id'])->value('deleted_at'))->toBeNull();
    Livewire::test(ContenidosSeccion::class, ['seccion' => $e['ulid']])->assertSee('Unidad 1 · Fundamentos')->assertSee('Lectura guiada');
});

it('CP-RF11-08 restaura también la unidad eliminada cuando se restaura uno de sus recursos', function () {
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion'], 'Unidad 1 · Fundamentos');
    $recurso = recursoEnUnidad($unidad, ['title' => 'Lectura guiada']);
    actingAs($e['docente']);
    contenidos()->eliminar($e['docente'], $e['seccion'], $recurso['id']);
    contenidos()->eliminarUnidad($e['docente'], $e['seccion'], $unidad);

    contenidos()->restaurar($e['docente'], $e['seccion'], $recurso['id']);

    expect(DB::table('resources')->where('id', $recurso['id'])->value('deleted_at'))->toBeNull()
        ->and(DB::table('units')->where('id', $unidad)->value('deleted_at'))->toBeNull();
    Livewire::test(ContenidosSeccion::class, ['seccion' => $e['ulid']])->assertSee('Unidad 1 · Fundamentos')->assertSee('Lectura guiada');
});

it('CP-RF11-08 rechaza restaurar lo que se eliminó hace más de 30 días', function () {
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    $recurso = recursoEnUnidad($unidad, ['title' => 'Lectura guiada']);
    actingAs($e['docente']);
    contenidos()->eliminar($e['docente'], $e['seccion'], $recurso['id']);

    $this->travel(31)->days();

    expect(fn () => contenidos()->restaurar($e['docente'], $e['seccion'], $recurso['id']))
        ->toThrow(ValidationException::class);
    expect(DB::table('resources')->where('id', $recurso['id'])->value('deleted_at'))->not->toBeNull();
    Livewire::test(Papelera::class, ['seccion' => $e['ulid']])->assertDontSee('Lectura guiada');
});

it('CP-RF11-08 el job PurgarPapelera borra con su archivo lo eliminado hace más de 30 días y conserva lo reciente', function () {
    Storage::fake();
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    $viejo = recursoEnUnidad($unidad, ['resource_type' => 'file', 'title' => 'Guía antigua', 'file_path' => 'contenidos/antigua.pdf', 'pdf_mode' => 'download']);
    $reciente = recursoEnUnidad($unidad, ['resource_type' => 'file', 'title' => 'Guía reciente', 'file_path' => 'contenidos/reciente.pdf', 'pdf_mode' => 'download', 'position' => 2]);
    Storage::put('contenidos/antigua.pdf', 'PDF antiguo');
    Storage::put('contenidos/reciente.pdf', 'PDF reciente');
    actingAs($e['docente']);
    contenidos()->eliminar($e['docente'], $e['seccion'], $viejo['id']);

    $this->travel(31)->days();
    contenidos()->eliminar($e['docente'], $e['seccion'], $reciente['id']);
    PurgarPapelera::dispatchSync();
    PurgarPapelera::dispatchSync();

    // RF11: «el docente puede restaurarlos durante 30 días; después se borran definitivamente».
    // Decisión del 24/10/2026: la purga borra el archivo y vacía la fila, pero la conserva, porque el ER manda
    // que los logs de acceso nunca se borren y son los que referencian al recurso.
    expect(DB::table('resources')->where('id', $viejo['id'])->value('file_path'))->toBeNull()
        ->and(Storage::exists('contenidos/antigua.pdf'))->toBeFalse()
        ->and(DB::table('resources')->where('id', $reciente['id'])->value('file_path'))->toBe('contenidos/reciente.pdf')
        ->and(Storage::exists('contenidos/reciente.pdf'))->toBeTrue();
});

it('CP-RF11-08 el job PurgarPapelera purga los recursos de la unidad vencida y conserva las filas', function () {
    Storage::fake();
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    $recurso = recursoEnUnidad($unidad, ['resource_type' => 'file', 'title' => 'Guía antigua', 'file_path' => 'contenidos/antigua.pdf', 'pdf_mode' => 'download']);
    Storage::put('contenidos/antigua.pdf', 'PDF antiguo');
    actingAs($e['docente']);
    contenidos()->eliminarUnidad($e['docente'], $e['seccion'], $unidad);

    $this->travel(31)->days();
    PurgarPapelera::dispatchSync();

    // La unidad vencida y lo que quedaba dentro se borran definitivamente (RF11).
    expect(DB::table('units')->where('id', $unidad)->whereNotNull('deleted_at')->exists())->toBeTrue()
        ->and(DB::table('resources')->where('id', $recurso['id'])->value('file_path'))->toBeNull()
        ->and(Storage::exists('contenidos/antigua.pdf'))->toBeFalse();
});

it('CP-RF11-08 impide al estudiante y al docente de otra sección eliminar o restaurar materiales', function (string $quien) {
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    $recurso = recursoEnUnidad($unidad, ['title' => 'Lectura guiada']);
    $actor = $quien === 'ajeno' ? docenteAjeno($e) : $e['estudiante'];

    expect(fn () => contenidos()->eliminar($actor, $e['seccion'], $recurso['id']))->toThrow(AuthorizationException::class)
        ->and(fn () => contenidos()->eliminarUnidad($actor, $e['seccion'], $unidad))->toThrow(AuthorizationException::class)
        ->and(fn () => contenidos()->restaurar($actor, $e['seccion'], $recurso['id']))->toThrow(AuthorizationException::class)
        ->and(fn () => contenidos()->restaurarUnidad($actor, $e['seccion'], $unidad))->toThrow(AuthorizationException::class);

    expect(DB::table('resources')->where('id', $recurso['id'])->value('deleted_at'))->toBeNull()
        ->and(DB::table('units')->where('id', $unidad)->value('deleted_at'))->toBeNull();
})->with(['ajeno', 'estudiante']);

/*
|--------------------------------------------------------------------------
| CP-RF12 · Video integrado
|--------------------------------------------------------------------------
*/

it('CP-RF12-01 muestra incrustado el reproductor del enlace de YouTube o de Drive', function (string $url, string $esperado) {
    $e = escenario();
    $recurso = recursoEnUnidad(unidadDeContenidos($e['seccion']), ['resource_type' => 'video', 'title' => 'Clase grabada', 'url' => $url]);

    $html = actingAs($e['estudiante'])->get(route('secciones.recurso', ['seccion' => $e['ulid'], 'recurso' => $recurso['ulid']]))->assertOk()->content();

    expect($html)->toContain('<iframe')->toContain('src="'.$esperado.'"');
})->with([
    ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    ['https://youtu.be/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    ['https://www.youtube.com/embed/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    ['https://www.youtube.com/shorts/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    ['https://m.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    ['https://www.youtube.com/watch?list=PLsabcd1234&index=2&v=dQw4w9WgXcQ&t=30s', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    ['https://drive.google.com/file/d/1aBcDeFgHiJkLmNoP/view?usp=sharing', 'https://drive.google.com/file/d/1aBcDeFgHiJkLmNoP/preview'],
]);

it('CP-RF12-01 el docente agrega el video desde el formulario y el estudiante lo ve incrustado', function () {
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    actingAs($e['docente']);

    Livewire::test(NuevoRecurso::class, ['seccion' => $e['ulid'], 'unidad' => $unidad])
        ->set('tipo', 'video')->set('tituloRecurso', 'Clase grabada')->set('url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        ->call('agregar')->assertHasNoErrors();

    $ulid = DB::table('resources')->where('title', 'Clase grabada')->value('ulid');
    $html = actingAs($e['estudiante'])->get(route('secciones.recurso', ['seccion' => $e['ulid'], 'recurso' => $ulid]))->assertOk()->content();

    expect($html)->toContain('src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"');
});

it('CP-RF12-02 muestra como enlace externo el video que no se puede incrustar', function () {
    $e = escenario();
    $recurso = recursoEnUnidad(unidadDeContenidos($e['seccion']), ['resource_type' => 'video', 'title' => 'Charla en Vimeo', 'url' => 'https://vimeo.com/76979871']);

    $html = actingAs($e['estudiante'])->get(route('secciones.recurso', ['seccion' => $e['ulid'], 'recurso' => $recurso['ulid']]))->assertOk()->content();

    expect($html)->not->toContain('<iframe')
        ->and($html)->toContain('https://vimeo.com/76979871')
        ->and($html)->toContain('target="_blank"')
        ->and($html)->toContain('rel="noopener"');
});

it('CP-RF12-02 rechaza el enlace que no usa http o https', function (string $url) {
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    actingAs($e['docente']);

    Livewire::test(NuevoRecurso::class, ['seccion' => $e['ulid'], 'unidad' => $unidad])
        ->set('tipo', 'video')->set('tituloRecurso', 'Enlace raro')->set('url', $url)
        ->call('agregar')->assertHasErrors(['url']);

    expect(DB::table('resources')->count())->toBe(0);
})->with(['javascript:alert(1)', 'ftp://ues.edu.sv/clase.mp4']);
