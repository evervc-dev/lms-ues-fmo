<?php

namespace App\Modules\Calificaciones\Livewire;

use App\Modules\Calificaciones\Services\CalificacionService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Cierre del período (RF34, maqueta 26): el Jefe autoriza el cierre de su departamento cuando todas
 * las actas están validadas.
 */
#[Layout('layouts::app')]
class Cierre extends Component
{
    use PaginaLivewire;

    public function autorizar(CalificacionService $calificaciones): void
    {
        try {
            $calificaciones->autorizarCierre(auth()->user());
            $this->dispatch('aviso', tipo: 'exito', mensaje: __('Cierre autorizado. El Administrador podrá cerrar el período cuando todos los departamentos autoricen.'));
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
        }
    }

    public function render(CalificacionService $calificaciones): View
    {
        return $this->pagina('modules.calificaciones.cierre', $calificaciones->estadoCierre(auth()->user()));
    }
}
