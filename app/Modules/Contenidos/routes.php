<?php

use App\Modules\Academica\Services\SeccionService;
use App\Modules\Contenidos\Livewire\Analitica;
use App\Modules\Contenidos\Livewire\ContenidosSeccion;
use App\Modules\Contenidos\Livewire\Importar;
use App\Modules\Contenidos\Livewire\NuevoRecurso;
use App\Modules\Contenidos\Livewire\Papelera;
use App\Modules\Contenidos\Livewire\Progreso;
use App\Modules\Contenidos\Livewire\Recurso;
use App\Modules\Contenidos\Services\ContenidoService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\ZipStream;

// Vistas del registro (config/vistas.php): mismo nombre y URI.
Route::middleware('auth')->group(function (): void {
    Route::livewire('/secciones/{seccion}', ContenidosSeccion::class)->middleware('permission:contenidos.ver')->name('secciones.contenidos');
    Route::livewire('/secciones/{seccion}/recursos/{recurso}', Recurso::class)->middleware('permission:contenidos.ver')->name('secciones.recurso');
    Route::livewire('/secciones/{seccion}/unidades/{unidad}/recursos/crear', NuevoRecurso::class)->middleware('permission:contenidos.gestionar')->whereNumber('unidad')->name('secciones.recursos.crear');
    Route::livewire('/secciones/{seccion}/papelera', Papelera::class)->middleware('permission:contenidos.gestionar')->name('secciones.papelera');
    Route::livewire('/secciones/{seccion}/importar', Importar::class)->middleware('permission:contenidos.reutilizar')->name('secciones.importar');
    Route::livewire('/secciones/{seccion}/analitica', Analitica::class)->middleware('permission:contenidos.analitica.ver')->name('secciones.analitica');
    Route::livewire('/secciones/{seccion}/progreso', Progreso::class)->middleware('permission:progreso.ver')->name('secciones.progreso');

    // Archivo de un recurso: se sirve desde el disco privado tras verificar el acceso a la sección. El PDF que se ve
    // incrustado es parte de la apertura de la página; lo demás es una descarga (RF13).
    Route::get('/secciones/{seccion}/recursos/{recurso}/archivo', function (string $seccion, string $recurso, SeccionService $secciones, ContenidoService $contenidos) {
        $seccionId = $secciones->idPorUlid($seccion);
        $datos = $contenidos->recurso(auth()->user(), $seccionId, $recurso);
        $ruta = (string) $datos['recurso']->file_path;
        abort_unless($ruta !== '' && Storage::exists($ruta), 404, __('El archivo no está disponible.'));

        if ($datos['recurso']->pdf_mode === 'viewer' && str_ends_with(strtolower($ruta), '.pdf')) {
            return Storage::response($ruta);
        }
        $contenidos->registrarDescarga(auth()->user(), $seccionId, (int) $datos['recurso']->id);

        return Storage::download($ruta, basename($ruta));
    })->middleware('permission:contenidos.ver')->name('secciones.recurso.archivo');

    // Carpeta completa en .zip (RF11): se arma al vuelo con ZipStream, sin archivo temporal. El permiso y el
    // alcance sobre la sección los exige el Service; aquí solo se transmite.
    Route::get('/secciones/{seccion}/recursos/{recurso}/zip', function (string $seccion, string $recurso, SeccionService $secciones, ContenidoService $contenidos): StreamedResponse {
        $seccionId = $secciones->idPorUlid($seccion);
        $datos = $contenidos->carpeta(auth()->user(), $seccionId, $recurso);
        $base = (string) $datos['recurso']->file_path;
        abort_if($base === '' || $datos['archivos']->isEmpty(), 404, __('La carpeta no tiene archivos para descargar.'));
        $contenidos->registrarDescarga(auth()->user(), $seccionId, (int) $datos['recurso']->id);

        return response()->streamDownload(function () use ($base, $datos): void {
            $zip = new ZipStream(sendHttpHeaders: false);
            foreach ($datos['archivos'] as $archivo) {
                $flujo = Storage::readStream($base.'/'.$archivo->path);
                if ($flujo !== null) {
                    $zip->addFileFromStream(fileName: (string) $archivo->path, stream: $flujo);
                }
            }
            $zip->finish();
        }, ContenidoService::nombreZip((string) $datos['recurso']->title), ['Content-Type' => 'application/zip']);
    })->middleware('permission:contenidos.ver')->name('secciones.recurso.zip');
});
