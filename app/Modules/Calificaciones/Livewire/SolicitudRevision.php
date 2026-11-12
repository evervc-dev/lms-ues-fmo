<?php

namespace App\Modules\Calificaciones\Livewire;

use App\Modules\Academica\Services\SeccionService;
use App\Modules\Calificaciones\Services\RevisionService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Solicitud de revisión del estudiante (RF35), dentro de la tarea o del cuestionario: marca qué revisar
 * (preguntas, criterios o la nota general), explica por qué y ve la respuesta del docente. No se puede retirar.
 */
class SolicitudRevision extends Component
{
    #[Locked]
    public string $seccion = '';

    #[Locked]
    public string $actividad = '';

    public string $motivo = '';

    /** @var array<int, string> claves marcadas */
    public array $marcados = [];

    /** @var array<string, string> clave => por qué */
    public array $comentarios = [];

    public function mount(string $seccion, string $actividad): void
    {
        $this->seccion = $seccion;
        $this->actividad = $actividad;
    }

    public function abrir(): void
    {
        $this->reset('motivo', 'marcados', 'comentarios');
        $this->resetValidation();
        $this->dispatch('abrir-modal', 'solicitar-revision');
    }

    public function enviar(RevisionService $revisiones): void
    {
        $this->validate([
            'motivo' => 'required|string|max:2000',
            'marcados' => 'required|array|min:1',
            'marcados.*' => 'string|max:40',
            'comentarios.*' => 'nullable|string|max:1000',
        ], [], ['motivo' => __('motivo'), 'marcados' => __('ítems a revisar')]);

        $items = array_intersect_key($this->comentarios, array_flip($this->marcados)) + array_fill_keys($this->marcados, '');
        $revisiones->solicitar(auth()->user(), $this->seccionId(), $this->actividad, $this->motivo, $items);

        $this->dispatch('cerrar-modal', 'solicitar-revision');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Solicitud enviada. Le avisaremos cuando el docente la resuelva.'));
    }

    public function render(RevisionService $revisiones): View
    {
        return view('modules.calificaciones.solicitud-revision', $revisiones->paraEstudiante(auth()->user(), $this->seccionId(), $this->actividad) + [
            'estados' => RevisionService::ESTADOS,
        ]);
    }

    private function seccionId(): int
    {
        return app(SeccionService::class)->idPorUlid($this->seccion);
    }
}
