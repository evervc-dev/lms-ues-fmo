<?php

namespace App\Modules\Asistencia\Livewire;

use App\Modules\Asistencia\Services\AsistenciaService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Áreas de asistencia (RF26, maqueta 34): la lista, con activar y desactivar. Crear y editar van en su propia página
 * (AreaFormulario), con el mapa grande (Leaflet, ADR-055).
 */
#[Layout('layouts::app')]
class AreasAsistencia extends Component
{
    use PaginaLivewire;

    public function alternar(int $id, AsistenciaService $asistencia): void
    {
        $asistencia->alternarArea(auth()->user(), $id);
    }

    public function render(AsistenciaService $asistencia): View
    {
        return $this->pagina('modules.asistencia.areas', [
            'areas' => $asistencia->areas(auth()->user()),
        ]);
    }
}
