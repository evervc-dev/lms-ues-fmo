<?php

namespace App\Providers;

/**
 * Módulo Dashboards y reportes (ADR-017): componentes Livewire y rutas.
 */
class ReportesServiceProvider extends ModuloServiceProvider
{
    protected function modulo(): string
    {
        return 'Reportes';
    }
}
