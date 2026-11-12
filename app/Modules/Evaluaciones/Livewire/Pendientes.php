<?php

namespace App\Modules\Evaluaciones\Livewire;

use App\Modules\Calificaciones\Services\RevisionService;
use App\Modules\Evaluaciones\Services\ActividadService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Pendientes de calificar (RF15, RF16, RF24, maqueta 18): tareas sin nota, ensayos y solicitudes de revisión (RF35)
 * de las secciones del docente.
 */
#[Layout('layouts::app')]
class Pendientes extends Component
{
    use PaginaLivewire;

    public function render(ActividadService $actividades, RevisionService $revisiones): View
    {
        return $this->pagina('modules.evaluaciones.pendientes', [
            'pendientes' => $actividades->pendientes(auth()->user()),
            'revisiones' => $revisiones->pendientes(auth()->user()),
        ]);
    }
}
