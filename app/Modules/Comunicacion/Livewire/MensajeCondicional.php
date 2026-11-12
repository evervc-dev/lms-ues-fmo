<?php

namespace App\Modules\Comunicacion\Livewire;

use App\Modules\Calificaciones\Services\CalificacionService;
use App\Modules\Comunicacion\Services\MensajeriaService;
use App\Support\Navegacion\FormularioPorPasos;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Mensajear a estudiantes que… (RF31): desde Notas, el docente elige una actividad y una condición, revisa a
 * quién le llegaría (puede quitar a alguien) y escribe el mensaje. Por pasos, con el resumen al final.
 *
 * La lista de destinatarios se calcula siempre en el servidor: lo que el navegador manda son solo los que el
 * docente quitó, y MensajeriaService::enviar() la vuelve a calcular al enviar.
 */
#[Layout('layouts::app')]
class MensajeCondicional extends Component
{
    use FormularioPorPasos;
    use PaginaDeSeccion;

    public ?int $item = null;

    public string $condicion = MensajeriaService::NO_ENTREGARON;

    public ?float $umbral = null;

    /** @var array<int, int> ids de usuario que el docente quitó de la lista */
    public array $excluidos = [];

    public string $asunto = '';

    public string $texto = '';

    public function mount(MensajeriaService $mensajeria): void
    {
        abort_unless($mensajeria->puede(auth()->user(), $this->seccionId()), 403);

        $this->umbral = CalificacionService::notaMinima();
    }

    /**
     * Condición, destinatarios y mensaje con su resumen.
     *
     * @return array<int, array{titulo: string, descripcion?: string, reglas?: array<string, mixed>, atributos?: array<string, string>}>
     */
    public function pasos(): array
    {
        return [
            [
                'titulo' => __('Condición'),
                'descripcion' => __('Qué actividad y a quiénes.'),
                'reglas' => [
                    'item' => 'required|integer',
                    'condicion' => ['required', Rule::in(array_keys(MensajeriaService::CONDICIONES))],
                    'umbral' => ['nullable', 'required_if:condicion,'.MensajeriaService::NOTA_MENOR, 'numeric', 'between:0,10'],
                ],
                'atributos' => ['item' => __('actividad'), 'condicion' => __('condición'), 'umbral' => __('nota')],
            ],
            [
                'titulo' => __('Destinatarios'),
                'descripcion' => __('Quite a quien no deba recibirlo.'),
                'reglas' => ['excluidos' => 'array'],
            ],
            [
                'titulo' => __('Mensaje y revisión'),
                'descripcion' => __('Escriba y confirme el envío.'),
                'reglas' => ['asunto' => 'required|string|max:150', 'texto' => 'required|string|max:5000'],
                'atributos' => ['asunto' => __('asunto'), 'texto' => __('mensaje')],
            ],
        ];
    }

    /**
     * Quienes cumplen la condición, antes de que el docente quite a nadie.
     *
     * @return Collection<int, object{id: int, nombre: string, correo: string, nota: ?float}>
     */
    #[Computed]
    public function candidatos(): Collection
    {
        if ($this->item === null) {
            return collect();
        }

        try {
            return app(MensajeriaService::class)->destinatarios(auth()->user(), $this->seccionId(), $this->item, $this->condicion, $this->umbral);
        } catch (ValidationException) {
            return collect();
        }
    }

    /**
     * Los que de verdad recibirían el mensaje: los candidatos menos los que el docente quitó.
     *
     * @return Collection<int, object{id: int, nombre: string, correo: string, nota: ?float}>
     */
    #[Computed]
    public function destinatarios(): Collection
    {
        return $this->candidatos->reject(fn (object $d) => in_array($d->id, array_map('intval', $this->excluidos), true))->values();
    }

    /**
     * Quita o vuelve a poner a un estudiante en la lista. Solo cuentan los que de verdad son candidatos: un id que
     * no lo sea se ignora.
     */
    public function alternar(int $usuario): void
    {
        if (! $this->candidatos->contains('id', $usuario)) {
            return;
        }

        $excluidos = array_map('intval', $this->excluidos);
        $this->excluidos = in_array($usuario, $excluidos, true)
            ? array_values(array_diff($excluidos, [$usuario]))
            : [...$excluidos, $usuario];
    }

    /** Cambiar la condición cambia la lista: lo que se quitó antes ya no tiene sentido. */
    public function updatedItem(): void
    {
        $this->excluidos = [];
    }

    public function updatedCondicion(): void
    {
        $this->excluidos = [];
    }

    public function updatedUmbral(): void
    {
        $this->excluidos = [];
    }

    protected function validarPaso(int $paso): void
    {
        if ($paso === 1) {
            // Falla aquí, con su mensaje, si la actividad no recibe entregas o no es de esta sección.
            app(MensajeriaService::class)->destinatarios(auth()->user(), $this->seccionId(), (int) $this->item, $this->condicion, $this->umbral);
        }

        if ($paso === 2 && $this->destinatarios->isEmpty()) {
            throw ValidationException::withMessages(['excluidos' => __('Debe quedar al menos un destinatario.')]);
        }
    }

    public function enviar(MensajeriaService $mensajeria): void
    {
        $this->validarTodosLosPasos();

        $mensajeria->enviar(auth()->user(), $this->seccionId(), [
            'item' => (int) $this->item, 'condicion' => $this->condicion, 'umbral' => $this->umbral,
            'destinatarios' => $this->destinatarios->pluck('id')->all(), 'asunto' => $this->asunto, 'texto' => $this->texto,
        ]);

        session()->flash('aviso', ['tipo' => 'exito', 'mensaje' => trans_choice('{1} El mensaje va en camino a 1 estudiante.|[2,*] El mensaje va en camino a :count estudiantes.', $this->destinatarios->count())]);
        $this->redirectRoute('secciones.mensajes', ['seccion' => $this->seccion], navigate: true);
    }

    public function render(MensajeriaService $mensajeria): View
    {
        return $this->paginaDeSeccion('modules.comunicacion.mensaje-condicional', [
            'actividades' => $mensajeria->actividades(auth()->user(), $this->seccionId()),
            'condiciones' => MensajeriaService::CONDICIONES,
        ]);
    }
}
