<?php

namespace App\Modules\Comunicacion\Livewire;

use App\Modules\Comunicacion\Services\ForoService;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Hilo del foro (RF30, maqueta 41): respuestas anidadas y moderación.
 */
#[Layout('layouts::app')]
class HiloForo extends Component
{
    use PaginaDeSeccion;

    #[Locked]
    public int $hiloId;

    public string $mensaje = '';

    public ?int $respondiendoA = null;

    public function mount(int $hilo): void
    {
        $this->hiloId = $hilo;
    }

    public function responderA(?int $mensaje): void
    {
        $this->respondiendoA = $mensaje;
    }

    public function responder(ForoService $foro): void
    {
        $this->validate(['mensaje' => 'required|string|max:5000'], [], ['mensaje' => 'mensaje']);
        $foro->responder(auth()->user(), $this->seccionId(), $this->hiloId, $this->mensaje, $this->respondiendoA);
        $this->reset('mensaje', 'respondiendoA');
    }

    public function moderar(string $accion, ?int $mensaje, ForoService $foro): void
    {
        $foro->moderar(auth()->user(), $this->seccionId(), $this->hiloId, $accion, $mensaje);
    }

    public function render(ForoService $foro): View
    {
        $datos = $foro->hilo(auth()->user(), $this->seccionId(), $this->hiloId);

        return $this->paginaDeSeccion('modules.comunicacion.hilo', $datos + ['modera' => $foro->puedeModerar(auth()->user(), $this->seccionId())]);
    }
}
