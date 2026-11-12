<?php

namespace App\Providers;

/**
 * Módulo Identidad y acceso (ADR-017): componentes Livewire y rutas.
 */
class IdentidadServiceProvider extends ModuloServiceProvider
{
    protected function modulo(): string
    {
        return 'Identidad';
    }
}
