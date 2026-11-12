<?php

namespace App\Modules\Evaluaciones\Livewire;

use App\Modules\Evaluaciones\Services\CuestionarioService;
use App\Support\Navegacion\FormularioPorPasos;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Nueva pregunta de un banco (RF16, ADR-009). Va en página y no en un modal: el formulario cambia entero
 * según el tipo y las opciones crecen con los datos (docs/13 Layout/Layout base.md).
 *
 * Al guardar vuelve al banco, que es donde se ve la pregunta agregada.
 */
#[Layout('layouts::app')]
class NuevaPregunta extends Component
{
    use FormularioPorPasos;
    use PaginaLivewire;

    #[Locked]
    public int $bancoId;

    public string $tipo = 'multiple_choice';

    public string $enunciado = '';

    public string $tema = '';

    public string $dificultad = 'medium';

    /** @var array<int, string> */
    public array $opciones = ['', '', '', ''];

    public int $correcta = 0;

    public string $aceptadas = '';

    public string $justificacion = '';

    public function mount(int $banco, CuestionarioService $cuestionarios): void
    {
        $this->bancoId = $banco;
        // Comprueba el permiso y el alcance sobre el banco antes de mostrar el formulario.
        $cuestionarios->banco(auth()->user(), $banco);
    }

    /**
     * El enunciado con su clasificación, la respuesta correcta según el tipo y el resumen para confirmar.
     *
     * @return array<int, array{titulo: string, descripcion?: string, reglas?: array<string, mixed>, atributos?: array<string, string>}>
     */
    public function pasos(): array
    {
        return [
            [
                'titulo' => __('Enunciado'),
                'descripcion' => __('Tipo, tema y pregunta.'),
                'reglas' => ['tipo' => 'required|in:multiple_choice,true_false,short_answer,essay', 'enunciado' => 'required|string|max:2000'],
                'atributos' => ['enunciado' => __('enunciado'), 'tipo' => __('tipo')],
            ],
            [
                'titulo' => __('Respuesta'),
                'descripcion' => __('Lo que se tomará por correcto.'),
            ],
            [
                'titulo' => __('Revisar y agregar'),
                'descripcion' => __('Confirme antes de guardarla en el banco.'),
            ],
        ];
    }

    public function agregar(CuestionarioService $cuestionarios): void
    {
        $this->validarTodosLosPasos();

        $cuestionarios->agregarPregunta(auth()->user(), $this->bancoId, [
            'tipo' => $this->tipo, 'enunciado' => $this->enunciado, 'tema' => $this->tema ?: null, 'dificultad' => $this->dificultad,
            'opciones' => $this->opciones, 'correcta' => $this->correcta, 'aceptadas' => $this->aceptadas,
            'justificacion' => $this->justificacion ?: null,
        ]);

        session()->flash('aviso', ['tipo' => 'exito', 'mensaje' => __('Pregunta agregada.')]);
        $this->redirectRoute('bancos-de-preguntas.show', ['banco' => $this->bancoId], navigate: true);
    }

    public function render(CuestionarioService $cuestionarios): View
    {
        $datos = $cuestionarios->banco(auth()->user(), $this->bancoId);

        return $this->pagina('modules.evaluaciones.nueva-pregunta', [
            'banco' => $datos['banco'],
            'tipos' => CuestionarioService::TIPOS,
        ], intermedias: [['etiqueta' => $datos['banco']->name, 'url' => route('bancos-de-preguntas.show', ['banco' => $this->bancoId])]]);
    }
}
