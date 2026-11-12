<?php

namespace App\Providers;

use App\Support\Navegacion\Vistas;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Esqueleto navegable (ADR-035): registra como «En construcción» las vistas de config/vistas.php
 * que ningún módulo implementó. Va al final de bootstrap/providers.php.
 */
class NavegacionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->isProduction()) {
            Route::middleware(['web', 'auth'])->get('/dev/maquetas/{archivo}', function (string $archivo) {
                $permitidas = collect(Vistas::todas())->pluck('maqueta')->filter()->unique();
                abort_unless($permitidas->contains($archivo), 404);
                $ruta = base_path('docs/13 Layout/Maquetas/'.$archivo.'.png');
                abort_unless(is_file($ruta), 404);

                return response()->file($ruta, ['Cache-Control' => 'private, max-age=3600']);
            })->name('dev.maqueta');
        }

        $this->app->booted(fn () => Vistas::registrarPendientes());
    }
}
