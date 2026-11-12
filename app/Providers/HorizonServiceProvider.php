<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Gate `viewHorizon`: única excepción a ADR-026 (sin Policies ni Gates).
     * Fuera de local, el panel /horizon requiere el permiso `horizon.ver`.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function (?User $user = null): bool {
            return (bool) $user?->can('horizon.ver');
        });
    }
}
