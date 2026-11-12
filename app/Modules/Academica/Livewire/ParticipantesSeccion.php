<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\SeccionService;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Vista de sección › Participantes (RF36, ADR-056): por aquí llegan Docentes y Estudiantes a los perfiles, sin un
 * buscador global de personas.
 */
#[Layout('layouts::app')]
class ParticipantesSeccion extends Component
{
    use PaginaDeSeccion;

    public function render(SeccionService $secciones): View
    {
        return $this->paginaDeSeccion('modules.academica.participantes', $secciones->participantes(auth()->user(), $this->seccionId()));
    }
}
