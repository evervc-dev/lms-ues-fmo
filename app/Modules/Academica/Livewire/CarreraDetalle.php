<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\CarreraService;
use App\Support\Alcance;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Detalle de una carrera (RF07, RF08): coordinador, docentes y materias del plan con sus grupos
 * teóricos y horarios del período en curso. Solo lectura; el alcance lo aplica CarreraService.
 */
#[Layout('layouts::app')]
class CarreraDetalle extends Component
{
    use PaginaLivewire;

    #[Locked]
    public int $carreraId;

    public function mount(int $carrera): void
    {
        $this->carreraId = $carrera;
    }

    public function render(CarreraService $carreras): View
    {
        $detalle = $carreras->detalle(auth()->user(), $this->carreraId);

        // El Administrador llega desde un departamento: la miga intermedia lo devuelve a las carreras de ese departamento.
        $intermedias = app(Alcance::class)->de(auth()->user(), 'carreras.ver') !== 'G' ? [] : [[
            'etiqueta' => $detalle['carrera']->departamentoCodigo,
            'url' => route('carreras', ['departamento' => $detalle['carrera']->department_id]),
        ]];

        return $this->pagina('modules.academica.carrera-detalle', $detalle, intermedias: $intermedias,
            titulo: __('Carrera :nombre', ['nombre' => $detalle['carrera']->name]));
    }
}
