<?php

namespace App\Modules\Asistencia\Livewire;

use App\Modules\Asistencia\Services\AsistenciaService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Clases canceladas (RF32, maqueta 43): aprobar o rechazar solicitudes y cancelar clases de la carrera o la facultad.
 */
#[Layout('layouts::app')]
class ClasesCanceladas extends Component
{
    use PaginaLivewire;

    #[Url(except: 'requested')]
    public string $estado = 'requested';

    public string $fecha = '';

    public string $motivo = '';

    public function resolver(int $id, bool $aprobar, AsistenciaService $asistencia): void
    {
        $asistencia->resolverCancelacion(auth()->user(), $id, $aprobar);
        $this->dispatch('aviso', tipo: 'exito', mensaje: $aprobar ? __('Cancelación aprobada.') : __('Solicitud rechazada.'));
    }

    public function cancelar(AsistenciaService $asistencia): void
    {
        $this->validate(['fecha' => 'required|date|after_or_equal:today', 'motivo' => 'required|string|min:5|max:300'], [], ['fecha' => 'fecha', 'motivo' => 'motivo']);
        $asistencia->cancelarDirecto(auth()->user(), $this->fecha, $this->motivo);
        $this->reset('fecha', 'motivo');
        $this->dispatch('cerrar-modal', 'cancelar-directo');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Clases canceladas para esa fecha.'));
    }

    public function render(AsistenciaService $asistencia): View
    {
        return $this->pagina('modules.asistencia.clases-canceladas', [
            'cancelaciones' => $asistencia->cancelaciones(auth()->user(), $this->estado),
            'alcanceFacultad' => auth()->user()->hasRole('administrador'),
        ]);
    }
}
