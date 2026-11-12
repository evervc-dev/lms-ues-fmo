<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\MatriculaService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Solicitudes de cambio de grupo (RF09, maqueta 37).
 */
#[Layout('layouts::app')]
class SolicitudesCambio extends Component
{
    use PaginaLivewire;

    #[Url(except: 'pending')]
    public string $estado = 'pending';

    public ?int $rechazarId = null;

    public string $motivo = '';

    public function aprobar(int $solicitud, MatriculaService $matricula): void
    {
        try {
            $matricula->aprobarCambio(auth()->user(), $solicitud);
            $this->dispatch('aviso', tipo: 'exito', mensaje: __('Cambio de grupo aprobado.'));
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
        }
    }

    public function confirmarRechazo(int $solicitud): void
    {
        $this->rechazarId = $solicitud;
        $this->motivo = '';
        $this->dispatch('abrir-modal', 'rechazar-cambio');
    }

    public function rechazar(MatriculaService $matricula): void
    {
        $this->validate(['motivo' => 'required|string|min:5|max:300'], [], ['motivo' => 'motivo']);
        $matricula->rechazarCambio(auth()->user(), (int) $this->rechazarId, $this->motivo);
        $this->reset('rechazarId', 'motivo');
        $this->dispatch('cerrar-modal', 'rechazar-cambio');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Solicitud rechazada.'));
    }

    public function render(MatriculaService $matricula): View
    {
        $usuario = auth()->user();

        return $this->pagina('modules.academica.solicitudes-cambio', [
            'solicitudes' => $matricula->solicitudes($usuario, $this->estado),
            'pestanas' => array_values(array_filter([
                ['etiqueta' => __('Estudiantes'), 'url' => route('matricula'), 'activa' => false],
                $usuario->checkPermissionTo('matricula.claves.gestionar') ? ['etiqueta' => __('Claves por asignatura'), 'url' => route('matricula.claves'), 'activa' => false] : null,
                ['etiqueta' => __('Solicitudes de cambio de grupo'), 'url' => route('matricula.solicitudes'), 'activa' => true],
                $usuario->hasAnyPermission(['matricula.plazo.gestionar', 'matricula.prorroga.otorgar']) ? ['etiqueta' => __('Plazos'), 'url' => route('matricula.plazos'), 'activa' => false] : null,
            ])),
        ]);
    }
}
