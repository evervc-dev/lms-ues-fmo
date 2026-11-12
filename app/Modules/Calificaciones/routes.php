<?php

use App\Modules\Calificaciones\Livewire\Acta;
use App\Modules\Calificaciones\Livewire\ActasPorValidar;
use App\Modules\Calificaciones\Livewire\Cierre;
use App\Modules\Calificaciones\Livewire\MisNotas;
use App\Modules\Calificaciones\Livewire\NotasSeccion;
use App\Modules\Calificaciones\Livewire\PesosSeccion;
use App\Modules\Calificaciones\Livewire\Revision;
use App\Modules\Calificaciones\Livewire\RevisionesSeccion;
use Illuminate\Support\Facades\Route;

// Vistas del registro (config/vistas.php): mismo nombre y URI.
Route::middleware('auth')->group(function (): void {
    Route::livewire('/secciones/{seccion}/notas', NotasSeccion::class)->middleware('permission:calificaciones.ver')->name('secciones.notas');
    Route::livewire('/secciones/{seccion}/notas/pesos', PesosSeccion::class)->middleware('permission:calificaciones.gestionar')->name('secciones.pesos');
    Route::livewire('/secciones/{seccion}/acta', Acta::class)->middleware('permission:actas.publicar')->name('secciones.acta');
    Route::livewire('/secciones/{seccion}/revisiones', RevisionesSeccion::class)->middleware('permission:calificaciones.revision.ver')->name('secciones.revisiones');
    Route::livewire('/secciones/{seccion}/revisiones/{revision}', Revision::class)->middleware('permission:calificaciones.revision.ver')->name('secciones.revisiones.show');
    Route::livewire('/mis-notas', MisNotas::class)->middleware('permission:calificaciones.ver')->name('mis-notas');
    Route::livewire('/actas', ActasPorValidar::class)->middleware('permission:actas.validar')->name('actas');
    Route::livewire('/cierre', Cierre::class)->middleware('permission:cierre.autorizar')->name('cierre');
});
