<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\CarreraService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Carreras del ámbito del actor (RF07, RF08): el Jefe ve las de su departamento y el Coordinador
 * la que coordina. El Administrador las ve todas y llega aquí desde Departamentos, filtradas por uno
 * (ADR-083). Solo lectura; el alcance lo aplica CarreraService.
 */
#[Layout('layouts::app')]
class Carreras extends Component
{
    use PaginaLivewire;

    /** Departamento por el que filtra el Administrador; para los demás roles se ignora. */
    #[Url(as: 'departamento', except: null)]
    public ?int $departamento = null;

    public function render(CarreraService $carreras): View
    {
        $departamento = $carreras->departamentoFiltrable(auth()->user(), $this->departamento);

        return $this->pagina('modules.academica.carreras', [
            'carreras' => $carreras->listar(auth()->user(), $departamento === null ? null : (int) $departamento->id),
            'departamentoElegido' => $departamento,
        ], intermedias: $departamento === null ? [] : [['etiqueta' => __('Departamentos'), 'url' => route('departamentos')]],
            miga: $departamento === null ? null : __('Carreras de :d', ['d' => $departamento->code]));
    }
}
