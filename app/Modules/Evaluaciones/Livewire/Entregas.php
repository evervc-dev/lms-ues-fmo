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

    public ?float $nota = null;

    /** @var array<int|string, int|string> criterio => nivel */
    public array $niveles = [];

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

    public function abrir(int $entrega): void
    {
        $this->reset('nota', 'niveles', 'comentario');
        $this->entregaId = $entrega;
        $this->resetValidation();
        $this->dispatch('abrir-modal', 'calificar');
    }

    public function calificar(ActividadService $actividades): void
    {
        try {
            $final = $actividades->calificarEntrega(auth()->user(), $this->seccionId(), (int) $this->entregaId, $this->nota,
                array_map('intval', array_filter($this->niveles)), $this->comentario ?: null);
            $this->dispatch('cerrar-modal', 'calificar');
            $this->dispatch('aviso', tipo: 'exito', mensaje: __('Calificada con :n.', ['n' => number_format($final, 2)]));
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
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
