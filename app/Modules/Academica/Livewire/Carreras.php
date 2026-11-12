<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\CarreraService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Carreras del ámbito del actor (RF07, RF08): el Jefe ve las de su departamento y el Coordinador
 * la que coordina. Solo lectura; el alcance lo aplica CarreraService.
 */
#[Layout('layouts::app')]
class Carreras extends Component
{
    use PaginaLivewire;

    public function render(CarreraService $carreras): View
    {
        return $this->pagina('modules.academica.carreras', [
            'carreras' => $carreras->listar(auth()->user()),
        ]);
    }
}
