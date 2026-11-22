<?php

namespace App\Modules\Evaluaciones\Livewire;

use App\Modules\Evaluaciones\Services\ActividadService;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Crear o editar tarea (RF15, maqueta 19): fechas, política de entrega, archivos, rúbrica y calificación anónima. La rúbrica se
 * arma aquí mismo, con sus criterios (ADR-065).
 */
#[Layout('layouts::app')]
class NuevaTarea extends Component
{
    use PaginaDeSeccion;

    #[Locked]
    public ?string $editandoUlid = null;

    #[Locked]
    public int $entregasCount = 0;

    #[Locked]
    public bool $rubricaEditable = true;

    #[Locked]
    public bool $modalidadBloqueada = false;

    public string $titulo = '';

    public string $instrucciones = '';

    public ?int $componente = null;

    public float $peso = 20;

    public string $abre = '';

    public string $cierra = '';

    public string $politica = 'strict';

    public string $gracia = '';

    public int $penalizacion = 10;

    /** @var array<int, string> extensiones elegidas del catálogo de ActividadService::EXTENSIONES */
    public array $extensiones = ['pdf', 'docx'];

    public int $mb = 10;

    public int $intentos = 1;

    public bool $anonima = false;

    public bool $grupal = false;

    public bool $conRubrica = false;

    /** @var array<int, array{nombre: string, maximo: float|string}> */
    public array $criterios = [['nombre' => '', 'maximo' => 4], ['nombre' => '', 'maximo' => 3], ['nombre' => '', 'maximo' => 3]];

    public function mount(ActividadService $actividades, ?string $tarea = null): void
    {
        if ($tarea !== null) {
            $datos = $actividades->tareaParaEditar(auth()->user(), $this->seccionId(), $tarea);
            $t = $datos['tarea'];
            $this->editandoUlid = $t->ulid;
            $this->entregasCount = $datos['entregasCount'];
            $this->rubricaEditable = $datos['rubricaEditable'];
            $this->modalidadBloqueada = $datos['modalidadBloqueada'];
            $this->titulo = $t->title;
            $this->instrucciones = $t->instructions ?? '';
            $this->componente = (int) $t->component_id;
            $this->peso = (float) $t->weight_percent;
            $this->abre = Carbon::parse($t->opens_at)->timezone('America/El_Salvador')->format('Y-m-d\TH:i');
            $this->cierra = Carbon::parse($t->closes_at)->timezone('America/El_Salvador')->format('Y-m-d\TH:i');
            $this->politica = $t->late_policy;
            $this->gracia = $t->grace_until ? Carbon::parse($t->grace_until)->timezone('America/El_Salvador')->format('Y-m-d\TH:i') : '';
            $this->penalizacion = (int) $t->late_penalty_percent;
            $this->extensiones = json_decode((string) $t->allowed_extensions, true) ?: [];
            $this->mb = (int) $t->max_file_mb;
            $this->intentos = (int) $t->max_attempts;
            $this->anonima = (bool) $t->is_anonymous;
            $this->grupal = (bool) $t->is_group;

            if ($datos['criterios'] !== []) {
                $this->conRubrica = true;
                $this->criterios = $datos['criterios'];
            } else {
                $this->conRubrica = false;
            }

            return;
        }

        // A la próxima media hora en punto: «01:03» no es una hora que alguien elija (QA 04/11, D-15).
        $this->abre = now()->addMinutes(30 - now()->minute % 30)->second(0)->format('Y-m-d\TH:i');
        $this->cierra = now()->addWeek()->setTime(23, 59)->format('Y-m-d\TH:i');
    }

    public function agregarCriterio(): void
    {
        $this->criterios[] = ['nombre' => '', 'maximo' => 0];
    }

    public function quitarCriterio(int $indice): void
    {
        if (count($this->criterios) > 1) {
            unset($this->criterios[$indice]);
            $this->criterios = array_values($this->criterios);
        }
    }

    public function guardar(ActividadService $actividades): void
    {
        $this->validate([
            'titulo' => 'required|string|max:200', 'componente' => 'required|integer', 'peso' => 'required|numeric|min:1|max:100',
            'abre' => 'required|date', 'cierra' => 'required|date', 'politica' => 'required|in:strict,grace,grace_penalty',
            'gracia' => 'nullable|required_unless:politica,strict|date', 'penalizacion' => 'integer|min:0|max:100',
            'mb' => 'required|integer|min:1|max:100', 'intentos' => 'required|integer|min:1|max:10',
            'extensiones' => 'required|array|min:1', 'extensiones.*' => ['required', 'string', Rule::in(ActividadService::extensionesValidas())],
            'criterios.*.nombre' => 'exclude_unless:conRubrica,true|required|string|max:150', 'criterios.*.maximo' => 'exclude_unless:conRubrica,true|required|numeric|min:0.5|max:10',
        ], [], ['titulo' => 'título', 'componente' => 'cómputo', 'gracia' => 'fecha de gracia', 'extensiones' => 'extensiones permitidas', 'criterios.*.nombre' => 'criterio', 'criterios.*.maximo' => 'puntos']);

        $criterios = $this->conRubrica ? array_map(fn ($c) => ['nombre' => (string) $c['nombre'], 'maximo' => (float) $c['maximo']], $this->criterios) : [];

        if ($this->editandoUlid !== null) {
            $actividades->actualizarTarea(auth()->user(), $this->seccionId(), $this->editandoUlid, [
                'componente' => (int) $this->componente, 'titulo' => $this->titulo, 'instrucciones' => $this->instrucciones ?: null,
                'abre' => $this->abre, 'cierra' => $this->cierra, 'peso' => $this->peso, 'politica' => $this->politica, 'gracia' => $this->gracia ?: null,
                'penalizacion' => $this->penalizacion, 'extensiones' => array_values($this->extensiones),
                'mb' => $this->mb, 'intentos' => $this->intentos, 'anonima' => $this->anonima, 'grupal' => $this->grupal,
                'conRubrica' => $this->conRubrica,
                'criterios' => $criterios,
            ]);
            session()->flash('aviso', ['tipo' => 'exito', 'mensaje' => __('Tarea actualizada.')]);
            $this->redirectRoute('secciones.tareas.show', ['seccion' => $this->seccion, 'tarea' => $this->editandoUlid], navigate: true);

            return;
        }

        $ulid = $actividades->crearTarea(auth()->user(), $this->seccionId(), [
            'componente' => (int) $this->componente, 'titulo' => $this->titulo, 'instrucciones' => $this->instrucciones ?: null,
            'abre' => $this->abre, 'cierra' => $this->cierra, 'peso' => $this->peso, 'politica' => $this->politica, 'gracia' => $this->gracia ?: null,
            'penalizacion' => $this->penalizacion, 'extensiones' => array_values($this->extensiones),
            'mb' => $this->mb, 'intentos' => $this->intentos, 'anonima' => $this->anonima, 'grupal' => $this->grupal,
            'criterios' => $criterios,
        ]);
        session()->flash('aviso', ['tipo' => 'exito', 'mensaje' => __('Tarea creada.')]);
        $this->redirectRoute('secciones.tareas.show', ['seccion' => $this->seccion, 'tarea' => $ulid], navigate: true);
    }

    public function render(): View
    {
        return $this->paginaDeSeccion('modules.evaluaciones.nueva-tarea', [
            'editando' => $this->editandoUlid !== null,
            'entregasCount' => $this->entregasCount,
            'rubricaEditable' => $this->rubricaEditable,
            'modalidadBloqueada' => $this->modalidadBloqueada,
            'computos' => DB::table('grading_components')->where('section_id', $this->seccionId())->orderBy('number')->get(),
            'politicas' => ActividadService::POLITICAS,
            'catalogoExtensiones' => ActividadService::EXTENSIONES,
        ]);
    }
}
