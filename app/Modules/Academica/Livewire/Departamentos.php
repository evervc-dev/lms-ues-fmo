<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\CarreraService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Departamentos de la universidad para el Administrador (RF07, ADR-083): primer nivel de la navegación
 * Departamentos → Carreras → Carrera. Solo lectura; CarreraService exige el alcance global.
 */
#[Layout('layouts::app')]
class Departamentos extends Component
{
    use PaginaLivewire;

    public function render(CarreraService $carreras): View
    {
        return $this->pagina('modules.academica.departamentos', [
            'departamentos' => $carreras->departamentos(auth()->user()),
        ]);
    }
}
