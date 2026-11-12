<?php

namespace App\Modules\Evaluaciones\Livewire;

use App\Modules\Evaluaciones\Services\CuestionarioService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Intento de cuestionario (RF16, maqueta 10) en el Layout Examen: tiempo del servidor, autoguardado de cada
 * respuesta al cambiarla (y cada 30 s de respaldo), navegación libre o una a una. El contador se resincroniza con
 * el servidor en cada respuesta: si el docente da minutos extra, el estudiante los ve sin recargar (ADR-042).
 */
#[Layout('layouts::exam')]
class Intento extends Component
{
    #[Locked]
    public string $intentoUlid = '';

    public int $actual = 0;

    /** Segundos que quedan según el servidor; el contador del navegador se ajusta a este valor. */
    public int $segundos = 0;

    /** @var array<int|string, int|string|null> respuesta => opción */
    public array $opciones = [];

    /** @var array<int|string, string|null> respuesta => texto */
    public array $textos = [];

    public function mount(string $intento, CuestionarioService $cuestionarios): void
    {
        $this->intentoUlid = $intento;
        foreach ($cuestionarios->intento(auth()->user(), $intento)['preguntas'] as $p) {
            $this->opciones[$p->respuesta_id] = $p->selected_option_id;
            $this->textos[$p->respuesta_id] = $p->answer_text;
        }
    }

    /**
     * Guarda la opción elegida en cuanto cambia (wire:model.live).
     */
    public function updatedOpciones(mixed $valor, string $respuesta, CuestionarioService $cuestionarios): void
    {
        $this->validate(['opciones.*' => 'nullable|integer']);
        $this->exigirEditable((int) $respuesta, $cuestionarios);
        $cuestionarios->guardarRespuesta(auth()->user(), $this->intentoUlid, (int) $respuesta, $valor ? (int) $valor : null, $this->textos[$respuesta] ?? null);
    }

    /**
     * Guarda el texto 2 s después de que el estudiante deja de escribir (wire:model.live.debounce).
     */
    public function updatedTextos(mixed $valor, string $respuesta, CuestionarioService $cuestionarios): void
    {
        $this->validate(['textos.*' => 'nullable|string|max:10000'], [], ['textos.*' => __('respuesta')]);
        $this->exigirEditable((int) $respuesta, $cuestionarios);
        $opcion = $this->opciones[$respuesta] ?? null;
        $cuestionarios->guardarRespuesta(auth()->user(), $this->intentoUlid, (int) $respuesta, $opcion ? (int) $opcion : null, $valor === null ? null : (string) $valor);
    }

    /**
     * El contador del navegador llegó a cero: si el docente dio minutos extra se resincroniza; si no, se envía.
     */
    public function tiempoAgotado(CuestionarioService $cuestionarios): void
    {
        $datos = $cuestionarios->intento(auth()->user(), $this->intentoUlid);
        if ($datos['intento']->status === 'in_progress' && $datos['segundos'] > 0) {
            $this->segundos = $datos['segundos'];

            return;
        }
        $this->terminar($cuestionarios);
    }

    public function guardar(CuestionarioService $cuestionarios): void
    {
        $datos = $cuestionarios->intento(auth()->user(), $this->intentoUlid);
        if ($datos['intento']->status !== 'in_progress') {
            return;
        }
        $lineal = $datos['cuestionario']->navigation === 'linear';
        foreach ($datos['preguntas'] as $p) {
            // Una a una: solo la pregunta actual se puede cambiar.
            if ($lineal && (int) $p->position !== $this->actual) {
                continue;
            }
            if ($p->selected_option_id != ($this->opciones[$p->respuesta_id] ?? null) || $p->answer_text !== ($this->textos[$p->respuesta_id] ?? null)) {
                $cuestionarios->guardarRespuesta(auth()->user(), $this->intentoUlid, (int) $p->respuesta_id,
                    ($this->opciones[$p->respuesta_id] ?? null) ? (int) $this->opciones[$p->respuesta_id] : null, $this->textos[$p->respuesta_id] ?? null);
            }
        }
    }

    public function ir(int $posicion, CuestionarioService $cuestionarios): void
    {
        $this->guardar($cuestionarios);
        $datos = $cuestionarios->intento(auth()->user(), $this->intentoUlid);
        // Una a una solo mientras se responde: un intento terminado se revisa con navegación libre.
        $lineal = $datos['cuestionario']->navigation === 'linear' && $datos['intento']->status === 'in_progress';
        if ($posicion >= 0 && $posicion < $datos['preguntas']->count() && (! $lineal || $posicion > $this->actual)) {
            $this->actual = $posicion;
        }
    }

    public function terminar(CuestionarioService $cuestionarios): void
    {
        // Si el tiempo ya venció, lo pendiente no se guarda, pero el intento igual se envía con lo guardado.
        try {
            $this->guardar($cuestionarios);
        } catch (ValidationException) {
        }
        $cuestionarios->terminar(auth()->user(), $this->intentoUlid);
        $datos = $cuestionarios->intento(auth()->user(), $this->intentoUlid);
        session()->flash('aviso', ['tipo' => 'exito', 'mensaje' => __('Intento enviado.')]);
        $this->redirectRoute('secciones.cuestionarios.show', ['seccion' => $datos['cuestionario']->seccion, 'cuestionario' => $datos['cuestionario']->ulid]);
    }

    /**
     * En modo una a una solo se responde la pregunta actual; las anteriores quedan cerradas.
     */
    private function exigirEditable(int $respuesta, CuestionarioService $cuestionarios): void
    {
        $datos = $cuestionarios->intento(auth()->user(), $this->intentoUlid);
        $pregunta = $datos['preguntas']->firstWhere('respuesta_id', $respuesta) ?? abort(404);
        abort_if($datos['cuestionario']->navigation === 'linear' && (int) $pregunta->position !== $this->actual, 403);
    }

    public function render(CuestionarioService $cuestionarios): View
    {
        $datos = $cuestionarios->intento(auth()->user(), $this->intentoUlid);
        $this->segundos = $datos['segundos'];

        return view('modules.evaluaciones.intento', $datos + ['pregunta' => $datos['preguntas'][$this->actual] ?? null])
            ->title($datos['cuestionario']->title.' · '.$datos['cuestionario']->code);
    }
}
