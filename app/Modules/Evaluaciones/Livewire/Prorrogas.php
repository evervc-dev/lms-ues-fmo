<?php

namespace App\Modules\Evaluaciones\Livewire;

use App\Modules\Academica\Services\SeccionService;
use App\Modules\Evaluaciones\Services\ProrrogaService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Prórrogas de una actividad (RF15, RF35): el docente amplía a uno o varios estudiantes la entrega de la tarea o
 * el plazo para pedir revisión de la nota. Los elige en una lista con buscador por nombre o carné.
 */
class Prorrogas extends Component
{
    #[Locked]
    public string $seccion = '';

    #[Locked]
    public string $actividad = '';

    /** @var array<int, int> Matrículas elegidas en la lista. */
    public array $matriculas = [];

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
        $this->reset('matriculas', 'hasta', 'motivo');
        $this->tipo = (string) array_key_first($prorrogas->deActividad(auth()->user(), $this->seccionId(), $this->actividad)['tipos']);
        $this->resetValidation();
        $this->dispatch('abrir-modal', 'prorrogas-'.$this->actividad);
    }

    public function conceder(ProrrogaService $prorrogas): void
    {
        $this->validate([
            'matriculas' => 'required|array|min:1',
            'matriculas.*' => 'integer',
            'tipo' => 'required|string|in:'.implode(',', array_keys(ProrrogaService::TIPOS)),
            'hasta' => 'required|date',
            'motivo' => 'required|string|max:500',
        ], ['matriculas.required' => __('Elija al menos un estudiante.'), 'matriculas.min' => __('Elija al menos un estudiante.')],
            ['matriculas' => __('estudiantes'), 'tipo' => __('tipo'), 'hasta' => __('fecha'), 'motivo' => __('motivo')]);

        $resultado = $prorrogas->concederVarios(auth()->user(), $this->seccionId(), $this->actividad, $this->matriculas, $this->tipo, $this->hasta, $this->motivo);
        $total = $resultado['nuevas'] + $resultado['reemplazadas'];

        $this->reset('matriculas', 'hasta', 'motivo');
        $this->dispatch('aviso', tipo: 'exito', mensaje: trans_choice('{1} Prórroga guardada. Se avisó al estudiante.|[2,*] Prórroga guardada para :count estudiantes. Se avisó a cada uno.', $total)
            .($resultado['reemplazadas'] > 0 ? ' '.trans_choice('{1} Se reemplazó 1 que ya existía.|[2,*] Se reemplazaron :count que ya existían.', $resultado['reemplazadas']) : ''));
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
