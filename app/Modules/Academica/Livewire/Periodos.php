<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\PeriodoService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Períodos (RF06): listado, creación y avance de etapas.
 */
#[Layout('layouts::app')]
class Periodos extends Component
{
    use PaginaLivewire;

    /** Ver los períodos archivados (ADR-092) en vez de los activos. */
    #[Url(as: 'archivados', except: false)]
    public bool $archivados = false;

    #[Validate('required|string|max:20', as: 'nombre')]
    public string $nombre = '';

    #[Validate('required|date', as: 'inicio')]
    public string $inicio = '';

    #[Validate('required|date', as: 'fin')]
    public string $fin = '';

    public function crear(PeriodoService $periodos): void
    {
        $this->validate();
        $id = $periodos->crear(auth()->user(), $this->nombre, $this->inicio, $this->fin);
        $this->reset('nombre', 'inicio', 'fin');
        $this->dispatch('cerrar-modal', 'nuevo-periodo');
        $this->redirectRoute('periodos.show', ['periodo' => $id], navigate: true);
    }

    /**
     * Archiva o desarchiva un período cerrado (ADR-092).
     */
    public function archivar(int $periodo, bool $archivar, PeriodoService $periodos): void
    {
        try {
            $periodos->archivar(auth()->user(), $periodo, $archivar);
            $this->dispatch('aviso', tipo: 'exito', mensaje: $archivar ? __('Período archivado.') : __('Período desarchivado.'));
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
        }
    }

    public function render(PeriodoService $periodos): View
    {
        return $this->pagina('modules.academica.periodos', [
            'periodos' => $periodos->listar(auth()->user(), $this->archivados),
            'etapas' => PeriodoService::ETAPAS,
            'estados' => PeriodoService::ESTADOS,
        ]);
    }
}
