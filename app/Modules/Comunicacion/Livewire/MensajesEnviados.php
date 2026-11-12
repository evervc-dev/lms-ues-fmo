<?php

namespace App\Modules\Comunicacion\Livewire;

use App\Modules\Comunicacion\Services\MensajeriaService;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Vista de sección › Notas › Mensajes enviados (RF31, ADR-034): lo que el docente ha mandado desde el libro de
 * calificaciones, a quién y si ya se entregó.
 */
#[Layout('layouts::app')]
class MensajesEnviados extends Component
{
    use PaginaDeSeccion;

    public function render(MensajeriaService $mensajeria): View
    {
        return $this->paginaDeSeccion('modules.comunicacion.mensajes-enviados', [
            'mensajes' => $mensajeria->historial(auth()->user(), $this->seccionId()),
            'condiciones' => MensajeriaService::CONDICIONES,
        ]);
    }
}
