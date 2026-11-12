<?php

namespace App\Modules\Asistencia\Livewire;

use App\Modules\Asistencia\Services\AsistenciaService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Mi asistencia (RF22, maquetas 13 y 14): porcentaje por materia con su nivel de alerta.
 */
#[Layout('layouts::app')]
class MiAsistencia extends Component
{
    use PaginaLivewire;

    public function render(AsistenciaService $asistencia): View
    {
        return $this->pagina('modules.asistencia.mi-asistencia', ['materias' => $asistencia->miAsistencia(auth()->user())]);
    }
}
