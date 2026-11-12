<?php

namespace App\Providers;

/**
 * Módulo Actividades y evaluaciones (ADR-017): componentes Livewire y rutas.
 */
class EvaluacionesServiceProvider extends ModuloServiceProvider
{
    protected function modulo(): string
    {
        return 'Evaluaciones';
    }
}
