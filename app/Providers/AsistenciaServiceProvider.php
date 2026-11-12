<?php

namespace App\Providers;

use App\Modules\Asistencia\Events\ClaseCancelada;
use App\Modules\Asistencia\Listeners\CancelarEventosPorCancelacion;
use App\Modules\Asistencia\Listeners\ReevaluarAlertasPorCancelacion;
use App\Modules\Asistencia\Services\CalendarConexionService;
use App\Modules\Asistencia\Services\CalendarDesactivado;
use App\Modules\Asistencia\Services\CalendarGateway;
use App\Modules\Asistencia\Services\CalendarGoogle;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;

/**
 * Módulo Asistencia (ADR-017): componentes Livewire, rutas, la reevaluación de alertas y el borrado de los eventos de
 * Calendar tras una cancelación (RF22, RF32) y
 * Google Calendar (RF21, ADR-018), que se activa con `GOOGLE_CALENDAR_ENABLED` y el cliente OAuth configurado.
 */
class AsistenciaServiceProvider extends ModuloServiceProvider
{
    protected function modulo(): string
    {
        return 'Asistencia';
    }

    public function register(): void
    {
        parent::register();

        $this->app->bind(CalendarGateway::class, fn (Application $app) => $app->make(CalendarConexionService::class)->habilitada()
            ? $app->make(CalendarGoogle::class)
            : new CalendarDesactivado);
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(ClaseCancelada::class, ReevaluarAlertasPorCancelacion::class);
        Event::listen(ClaseCancelada::class, CancelarEventosPorCancelacion::class);
    }
}
