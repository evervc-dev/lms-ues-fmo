<?php

use App\Modules\Academica\Livewire\PeriodoDetalle;
use App\Modules\Auditoria\Livewire\Auditoria;
use App\Modules\Calificaciones\Livewire\NotasSeccion;
use App\Modules\Calificaciones\Services\CalificacionService;
use App\Modules\Reportes\Livewire\DesempenoDocente;
use App\Modules\Reportes\Livewire\Reportes;
use App\Support\DbContext;
use App\Support\Exportacion;
use App\Support\HojaCalculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Exportación a Excel y PDF (ADR-049)
|--------------------------------------------------------------------------
|
| El libro de calificaciones (RF17), el panel de desempeño (RF33), el período
| cerrado (RF06) y los reportes (RF25) salen en los tres formatos. Auditoría y
| Diagnóstico no ofrecen PDF a propósito: llegan a 10 000 filas.
|
*/

/** El cuerpo de la descarga que devolvió el componente: Livewire lo guarda en base64. */
function descargado(Testable $prueba): string
{
    return base64_decode((string) data_get($prueba->effects, 'download.content'));
}

/** Guarda un contenido en un archivo temporal con su extensión y devuelve la ruta. */
function enArchivo(string $contenido, string $extension): string
{
    $ruta = tempnam(sys_get_temp_dir(), 'export').'.'.$extension;
    file_put_contents($ruta, $contenido);

    return $ruta;
}

it('CP-RF17-08 CP-RF17-12 exporta el libro de calificaciones a Excel con una columna por actividad', function () {
    $e = escenario();
    actingAs($e['docente']);

    $libro = app(CalificacionService::class)->libroExportable($e['docente'], $e['seccion']);
    expect($libro['columnas'][0])->toBe('Carné')
        ->and($libro['columnas'][1])->toBe('Estudiante')
        ->and(end($libro['columnas']))->toBe('Nota final')
        ->and($libro['filas'])->not->toBeEmpty();

    $respuesta = Livewire::test(NotasSeccion::class, ['seccion' => $e['ulid']])->call('exportar', 'xlsx');
    $respuesta->assertFileDownloaded();

    // El .xlsx se vuelve a leer con el mismo lector que usa la importación (ADR-048).
    $ruta = enArchivo(descargado($respuesta), 'xlsx');
    $hoja = HojaCalculo::leer($ruta, 'xlsx');
    unlink($ruta);

    // Antes de los encabezados van las filas de cabecera comunes (facultad, reporte y fecha).
    expect($hoja[Exportacion::FILAS_DE_CABECERA])->toBe(array_map('strval', $libro['columnas']))
        ->and($hoja)->toHaveCount(Exportacion::FILAS_DE_CABECERA + count($libro['filas']) + 1);
});

it('CP-RF17-08 CP-RF17-12 exporta el libro de calificaciones a PDF', function () {
    $e = escenario();
    actingAs($e['docente']);

    $respuesta = Livewire::test(NotasSeccion::class, ['seccion' => $e['ulid']])->call('exportar', 'pdf');
    $respuesta->assertFileDownloaded();

    expect(descargado($respuesta))->toStartWith('%PDF-');
});

it('CP-RF17-12 niega la exportación del libro al estudiante matriculado', function () {
    $e = escenario();
    actingAs($e['estudiante']);

    expect(fn () => app(CalificacionService::class)->libroExportable($e['estudiante'], $e['seccion']))
        ->toThrow(HttpException::class);
});

it('CP-RF33-05 exporta el desempeño docente a Excel y a PDF', function () {
    $e = escenario();
    actingAs($e['jefe']);

    Livewire::test(DesempenoDocente::class)->call('exportar', 'xlsx')->assertFileDownloaded('desempeno-docente-'.now()->format('Y-m-d').'.xlsx');
    Livewire::test(DesempenoDocente::class)->call('exportar', 'pdf')->assertFileDownloaded('desempeno-docente-'.now()->format('Y-m-d').'.pdf');
    Livewire::test(DesempenoDocente::class)->call('exportar', 'csv')->assertFileDownloaded('desempeno-docente-'.now()->format('Y-m-d').'.csv');
});

it('CP-RF33-04 el Excel del desempeño docente lleva el retraso, la revisión y las entregas sin publicar', function () {
    $e = escenario();
    DbContext::system(fn () => DB::table('attendance_sessions')->insert(['ulid' => (string) str()->ulid(), 'section_id' => $e['seccion'], 'session_date' => '2026-08-10',
        'status' => 'closed', 'opened_at' => '2026-08-10 07:20:00', 'closed_at' => '2026-08-10 08:00:00', 'opened_by' => $e['docente']->id, 'created_at' => now(), 'updated_at' => now()]));
    actingAs($e['jefe']);

    $ruta = enArchivo(descargado(Livewire::test(DesempenoDocente::class)->call('exportar', 'xlsx')), 'xlsx');
    $hoja = HojaCalculo::leer($ruta, 'xlsx');
    unlink($ruta);

    $fila = collect($hoja)->first(fn (array $f) => ($f[0] ?? null) === $e['docente']->last_name.', '.$e['docente']->first_name);
    expect($hoja[Exportacion::FILAS_DE_CABECERA])->toBe(['Docente', 'Departamento', 'Secciones', 'Retraso (min)', 'A tiempo %', 'Recursos', 'Revisión (h)', 'Sin publicar', 'Notas publicadas %'])
        ->and((float) $fila[3])->toEqual(20.0)
        ->and((float) $fila[4])->toEqual(0.0)
        ->and((int) $fila[7])->toBe(0);
});

it('CP-RF06-06 exporta el período con sus cierres por departamento', function () {
    $e = escenario();
    actingAs($e['administrador']);

    $respuesta = Livewire::test(PeriodoDetalle::class, ['periodo' => $e['periodo']])->call('exportar', 'xlsx');
    $respuesta->assertFileDownloaded();

    $ruta = enArchivo(descargado($respuesta), 'xlsx');
    $hoja = HojaCalculo::leer($ruta, 'xlsx');
    unlink($ruta);

    expect($hoja[Exportacion::FILAS_DE_CABECERA])->toBe(['Código', 'Departamento', 'Secciones', 'Matrículas', 'Cierre autorizado', 'Autorizó'])
        ->and(collect($hoja)->skip(Exportacion::FILAS_DE_CABECERA + 1)->pluck(0))->toContain(DB::table('departments')->where('id', $e['dia'])->value('code'));

    Livewire::test(PeriodoDetalle::class, ['periodo' => $e['periodo']])->call('exportar', 'pdf')->assertFileDownloaded();
});

it('CP-RF25-03 exporta el reporte en los tres formatos', function () {
    $e = escenario();
    actingAs($e['administrador']);

    foreach (['xlsx', 'pdf', 'csv'] as $formato) {
        Livewire::test(Reportes::class)->call('exportar', $formato)
            ->assertFileDownloaded('reporte-asistencia-'.now()->format('Y-m-d').'.'.$formato);
    }
});

it('CP-RF05-08 exporta la auditoría a Excel, sin ofrecer PDF', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(Auditoria::class)
        ->call('exportar', 'xlsx')->assertFileDownloaded('auditoria-'.now()->format('Y-m-d').'.xlsx');

    // La pantalla no ofrece el PDF: la exportación llega a 10 000 filas.
    actingAs($e['administrador'])->get(route('auditoria'))->assertOk()->assertDontSee("exportar('pdf')", false);
});

it('deja el nombre del archivo sin acentos ni signos', function () {
    expect(Exportacion::nombre('PRN115 · 01 — Programación I'))->toBe('prn115-01-programacion-i')
        ->and(Exportacion::nombre('¿¡!'))->toBe('exportacion');
});

it('escribe un .xlsx que el lector del propio sistema vuelve a leer', function () {
    $respuesta = Exportacion::xlsx('prueba.xlsx', ['Uno', 'Dos'], [['a', 1], ['b', null]], titulo: 'Hoja: prueba/1');

    ob_start();
    $respuesta->sendContent();
    $ruta = enArchivo((string) ob_get_clean(), 'xlsx');
    $hoja = HojaCalculo::leer($ruta, 'xlsx');
    unlink($ruta);

    // El nombre de la hoja pierde los signos que Excel no admite; el contenido vuelve entero, tras la cabecera.
    expect($hoja[Exportacion::FILAS_DE_CABECERA])->toBe(['Uno', 'Dos'])
        ->and($hoja[Exportacion::FILAS_DE_CABECERA + 1])->toBe(['a', '1']);
});

it('CP-RF25-03 todo Excel abre con la facultad, el reporte con sus filtros y quién lo generó', function () {
    $e = escenario();
    actingAs($e['administrador']);

    $respuesta = Livewire::withQueryParams(['carrera' => $e['isi']])->test(Reportes::class)->call('exportar', 'xlsx');
    $ruta = enArchivo(descargado($respuesta), 'xlsx');
    $hoja = HojaCalculo::leer($ruta, 'xlsx');
    unlink($ruta);

    expect($hoja[0])->toBe(['Universidad de El Salvador · Facultad Multidisciplinaria Oriental'])
        ->and($hoja[1])->toBe(['Asistencia por sección · Período 2026-II · Carrera ISI'])
        ->and($hoja[2][0])->toBe('Generado el '.now()->format('d/m/Y H:i').' por '.$e['administrador']->fullName())
        ->and($hoja[Exportacion::FILAS_DE_CABECERA])->toBe(['Sección', 'Asignatura', 'Carrera', 'Estudiantes', 'Clases', 'Asistencia %']);
});

it('CP-RF25-03 los filtros del Excel se escriben en palabras y con fechas DD/MM/AAAA', function () {
    expect(Exportacion::filtros(['Desde' => '2026-10-01', 'Hasta' => '', 'Acción' => 'Cambio', 'Búsqueda' => null]))->toBe('Desde: 01/10/2026 · Acción: Cambio')
        ->and(Exportacion::filtros(['Desde' => '', 'Búsqueda' => null]))->toBe('Sin filtros');
});

it('H-01 un PDF de tabla con más de 500 filas lleva las primeras y una nota que manda al Excel o al CSV', function () {
    $filas = collect(range(1, 822))->map(fn (int $i) => ["Estudiante {$i}", 'ISI', 'Riesgo de reprobar']);

    $datos = Exportacion::recortarParaPdf(['titulo' => 'Estudiantes en riesgo', 'filas' => $filas]);

    expect($datos['filas'])->toHaveCount(Exportacion::FILAS_PDF)
        ->and($datos['filas'][0][0])->toBe('Estudiante 1')
        ->and($datos['nota'])->toContain('500 de 822')->toContain('Excel')
        ->and(Exportacion::recortarParaPdf(['filas' => $filas->take(500)]))->not->toHaveKey('nota')
        ->and(Exportacion::recortarParaPdf(['libro' => []]))->toBe(['libro' => []]);
});

it('H-01 exporta a PDF una tabla de cientos de filas sin agotar la memoria', function () {
    actingAs(usuarioCon('administrador'));
    $filas = collect(range(1, 900))->map(fn (int $i) => ["Apellido {$i}, Nombre", 'AB'.str_pad((string) $i, 5, '0'), 'ISI', 'PRN115 · 01', 'Riesgo de reprobar', '19/10/2026'])->all();

    $respuesta = Exportacion::pdf('riesgo.pdf', 'exportaciones.tabla', ['titulo' => 'Estudiantes en riesgo', 'subtitulo' => null,
        'columnas' => ['Estudiante', 'Carné', 'Carrera', 'Sección', 'Alerta', 'Desde'], 'filas' => $filas], 'landscape');
    ob_start();
    $respuesta->sendContent();
    $pdf = (string) ob_get_clean();

    expect($pdf)->toStartWith('%PDF-')->and(substr_count($pdf, '/Type /Page'))->toBeLessThan(30);
});
