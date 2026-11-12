<?php

namespace App\Providers;

use App\Modules\Calificaciones\Events\NotasPublicadas;
use App\Modules\Comunicacion\Events\RespuestaPublicada;
use App\Modules\Contenidos\Listeners\CompletarPorEntrega;
use App\Modules\Contenidos\Listeners\CompletarPorIntentoCalificado;
use App\Modules\Contenidos\Listeners\CompletarPorMensajeDelForo;
use App\Modules\Contenidos\Listeners\CompletarPorNotasPublicadas;
use App\Modules\Evaluaciones\Events\EntregaRealizada;
use App\Modules\Evaluaciones\Events\IntentoCalificado;
use Illuminate\Support\Facades\Event;

/**
 * Módulo Contenidos (ADR-017): componentes Livewire, rutas y la evaluación de las reglas de finalización
 * automática cuando ocurre el evento que las cumple (RF18, ADR-067, patrón Observer de RNF07).
 */
class ContenidosServiceProvider extends ModuloServiceProvider
{
    protected function modulo(): string
    {
        return 'Contenidos';
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(NotasPublicadas::class, CompletarPorNotasPublicadas::class);
        Event::listen(IntentoCalificado::class, CompletarPorIntentoCalificado::class);
        Event::listen(EntregaRealizada::class, CompletarPorEntrega::class);
        Event::listen(RespuestaPublicada::class, CompletarPorMensajeDelForo::class);
    }
}
