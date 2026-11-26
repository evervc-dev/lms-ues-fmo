<?php

namespace App\Modules\Calificaciones\Livewire;

use App\Modules\Academica\Services\PlanesService;
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
        $datos = $calificaciones->misNotas(auth()->user());
        // Pensum de cada materia (solo los planes de su carrera), como en Mis materias.
        $codigos = $datos['materias']->pluck('code');

        return $this->pagina('modules.calificaciones.mis-notas', $datos + [
            'minima' => CalificacionService::notaMinima(),
            'planes' => app(PlanesService::class)->delEstudiante((int) auth()->id(), $codigos),
        ]);
    }
}
