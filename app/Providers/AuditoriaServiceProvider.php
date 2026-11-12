<?php

namespace App\Providers;

/**
 * Módulo Auditoría (ADR-017): componentes Livewire y rutas.
 */
class AuditoriaServiceProvider extends ModuloServiceProvider
{
    protected function modulo(): string
    {
        return 'Auditoria';
    }
}
