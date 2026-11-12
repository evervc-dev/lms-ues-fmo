<?php

namespace App\Modules\Evaluaciones\Livewire;

use App\Modules\Academica\Services\SeccionService;
use App\Modules\Evaluaciones\Services\ProrrogaService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Prórrogas de una actividad (RF15, RF35): el docente amplía a un estudiante la entrega de la tarea o el plazo
 * para pedir revisión de la nota.
 */
class Prorrogas extends Component
{
    #[Locked]
    public string $seccion = '';

    #[Locked]
    public string $actividad = '';

    public ?int $matricula = null;

    public string $tipo = '';

    public string $hasta = '';

    public string $motivo = '';

    public function mount(string $seccion, string $actividad): void
    {
        $this->seccion = $seccion;
        $this->actividad = $actividad;
    }

    public function abrir(ProrrogaService $prorrogas): void
    {
        $this->reset('matricula', 'hasta', 'motivo');
        $this->tipo = (string) array_key_first($prorrogas->deActividad(auth()->user(), $this->seccionId(), $this->actividad)['tipos']);
        $this->resetValidation();
        $this->dispatch('abrir-modal', 'prorrogas-'.$this->actividad);
    }

    public function conceder(ProrrogaService $prorrogas): void
    {
        $this->validate([
            'matricula' => 'required|integer',
            'tipo' => 'required|string|in:'.implode(',', array_keys(ProrrogaService::TIPOS)),
            'hasta' => 'required|date',
            'motivo' => 'required|string|max:500',
        ], [], ['matricula' => __('estudiante'), 'tipo' => __('tipo'), 'hasta' => __('fecha'), 'motivo' => __('motivo')]);

        $prorrogas->conceder(auth()->user(), $this->seccionId(), $this->actividad, (int) $this->matricula, $this->tipo, $this->hasta, $this->motivo);

        $this->reset('matricula', 'hasta', 'motivo');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Prórroga guardada. Se avisó al estudiante.'));
    }

    public function render(ProrrogaService $prorrogas): View
    {
        return view('modules.evaluaciones.prorrogas', $prorrogas->deActividad(auth()->user(), $this->seccionId(), $this->actividad));
    }

    private function seccionId(): int
    {
        return app(SeccionService::class)->idPorUlid($this->seccion);
    }
}
