<?php

use App\Modules\Reportes\Livewire\DesempenoDocente;
use App\Modules\Reportes\Livewire\Inicio;
use App\Modules\Reportes\Livewire\Reportes;
use Illuminate\Support\Facades\Route;

// Vistas del registro (config/vistas.php): mismo nombre y URI.
Route::middleware('auth')->group(function (): void {
    Route::livewire('/inicio', Inicio::class)->name('inicio');
    Route::livewire('/desempeno-docente', DesempenoDocente::class)->middleware('permission:desempeno-docente.ver')->name('desempeno-docente');
    Route::livewire('/reportes', Reportes::class)->middleware('permission:reportes.ver')->name('reportes');
});
