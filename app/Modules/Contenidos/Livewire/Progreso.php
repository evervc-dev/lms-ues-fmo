<?php

namespace App\Modules\Contenidos\Livewire;

use App\Modules\Contenidos\Services\ProgresoService;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Vista de sección › Progreso (RF18): avance por unidad de cada estudiante. El docente ve su sección, el
 * Coordinador las de su carrera y el estudiante solo su fila; el alcance lo aplica el Service.
 */
#[Layout('layouts::app')]
class Progreso extends Component
{
    use PaginaDeSeccion;

    public function render(ProgresoService $progreso): View
    {
        return $this->paginaDeSeccion('modules.contenidos.progreso', $progreso->avance(auth()->user(), $this->seccionId()));
    }
}
