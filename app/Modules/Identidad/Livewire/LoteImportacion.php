<?php

namespace App\Modules\Identidad\Livewire;

use App\Modules\Identidad\Services\ImportacionService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Informe de un lote de importación (RF04): creados, duplicados y filas con error.
 */
#[Layout('layouts::app')]
class LoteImportacion extends Component
{
    use PaginaLivewire;

    #[Locked]
    public int $loteId;

    public function mount(int $lote): void
    {
        $this->loteId = $lote;
    }

    public function render(ImportacionService $importacion): View
    {
        return $this->pagina('modules.identidad.lote', $importacion->lote(auth()->user(), $this->loteId));
    }
}
