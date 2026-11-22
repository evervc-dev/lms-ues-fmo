<?php

namespace App\Modules\Evaluaciones\Livewire;

use App\Modules\Calificaciones\Services\CalificacionService;
use App\Modules\Evaluaciones\Services\ActividadService;
use App\Modules\Evaluaciones\Services\EquipoService;
use App\Modules\Evaluaciones\Services\RubricaService;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Entregas de una tarea (RF15): calificar con rúbrica o nota directa, anónimo si aplica. En una tarea grupal,
 * el docente arma los equipos aquí y puede ajustar la nota de un integrante. La rúbrica de la tarea se agrega o se
 * cambia aquí hasta la primera calificación (ADR-065).
 */
#[Layout('layouts::app')]
class Entregas extends Component
{
    use PaginaDeSeccion;

    #[Locked]
    public string $tareaUlid = '';

    public ?int $entregaId = null;

    /** ULID y nombre del archivo de la entrega abierta, para el visor del modal. */
    public string $entregaUlid = '';

    public ?string $entregaArchivo = null;

    public ?float $nota = null;

    /** @var array<int|string, float|int|string> criterio => puntos */
    public array $puntos = [];

    public string $comentario = '';

    public string $nombreEquipo = '';

    /** @var array<int, int|string> Matrículas elegidas para el equipo nuevo. */
    public array $integrantes = [];

    public ?int $matriculaAjuste = null;

    public ?float $notaIntegrante = null;

    public string $motivoAjuste = '';

    /** @var array<int, array{nombre: string, maximo: float|string}> */
    public array $criteriosRubrica = [];

    public function mount(string $tarea): void
    {
        $this->tareaUlid = $tarea;
    }

    /**
     * Abre la calificación de una entrega con su documento junto al formulario (QA 17/11). El ULID y el archivo salen de
     * la lista del servidor: antes el botón los pasaba con @js dentro de un atributo de componente, Blade no lo
     * compilaba y el clic no hacía nada en el navegador.
     */
    public function abrir(int $entrega): void
    {
        $fila = app(ActividadService::class)->entregas(auth()->user(), $this->seccionId(), $this->tareaUlid)['entregas']
            ->first(fn (object $e) => (int) $e->id === $entrega) ?? abort(404);
        $this->reset('nota', 'puntos', 'comentario');
        $this->entregaId = $entrega;
        $this->entregaUlid = (string) $fila->ulid;
        $this->entregaArchivo = $fila->archivo;
        // «Cambiar» abre con lo que ya tenía: antes el formulario salía vacío y había que volver a escribir todo.
        $previa = app(ActividadService::class)->calificacionDeEntrega(auth()->user(), $this->seccionId(), $entrega);
        $this->puntos = $previa['puntos'];
        $this->nota = $previa['puntos'] === [] ? $previa['nota'] : null;
        $this->comentario = (string) $previa['comentario'];
        $this->resetValidation();
        $this->dispatch('abrir-modal', 'calificar');
    }

    /**
     * Abre la entrega anterior o la siguiente de la lista sin cerrar el calificador.
     */
    public function moverA(int $paso, ActividadService $actividades): void
    {
        $entregas = $actividades->entregas(auth()->user(), $this->seccionId(), $this->tareaUlid)['entregas']->values();
        $indice = $entregas->search(fn (object $e) => (int) $e->id === (int) $this->entregaId);
        $destino = $indice === false ? null : $entregas->get($indice + $paso);
        if ($destino !== null) {
            $this->abrir((int) $destino->id);
        }
    }

    /**
     * Guarda la calificación. Con `$seguir` («Guardar y siguiente») abre la próxima entrega sin nota.
     */
    public function calificar(bool $seguir = false): void
    {
        $actividades = app(ActividadService::class);
        $this->resetValidation();

        try {
            $final = $actividades->calificarEntrega(auth()->user(), $this->seccionId(), (int) $this->entregaId, $this->nota,
                $this->puntos, $this->comentario ?: null);
            $this->dispatch('aviso', tipo: 'exito', mensaje: __('Calificada con :n.', ['n' => number_format($final, 2)]));
            // «Guardar y siguiente»: la próxima entrega sin nota; si ya no queda ninguna, se cierra.
            $siguiente = $seguir ? $actividades->entregas(auth()->user(), $this->seccionId(), $this->tareaUlid)['entregas']
                ->first(fn (object $e) => $e->score === null && (int) $e->id !== (int) $this->entregaId) : null;
            if ($siguiente !== null) {
                $this->abrir((int) $siguiente->id);
            } else {
                $this->dispatch('cerrar-modal', 'calificar');
            }
        } catch (ValidationException $e) {
            // El rechazo de un criterio se ve junto a su campo; lo que no pertenece al formulario, como aviso.
            $delFormulario = false;
            foreach ($e->errors() as $campo => $mensajes) {
                $this->addError($campo, (string) $mensajes[0]);
                $delFormulario = $delFormulario || $campo === 'nota' || str_starts_with($campo, 'puntos.');
            }
            if (! $delFormulario) {
                $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
            }
        }
    }

    public function publicar(CalificacionService $calificaciones, ActividadService $actividades): void
    {
        try {
            $item = $actividades->entregas(auth()->user(), $this->seccionId(), $this->tareaUlid)['tarea']->item_id;
            $n = $calificaciones->publicar(auth()->user(), (int) $item);
            $this->dispatch('aviso', tipo: 'exito', mensaje: trans_choice('{0} Notas publicadas.|{1} Se publicó 1 nota.|[2,*] Se publicaron :count notas.', $n));
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
        }
    }

    public function crearEquipo(ActividadService $actividades, EquipoService $equipos): void
    {
        $this->validate(['nombreEquipo' => 'required|string|max:100', 'integrantes' => 'required|array|min:1'], [], ['nombreEquipo' => 'nombre', 'integrantes' => 'integrantes']);
        $equipos->crear(auth()->user(), $this->seccionId(), $this->tareaId($actividades), $this->nombreEquipo, array_map('intval', $this->integrantes));
        $this->reset('nombreEquipo', 'integrantes');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Equipo creado.'));
    }

    public function eliminarEquipo(int $equipo, ActividadService $actividades, EquipoService $equipos): void
    {
        try {
            $equipos->eliminar(auth()->user(), $this->seccionId(), $this->tareaId($actividades), $equipo);
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
        }
    }

    public function abrirAjuste(int $entrega, int $matricula): void
    {
        $this->reset('notaIntegrante', 'motivoAjuste');
        $this->entregaId = $entrega;
        $this->matriculaAjuste = $matricula;
        $this->resetValidation();
        $this->dispatch('abrir-modal', 'ajustar-integrante');
    }

    public function ajustar(ActividadService $actividades): void
    {
        $this->validate(['notaIntegrante' => 'required|numeric|between:0,10', 'motivoAjuste' => 'required|string|min:5|max:500'], [], ['notaIntegrante' => 'nota', 'motivoAjuste' => 'motivo']);
        $final = $actividades->ajustarIntegrante(auth()->user(), $this->seccionId(), (int) $this->entregaId, (int) $this->matriculaAjuste, $this->notaIntegrante, $this->motivoAjuste);
        $this->dispatch('cerrar-modal', 'ajustar-integrante');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Nota del integrante ajustada a :n.', ['n' => number_format($final, 2)]));
    }

    public function abrirRubrica(ActividadService $actividades): void
    {
        $rubrica = $actividades->entregas(auth()->user(), $this->seccionId(), $this->tareaUlid)['rubrica'];
        $this->criteriosRubrica = $rubrica->isEmpty()
            ? [['nombre' => '', 'maximo' => 4], ['nombre' => '', 'maximo' => 3], ['nombre' => '', 'maximo' => 3]]
            : $rubrica->map(fn ($c) => ['nombre' => $c->name, 'maximo' => (float) $c->max_points])->values()->all();
        $this->resetValidation();
        $this->dispatch('abrir-modal', 'rubrica');
    }

    public function agregarCriterio(): void
    {
        $this->criteriosRubrica[] = ['nombre' => '', 'maximo' => 0];
    }

    public function quitarCriterio(int $indice): void
    {
        if (count($this->criteriosRubrica) > 1) {
            unset($this->criteriosRubrica[$indice]);
            $this->criteriosRubrica = array_values($this->criteriosRubrica);
        }
    }

    public function guardarRubrica(RubricaService $rubricas): void
    {
        $this->validate(['criteriosRubrica.*.nombre' => 'required|string|max:150', 'criteriosRubrica.*.maximo' => 'required|numeric|min:0.5|max:10'],
            [], ['criteriosRubrica.*.nombre' => 'criterio', 'criteriosRubrica.*.maximo' => 'puntos']);
        $rubricas->guardar(auth()->user(), $this->seccionId(), $this->tareaUlid,
            array_map(fn ($c) => ['nombre' => $c['nombre'], 'maximo' => (float) $c['maximo']], $this->criteriosRubrica));
        $this->dispatch('cerrar-modal', 'rubrica');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Rúbrica guardada.'));
    }

    public function quitarRubrica(RubricaService $rubricas): void
    {
        try {
            $rubricas->guardar(auth()->user(), $this->seccionId(), $this->tareaUlid, []);
            $this->dispatch('aviso', tipo: 'exito', mensaje: __('La tarea se calificará con nota directa.'));
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
        }
    }

    private function tareaId(ActividadService $actividades): int
    {
        return (int) $actividades->entregas(auth()->user(), $this->seccionId(), $this->tareaUlid)['tarea']->id;
    }

    public function render(ActividadService $actividades, EquipoService $equipos): View
    {
        $datos = $actividades->entregas(auth()->user(), $this->seccionId(), $this->tareaUlid);

        return $this->paginaDeSeccion('modules.evaluaciones.entregas', $datos + [
            'equipos' => $datos['tarea']->is_group ? $equipos->equipos(auth()->user(), $this->seccionId(), (int) $datos['tarea']->id) : null,
        ]);
    }
}
