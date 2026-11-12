<?php

namespace App\Modules\Contenidos\Livewire;

use App\Modules\Contenidos\Services\ContenidoService;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Papelera de la sección (RF11): lo eliminado se puede restaurar durante 30 días.
 */
#[Layout('layouts::app')]
class Papelera extends Component
{
    use PaginaDeSeccion;

    public function restaurar(int $recurso, ContenidoService $contenidos): void
    {
        $contenidos->restaurar(auth()->user(), $this->seccionId(), $recurso);
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Recurso restaurado.'));
    }

    public function restaurarUnidad(int $unidad, ContenidoService $contenidos): void
    {
        $contenidos->restaurarUnidad(auth()->user(), $this->seccionId(), $unidad);
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Unidad restaurada con sus materiales.'));
    }

    public function render(ContenidoService $contenidos): View
    {
        return $this->paginaDeSeccion('modules.contenidos.papelera', [
            'eliminados' => $contenidos->papelera(auth()->user(), $this->seccionId()),
            'tipos' => ContenidoService::TIPOS,
            'dias' => ContenidoService::DIAS_PAPELERA,
        ]);
    }
}
