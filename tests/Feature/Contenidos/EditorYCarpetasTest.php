<?php

use App\Modules\Contenidos\Livewire\NuevoRecurso;
use App\Modules\Contenidos\Livewire\Recurso;
use App\Modules\Contenidos\Services\ContenidoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
| Plan 7 de RF11: editor de páginas con HTML sanitizado (CP-RF11-07) y carpetas con descarga
| en .zip (CP-RF11-06). Los ayudantes del escenario y de contenidos viven en Pest.php y en
| MaterialesTest.php (contenidos(), unidadDeContenidos(), recursoEnUnidad(), docenteAjeno()).
*/

/** Lo que el editor permite: negrita, cursiva, subrayado, títulos, listas y centrado por clase. */
function htmlDelEditor(): string
{
    return '<h2>Unidad 1</h2>'
        .'<p><strong>Negrita</strong>, <em>cursiva</em> y <u>subrayado</u>.</p>'
        .'<ul><li>Primero</li><li>Segundo</li></ul>'
        .'<p class="ql-align-center">Centrado</p>';
}

/** Lo que el saneador debe quitar: scripts, estilos, iframes, imágenes, tablas, on* y javascript:. */
function htmlPeligroso(): string
{
    return '<p onclick="robar()">Texto con evento</p>'
        .'<script>alert(1)</script>'
        .'<style>body{display:none}</style>'
        .'<img src="x" onerror="alert(1)">'
        .'<iframe src="https://sitio.invalido/panel"></iframe>'
        .'<table><tr><td>Tabla</td></tr></table>'
        .'<p><a href="javascript:alert(1)">Clic aquí</a></p>';
}

/**
 * Lo que nunca debe quedar en la base ni llegar al navegador.
 *
 * @return array<int, string>
 */
function trozosProhibidos(): array
{
    return ['<script', 'onclick', 'onerror', '<iframe', '<img', '<style', '<table', 'javascript:'];
}

/**
 * Archivos de una carpeta, uno por ruta relativa, todos del mismo tamaño en kilobytes.
 *
 * @param  array<int, string>  $rutas
 * @return array<string, mixed>
 */
function datosDeCarpeta(array $rutas, int $kb = 10, string $titulo = 'Guía de laboratorio'): array
{
    return [
        'tipo' => 'folder',
        'titulo' => $titulo,
        'rutas' => $rutas,
        'archivos' => array_map(fn (string $ruta) => UploadedFile::fake()->create(basename($ruta), $kb), $rutas),
    ];
}

/**
 * Entradas del .zip que devolvió la descarga, con su ruta dentro del archivo comprimido.
 *
 * @return array<int, string>
 */
function entradasDelZip(string $contenido): array
{
    $ruta = (string) tempnam(sys_get_temp_dir(), 'carpeta');
    file_put_contents($ruta, $contenido);

    $zip = new ZipArchive;
    expect($zip->open($ruta))->toBeTrue();
    $entradas = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entradas[] = (string) $zip->getNameIndex($i);
    }
    $zip->close();
    unlink($ruta);

    return $entradas;
}

/*
|--------------------------------------------------------------------------
| CP-RF11-07 · Editor de páginas con HTML sanitizado
|--------------------------------------------------------------------------
*/

it('CP-RF11-07 guarda con su formato la página que el docente escribe en el editor', function () {
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    actingAs($e['docente']);

    Livewire::test(NuevoRecurso::class, ['seccion' => $e['ulid'], 'unidad' => $unidad])
        ->set('tipo', 'page')->set('tituloRecurso', 'Lectura 1')->set('texto', htmlDelEditor())
        ->call('agregar')->assertHasNoErrors();

    $guardado = (string) DB::table('resources')->where('title', 'Lectura 1')->value('html_content');
    expect($guardado)
        ->toContain('<strong>Negrita</strong>')
        ->toContain('<em>cursiva</em>')
        ->toContain('<u>subrayado</u>')
        ->toContain('<h2>Unidad 1</h2>')
        ->toContain('<li>Primero</li>')
        ->toContain('ql-align-center');
});

it('CP-RF11-07 quita del HTML de la página los scripts, los iframes, las imágenes y los atributos de evento', function () {
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    actingAs($e['docente']);

    contenidos()->agregarRecurso($e['docente'], $unidad, ['tipo' => 'page', 'titulo' => 'Lectura con trampa', 'texto' => htmlPeligroso()]);

    $recurso = DB::table('resources')->where('title', 'Lectura con trampa')->sole();
    $guardado = (string) $recurso->html_content;
    foreach (trozosProhibidos() as $trozo) {
        expect($guardado)->not->toContain($trozo);
    }
    expect($guardado)->toContain('Texto con evento');

    $vista = Livewire::test(Recurso::class, ['seccion' => $e['ulid'], 'recurso' => $recurso->ulid]);
    foreach (trozosProhibidos() as $trozo) {
        $vista->assertDontSeeHtml($trozo);
    }
});

it('CP-RF11-07 conserva el enlace http del editor y lo abre en una pestaña nueva sin filtrar la sesión', function (string $enlace) {
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);

    contenidos()->agregarRecurso($e['docente'], $unidad, [
        'tipo' => 'page', 'titulo' => 'Lectura con enlace',
        'texto' => '<p><a href="'.$enlace.'">Portal de la UES</a></p>',
    ]);

    $guardado = (string) DB::table('resources')->where('title', 'Lectura con enlace')->value('html_content');
    expect($guardado)
        ->toContain('href="'.$enlace.'"')
        ->toContain('target="_blank"')
        ->toContain('Portal de la UES')
        // Los dos valores de `rel`, en el orden en que los escriba el saneador: para el navegador es lo mismo.
        ->toMatch('~rel="(noopener noreferrer|noreferrer noopener)"~');
})->with(['https://www.ues.edu.sv/', 'http://www.ues.edu.sv/']);

it('CP-RF11-07 muestra la página con el formato renderizado y no como texto plano', function () {
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    contenidos()->agregarRecurso($e['docente'], $unidad, ['tipo' => 'page', 'titulo' => 'Lectura 1', 'texto' => htmlDelEditor()]);
    $pagina = DB::table('resources')->where('title', 'Lectura 1')->sole();

    $html = actingAs($e['estudiante'])->get(route('secciones.recurso', ['seccion' => $e['ulid'], 'recurso' => $pagina->ulid]))->assertOk()->content();

    // El HTML ya saneado llega íntegro a la página: con el texto plano de antes no aparecería ninguna etiqueta.
    expect($html)
        ->toContain((string) $pagina->html_content)
        ->toContain('<strong>Negrita</strong>')
        ->toContain('<ul><li>Primero</li>');
});

it('CP-RF11-07 sanea también el contenido con el que el docente edita una página ya publicada', function () {
    $e = escenario();
    $recurso = recursoEnUnidad(unidadDeContenidos($e['seccion']), ['title' => 'Lectura 1', 'html_content' => '<p>Versión inicial</p>']);
    actingAs($e['docente']);

    contenidos()->editarPagina($e['docente'], $e['seccion'], $recurso['id'], htmlDelEditor().htmlPeligroso());

    $guardado = (string) DB::table('resources')->where('id', $recurso['id'])->value('html_content');
    expect($guardado)->toContain('<strong>Negrita</strong>')->toContain('ql-align-center')->not->toContain('Versión inicial');
    foreach (trozosProhibidos() as $trozo) {
        expect($guardado)->not->toContain($trozo);
    }
});

it('CP-RF11-07 impide crear y editar la página al docente de otra sección y al estudiante', function (string $quien) {
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    $recurso = recursoEnUnidad($unidad, ['title' => 'Lectura 1', 'html_content' => '<p>Versión inicial</p>']);
    $actor = $quien === 'ajeno' ? docenteAjeno($e) : $e['estudiante'];

    expect(fn () => contenidos()->agregarRecurso($actor, $unidad, ['tipo' => 'page', 'titulo' => 'Intrusa', 'texto' => '<p>Mía</p>']))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => contenidos()->editarPagina($actor, $e['seccion'], $recurso['id'], '<p>Editada por el intruso</p>'))
        ->toThrow(AuthorizationException::class);

    expect(DB::table('resources')->where('id', $recurso['id'])->value('html_content'))->toBe('<p>Versión inicial</p>')
        ->and(DB::table('resources')->where('title', 'Intrusa')->exists())->toBeFalse();
})->with(['ajeno', 'estudiante']);

it('CP-RF11-07 rechaza la acción de editar la página cuando la invoca el estudiante desde el componente', function () {
    $e = escenario();
    $recurso = recursoEnUnidad(unidadDeContenidos($e['seccion']), ['title' => 'Lectura 1', 'html_content' => '<p>Versión inicial</p>']);
    actingAs($e['estudiante']);

    // Livewire traduce la AuthorizationException del Service a un 403; la acción no llega a escribir.
    Livewire::test(Recurso::class, ['seccion' => $e['ulid'], 'recurso' => $recurso['ulid']])
        ->set('texto', '<p>Editada por el estudiante</p>')->call('editarPagina')->assertForbidden();

    expect(DB::table('resources')->where('id', $recurso['id'])->value('html_content'))->toBe('<p>Versión inicial</p>');
});

/*
|--------------------------------------------------------------------------
| CP-RF11-06 · Carpetas: estructura y descarga en .zip
|--------------------------------------------------------------------------
*/

it('CP-RF11-06 crea la carpeta con una fila por archivo y conserva su ruta relativa', function () {
    Storage::fake();
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);

    contenidos()->agregarRecurso($e['docente'], $unidad, datosDeCarpeta(['guia/intro.pdf', 'guia/anexos/tabla.xlsx']));

    $recurso = DB::table('resources')->where('unit_id', $unidad)->sole();
    expect($recurso->resource_type)->toBe('folder')
        ->and($recurso->title)->toBe('Guía de laboratorio');

    $archivos = DB::table('resource_files')->where('resource_id', $recurso->id)->get();
    expect($archivos->pluck('path')->all())->toEqualCanonicalizing(['guia/intro.pdf', 'guia/anexos/tabla.xlsx'])
        ->and($archivos->pluck('name')->all())->toEqualCanonicalizing(['intro.pdf', 'tabla.xlsx'])
        ->and($archivos->every(fn ($a) => (int) $a->bytes > 0))->toBeTrue();
});

it('CP-RF11-06 rechaza la ruta que se sale de la carpeta', function (string $ruta) {
    Storage::fake();
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);

    expect(fn () => contenidos()->agregarRecurso($e['docente'], $unidad, datosDeCarpeta([$ruta])))
        ->toThrow(ValidationException::class);

    expect(DB::table('resources')->count())->toBe(0)
        ->and(DB::table('resource_files')->count())->toBe(0);
})->with(['../secreto.pdf', 'guia/../../etc/llaves.pdf', '/etc/llaves.pdf']);

it('CP-RF11-06 rechaza la carpeta que trae un archivo de extensión no permitida', function () {
    Storage::fake();
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);

    expect(fn () => contenidos()->agregarRecurso($e['docente'], $unidad, datosDeCarpeta(['guia/intro.pdf', 'guia/instalador.exe'])))
        ->toThrow(ValidationException::class);

    expect(DB::table('resources')->count())->toBe(0)
        ->and(DB::table('resource_files')->count())->toBe(0);
});

it('CP-RF11-06 rechaza la carpeta que pasa del máximo de archivos', function () {
    Storage::fake();
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    $rutas = array_map(fn (int $i) => "guia/anexo-{$i}.pdf", range(1, ContenidoService::MAXIMO_ARCHIVOS + 1));

    expect(fn () => contenidos()->agregarRecurso($e['docente'], $unidad, datosDeCarpeta($rutas, 1)))
        ->toThrow(ValidationException::class);

    expect(DB::table('resources')->count())->toBe(0);
});

it('CP-RF11-06 rechaza la carpeta que pasa del tamaño total permitido', function () {
    Storage::fake();
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    // Archivos que caben de uno en uno (máximo por archivo) pero que juntos pasan del total.
    $kb = min(ContenidoService::MAXIMO_KB, intdiv(ContenidoService::MAXIMO_TOTAL_KB, 3) + 1);
    $cuantos = intdiv(ContenidoService::MAXIMO_TOTAL_KB, $kb) + 1;
    $rutas = array_map(fn (int $i) => "guia/anexo-{$i}.pdf", range(1, $cuantos));

    expect($cuantos)->toBeLessThanOrEqual(ContenidoService::MAXIMO_ARCHIVOS)
        ->and(fn () => contenidos()->agregarRecurso($e['docente'], $unidad, datosDeCarpeta($rutas, $kb)))
        ->toThrow(ValidationException::class);

    expect(DB::table('resources')->count())->toBe(0);
});

it('CP-RF11-06 el estudiante matriculado descarga la carpeta en un .zip con la estructura original', function () {
    Storage::fake();
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    contenidos()->agregarRecurso($e['docente'], $unidad, datosDeCarpeta(['guia/intro.pdf', 'guia/anexos/tabla.xlsx']));
    $ulid = (string) DB::table('resources')->where('unit_id', $unidad)->value('ulid');

    $respuesta = actingAs($e['estudiante'])->get(route('secciones.recurso.zip', ['seccion' => $e['ulid'], 'recurso' => $ulid]));

    $respuesta->assertOk();
    expect($respuesta->headers->get('content-disposition'))->toContain('attachment')->toContain('.zip')
        ->and(entradasDelZip($respuesta->streamedContent()))->toEqualCanonicalizing(['guia/intro.pdf', 'guia/anexos/tabla.xlsx']);
});

it('CP-RF11-06 rechaza la descarga del .zip al estudiante que no está matriculado en la sección', function () {
    Storage::fake();
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    contenidos()->agregarRecurso($e['docente'], $unidad, datosDeCarpeta(['guia/intro.pdf']));
    $ulid = (string) DB::table('resources')->where('unit_id', $unidad)->value('ulid');
    $ajeno = usuarioCon('estudiante');

    actingAs($ajeno)->get(route('secciones.recurso.zip', ['seccion' => $e['ulid'], 'recurso' => $ulid]))->assertForbidden();
});

it('CP-RF11-06 impide crear la carpeta al docente de otra sección y al estudiante', function (string $quien) {
    Storage::fake();
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    $actor = $quien === 'ajeno' ? docenteAjeno($e) : $e['estudiante'];

    expect(fn () => contenidos()->agregarRecurso($actor, $unidad, datosDeCarpeta(['guia/intro.pdf'])))
        ->toThrow(AuthorizationException::class);

    expect(DB::table('resources')->count())->toBe(0)
        ->and(DB::table('resource_files')->count())->toBe(0);
})->with(['ajeno', 'estudiante']);

it('CP-RF11-06 muestra el árbol de archivos de la carpeta con el botón de descargar el .zip', function () {
    Storage::fake();
    $e = escenario();
    $unidad = unidadDeContenidos($e['seccion']);
    contenidos()->agregarRecurso($e['docente'], $unidad, datosDeCarpeta(['guia/intro.pdf', 'guia/anexos/tabla.xlsx']));
    $ulid = (string) DB::table('resources')->where('unit_id', $unidad)->value('ulid');

    actingAs($e['estudiante']);
    Livewire::test(Recurso::class, ['seccion' => $e['ulid'], 'recurso' => $ulid])
        ->assertSee('intro.pdf')->assertSee('tabla.xlsx')->assertSee('anexos')
        ->assertSeeHtml(route('secciones.recurso.zip', ['seccion' => $e['ulid'], 'recurso' => $ulid]));
});
