<?php

use App\Modules\Comunicacion\Livewire\Ayuda;
use App\Modules\Comunicacion\Livewire\Foro;
use App\Modules\Comunicacion\Livewire\HiloForo;
use App\Modules\Comunicacion\Livewire\MensajeCondicional;
use App\Modules\Comunicacion\Livewire\MensajesEnviados;
use App\Modules\Comunicacion\Livewire\Notificaciones;
use Illuminate\Support\Facades\Route;

// Vistas del registro (config/vistas.php): mismo nombre y URI.
Route::middleware('auth')->group(function (): void {
    Route::livewire('/notificaciones', Notificaciones::class)->name('notificaciones');
    Route::livewire('/ayuda/{manual?}', Ayuda::class)->name('ayuda');
    Route::livewire('/secciones/{seccion}/notas/mensajes/crear', MensajeCondicional::class)->middleware('permission:mensajes.condicionales.enviar')->name('secciones.mensajes.crear');
    Route::livewire('/secciones/{seccion}/notas/mensajes', MensajesEnviados::class)->middleware('permission:mensajes.condicionales.enviar')->name('secciones.mensajes');
    Route::livewire('/secciones/{seccion}/foro', Foro::class)->middleware('permission:foros.participar')->name('secciones.foro');
    Route::livewire('/secciones/{seccion}/foro/{hilo}', HiloForo::class)->middleware('permission:foros.participar')->whereNumber('hilo')->name('secciones.foro.hilo');
});
