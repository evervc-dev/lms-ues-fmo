<?php

namespace App\Modules\Calificaciones\Livewire;

use App\Modules\Calificaciones\Services\CalificacionService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Mis notas (RF17, maquetas 11 y 12): boleta del período y historial de períodos cerrados.
 */
#[Layout('layouts::app')]
class MisNotas extends Component
{
    use PaginaLivewire;

    public function render(CalificacionService $calificaciones): View
    {
        return $this->pagina('modules.calificaciones.mis-notas', $calificaciones->misNotas(auth()->user()) + ['minima' => CalificacionService::notaMinima()]);
    }
}
