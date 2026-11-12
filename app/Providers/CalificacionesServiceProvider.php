<?php

namespace App\Providers;

use App\Modules\Calificaciones\Events\NotasPublicadas;
use App\Modules\Calificaciones\Listeners\ReevaluarRiesgoPorNotas;
use Illuminate\Support\Facades\Event;

/**
 * Módulo Calificaciones y progreso (ADR-017): componentes Livewire, rutas y la reevaluación del riesgo académico
 * al publicar notas (RF17).
 */
class CalificacionesServiceProvider extends ModuloServiceProvider
{
    protected function modulo(): string
    {
        return 'Calificaciones';
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(NotasPublicadas::class, ReevaluarRiesgoPorNotas::class);
    }
}
