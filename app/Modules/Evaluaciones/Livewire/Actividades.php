<?php

namespace App\Modules\Evaluaciones\Livewire;

use App\Modules\Evaluaciones\Services\ActividadService;
use App\Support\Alcance;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Vista de sección › Actividades (RF15, RF16): tareas y cuestionarios por cómputo. El docente programa aquí las
 * evaluaciones copiadas de un período anterior, que llegan sin fechas ni cómputo (RF14, ADR-066).
 */
#[Layout('layouts::app')]
class Actividades extends Component
{
    use PaginaDeSeccion;

    #[Locked]
    public ?string $programando = null;

    #[Locked]
    public bool $conGracia = false;

    public string $abre = '';

    public string $cierra = '';

    public ?int $componente = null;

    public float $peso = 20;

    public string $gracia = '';

    public function abrirProgramar(string $ulid, ActividadService $actividades): void
    {
        $evaluacion = $actividades->sinProgramar(auth()->user(), $this->seccionId())->firstWhere('ulid', $ulid) ?? abort(404);
        $this->reset('componente', 'peso', 'gracia');
        $this->programando = $ulid;
        $this->conGracia = $evaluacion->assessment_type === 'assignment' && $evaluacion->late_policy !== 'strict';
        $this->abre = now()->format('Y-m-d\TH:i');
        $this->cierra = now()->addWeek()->setTime(23, 59)->format('Y-m-d\TH:i');
        $this->resetValidation();
        $this->dispatch('abrir-modal', 'programar');
    }

    public function programar(ActividadService $actividades): void
    {
        $this->validate([
            'abre' => 'required|date', 'cierra' => 'required|date', 'componente' => 'required|integer', 'peso' => 'required|numeric|min:1|max:100',
            'gracia' => $this->conGracia ? 'required|date' : 'nullable',
        ], [], ['componente' => 'cómputo', 'gracia' => 'fecha de gracia']);
        $actividades->programar(auth()->user(), $this->seccionId(), (string) $this->programando, [
            'abre' => $this->abre, 'cierra' => $this->cierra, 'componente' => (int) $this->componente, 'peso' => $this->peso, 'gracia' => $this->gracia ?: null,
        ]);
        $this->reset('programando');
        $this->dispatch('cerrar-modal', 'programar');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Evaluación programada: ya la ven los estudiantes desde su apertura.'));
    }

    public function render(ActividadService $actividades): View
    {
        $usuario = auth()->user();
        $seccion = $this->seccionId();

        return $this->paginaDeSeccion('modules.evaluaciones.actividades', [
            'computos' => $actividades->porComputo($usuario, $seccion),
            'sinProgramar' => $actividades->sinProgramar($usuario, $seccion),
            'gestiona' => app(Alcance::class)->puedeEnSeccion($usuario, 'tareas.gestionar', $seccion),
            'servicio' => $actividades,
        ]);
    }
}
