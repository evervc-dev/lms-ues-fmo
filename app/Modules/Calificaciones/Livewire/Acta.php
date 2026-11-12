<?php

namespace App\Modules\Calificaciones\Livewire;

use App\Modules\Calificaciones\Services\CalificacionService;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Acta de la sección (RF34, maqueta 48): el docente revisa el resumen y la publica.
 */
#[Layout('layouts::app')]
class Acta extends Component
{
    use PaginaDeSeccion;

    public function publicar(CalificacionService $calificaciones): void
    {
        try {
            $calificaciones->publicarActa(auth()->user(), $this->seccionId());
            $this->dispatch('aviso', tipo: 'exito', mensaje: __('Acta publicada. El Coordinador la validará.'));
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
        }
    }

    public function render(CalificacionService $calificaciones): View
    {
        return $this->paginaDeSeccion('modules.calificaciones.acta', $calificaciones->acta(auth()->user(), $this->seccionId()));
    }
}
