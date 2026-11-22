<?php

namespace App\Modules\Evaluaciones\Livewire;

use App\Modules\Evaluaciones\Services\CuestionarioService;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Vista detallada de las respuestas de un intento para el docente (RF16).
 * Muestra el examen completo del estudiante en pantalla completa, listando todas las preguntas
 * con las opciones elegidas, respuestas enviadas, aciertos y puntaje real obtenido.
 */
#[Layout('layouts::app')]
class RevisionIntentoDocente extends Component
{
    use PaginaDeSeccion;

    #[Locked]
    public string $cuestionarioUlid = '';

    #[Locked]
    public string $intentoUlid = '';

    public function mount(string $cuestionario, string $intento): void
    {
        $this->cuestionarioUlid = $cuestionario;
        $this->intentoUlid = $intento;
    }

    public function render(CuestionarioService $cuestionarios): View
    {
        $datos = $cuestionarios->intentoParaDocente(
            auth()->user(),
            $this->seccionId(),
            $this->cuestionarioUlid,
            $this->intentoUlid
        );

        $estados = [
            'in_progress' => ['ambar', __('En curso')],
            'submitted' => ['azul', __('Enviado')],
            'pending_grading' => ['ambar', __('Ensayos por calificar')],
            'graded' => ['verde', __('Calificado')],
        ];

        return $this->paginaDeSeccion('modules.evaluaciones.revision-intento', $datos + [
            'estados' => $estados,
            'cuestionarioUlid' => $this->cuestionarioUlid,
        ]);
    }
}
