<?php

namespace App\Providers;

use App\Support\Alcance;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Una instancia por petición: guarda en memoria los departamentos y carreras del usuario.
        $this->app->scoped(Alcance::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
