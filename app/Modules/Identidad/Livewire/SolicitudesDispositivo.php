<?php

namespace App\Modules\Identidad\Livewire;

use App\Modules\Identidad\Services\DispositivoService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Solicitudes de cambio de teléfono (RF27, maqueta 30): bandeja de Soporte.
 */
#[Layout('layouts::app')]
class SolicitudesDispositivo extends Component
{
    use PaginaLivewire;

    #[Url(except: 'pending')]
    public string $estado = 'pending';

    public ?int $seleccionada = null;

    public string $motivoRechazo = '';

    #[Computed]
    public function solicitudes(): Collection
    {
        return app(DispositivoService::class)->solicitudes(auth()->user(), $this->estado);
    }

    #[Computed]
    public function actual(): ?object
    {
        return $this->solicitudes->firstWhere('id', $this->seleccionada) ?? $this->solicitudes->first();
    }

    public function seleccionar(int $solicitud): void
    {
        $this->seleccionada = $solicitud;
        $this->motivoRechazo = '';
    }

    public function aprobar(DispositivoService $dispositivos): void
    {
        $this->resolver(fn () => $dispositivos->aprobar(auth()->user(), (int) $this->actual?->id), __('Solicitud aprobada: el teléfono anterior quedó revocado.'));
    }

    public function rechazar(DispositivoService $dispositivos): void
    {
        $this->validate(['motivoRechazo' => 'required|string|min:5|max:300'], [], ['motivoRechazo' => 'motivo']);
        $this->resolver(fn () => $dispositivos->rechazar(auth()->user(), (int) $this->actual?->id, $this->motivoRechazo), __('Solicitud rechazada.'));
    }

    private function resolver(callable $accion, string $mensaje): void
    {
        try {
            $accion();
            $this->reset('seleccionada', 'motivoRechazo');
            unset($this->solicitudes, $this->actual);
            $this->dispatch('aviso', tipo: 'exito', mensaje: $mensaje);
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
        }
    }

    public function render(DispositivoService $dispositivos): View
    {
        return $this->pagina('modules.identidad.solicitudes-dispositivo', [
            'historial' => $this->actual ? $dispositivos->historial($this->actual->student_id) : null,
        ]);
    }
}
