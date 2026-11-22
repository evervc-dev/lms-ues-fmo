<?php

namespace App\Modules\Evaluaciones\Livewire;

use App\Modules\Academica\Services\SeccionService;
use App\Modules\Evaluaciones\Services\ProrrogaService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Prórrogas de una actividad (RF15, RF35): el docente amplía a uno o varios estudiantes la entrega de la tarea o
 * el plazo para pedir revisión de la nota. Es un asistente de tres pasos: los estudiantes (lista con buscador por
 * nombre o carné), el tipo con la fecha y el motivo, y una vista previa para confirmar.
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

    /** Paso del asistente: 1 estudiantes, 2 tipo, fecha y motivo, 3 vista previa. */
    public int $paso = 1;

    /** @return array<int, array{titulo: string, descripcion: string}> */
    public function pasos(): array
    {
        return [
            ['titulo' => __('Estudiantes'), 'descripcion' => __('A quiénes se da la prórroga.')],
            ['titulo' => __('Prórroga'), 'descripcion' => __('Tipo, fecha y motivo.')],
            ['titulo' => __('Confirmar'), 'descripcion' => __('Revise antes de guardar.')],
        ];
    }

    public function mount(string $seccion, string $actividad): void
    {
        $this->seccion = $seccion;
        $this->actividad = $actividad;
    }

    public function abrir(ProrrogaService $prorrogas): void
    {
        $this->reset('matriculas', 'hasta', 'motivo', 'paso');
        $this->tipo = (string) array_key_first($prorrogas->deActividad(auth()->user(), $this->seccionId(), $this->actividad)['tipos']);
        $this->resetValidation();
        $this->dispatch('abrir-modal', 'prorrogas-'.$this->actividad);
    }

    /**
     * Reglas de formato de cada paso; la fecha futura y el motivo mínimo los vuelve a exigir el Service.
     *
     * @return array<string, mixed>
     */
    private function reglas(int $paso): array
    {
        return match ($paso) {
            1 => ['matriculas' => 'required|array|min:1', 'matriculas.*' => 'integer'],
            2 => [
                'tipo' => 'required|string|in:'.implode(',', array_keys(ProrrogaService::TIPOS)),
                'hasta' => 'required|date|after:now',
                'motivo' => 'required|string|min:5|max:500',
            ],
            default => [],
        };
    }

    private function validarPaso(int $paso): void
    {
        $this->validate($this->reglas($paso), [
            'matriculas.required' => __('Elija al menos un estudiante.'),
            'matriculas.min' => __('Elija al menos un estudiante.'),
            'hasta.after' => __('La prórroga debe terminar en una fecha futura.'),
            'motivo.min' => __('Explique el motivo de la prórroga (al menos 5 caracteres).'),
        ], ['matriculas' => __('estudiantes'), 'tipo' => __('tipo'), 'hasta' => __('fecha'), 'motivo' => __('motivo')]);
    }

    public function siguiente(): void
    {
        $this->validarPaso($this->paso);
        $this->paso = min(3, $this->paso + 1);
    }

    public function anterior(): void
    {
        $this->paso = max(1, $this->paso - 1);
    }

    /** Volver a un paso ya recorrido desde el indicador. */
    public function irAlPaso(int $paso): void
    {
        if ($paso >= 1 && $paso < $this->paso) {
            $this->paso = $paso;
        }
    }

    public function conceder(ProrrogaService $prorrogas): void
    {
        // Se revalidan los dos pasos: la confirmación no confía en que se hayan recorrido en orden.
        foreach ([1, 2] as $paso) {
            try {
                $this->validarPaso($paso);
            } catch (ValidationException $e) {
                $this->paso = $paso;

                throw $e;
            }
        }

        $resultado = $prorrogas->concederVarios(auth()->user(), $this->seccionId(), $this->actividad, $this->matriculas, $this->tipo, $this->hasta, $this->motivo);
        $total = $resultado['nuevas'] + $resultado['reemplazadas'];

        $this->reset('matriculas', 'hasta', 'motivo', 'paso');
        $this->dispatch('cerrar-modal', 'prorrogas-'.$this->actividad);
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
