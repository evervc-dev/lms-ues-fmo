<?php

namespace App\Modules\Evaluaciones\Livewire;

use App\Modules\Evaluaciones\Services\CuestionarioService;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Cuestionario (RF16, maqueta 33): el estudiante inicia o retoma su intento; el docente ve los intentos
 * y califica los ensayos.
 */
#[Layout('layouts::app')]
class Cuestionario extends Component
{
    use PaginaDeSeccion;

    #[Locked]
    public string $cuestionarioUlid = '';

    /** @var array<int|string, float|string> respuesta => puntos */
    public array $ensayos = [];

    public function mount(string $cuestionario): void
    {
        $this->cuestionarioUlid = $cuestionario;
    }

    public function comenzar(CuestionarioService $cuestionarios): void
    {
        try {
            $intento = $cuestionarios->iniciar(auth()->user(), $this->seccionId(), $this->cuestionarioUlid);
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());

            return;
        }
        $this->redirectRoute('intentos.show', ['intento' => $intento]);
    }

    public function calificarEnsayo(int $respuesta, CuestionarioService $cuestionarios): void
    {
        try {
            $cuestionarios->calificarEnsayo(auth()->user(), $respuesta, (float) ($this->ensayos[$respuesta] ?? -1));
            $this->dispatch('aviso', tipo: 'exito', mensaje: __('Ensayo calificado.'));
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
        }
    }

    public function render(CuestionarioService $cuestionarios): View
    {
        $datos = $cuestionarios->cuestionario(auth()->user(), $this->seccionId(), $this->cuestionarioUlid);
        $ensayosPendientes = $datos['gestiona'] ? DB::table('attempt_answers as r')->join('quiz_attempts as q', 'q.id', '=', 'r.attempt_id')
            ->join('questions as p', 'p.id', '=', 'r.question_id')->where('q.assessment_id', $datos['cuestionario']->id)
            ->where('p.question_type', 'essay')->whereNull('r.score')->where('q.status', 'pending_grading')
            ->get(['r.id', 'r.answer_text', 'p.statement']) : collect();

        return $this->paginaDeSeccion('modules.evaluaciones.cuestionario', $datos + ['ensayosPendientes' => $ensayosPendientes]);
    }
}
