<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\CatalogoService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Departamentos, carreras, planes y aulas (RF07, ADR-034): los mantiene el Administrador.
 */
#[Layout('layouts::app')]
class Estructura extends Component
{
    use PaginaLivewire;

    public string $aulaCodigo = '';

    public int $aulaCapacidad = 40;

    public function agregarAula(CatalogoService $catalogo): void
    {
        $this->validate(['aulaCodigo' => 'required|string|max:20', 'aulaCapacidad' => 'required|integer|min:1|max:500'], [], ['aulaCodigo' => 'código', 'aulaCapacidad' => 'capacidad']);
        $catalogo->guardarAula(auth()->user(), $this->aulaCodigo, $this->aulaCapacidad);
        $this->reset('aulaCodigo', 'aulaCapacidad');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Aula agregada.'));
    }

    public function render(CatalogoService $catalogo): View
    {
        return $this->pagina('modules.academica.estructura', $catalogo->estructura(auth()->user()));
    }
}
