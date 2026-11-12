<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * Base de los ServiceProvider de módulo (ADR-017, ADR-035): registra cada componente de
 * app/Modules/<Modulo>/Livewire con el alias `<modulo>.<nombre-kebab>` y carga
 * app/Modules/<Modulo>/routes.php. Las rutas reales reemplazan a las provisionales del
 * registro de vistas si usan el mismo nombre.
 */
abstract class ModuloServiceProvider extends ServiceProvider
{
    /** Carpeta del módulo en app/Modules (p. ej. «Academica»). */
    abstract protected function modulo(): string;

    public function boot(): void
    {
        $modulo = $this->modulo();

        foreach (glob(app_path("Modules/{$modulo}/Livewire/*.php")) ?: [] as $archivo) {
            $clase = basename($archivo, '.php');
            Livewire::component(Str::lower($modulo).'.'.Str::kebab($clase), "App\\Modules\\{$modulo}\\Livewire\\{$clase}");
        }

        $rutas = app_path("Modules/{$modulo}/routes.php");
        if (is_file($rutas)) {
            Route::middleware('web')->group($rutas);
        }
    }
}
