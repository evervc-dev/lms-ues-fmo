<?php

namespace App\Modules\Evaluaciones\Livewire;

use App\Modules\Evaluaciones\Services\ActividadService;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Crear tarea (RF15, maqueta 19): fechas, política de entrega, archivos, rúbrica y calificación anónima. La rúbrica se
 * arma aquí mismo, con sus criterios (ADR-065).
 */
#[Layout('layouts::app')]
class NuevaTarea extends Component
{
    use PaginaDeSeccion;

    public string $titulo = '';

    public string $instrucciones = '';

    public ?int $componente = null;

    public float $peso = 20;

    public string $abre = '';

    public string $cierra = '';

    public string $politica = 'strict';

    public string $gracia = '';

    public int $penalizacion = 10;

    public string $extensiones = 'pdf, docx, zip';

    public int $mb = 10;

    public int $intentos = 1;

    public bool $anonima = false;

    public bool $grupal = false;

    public bool $conRubrica = false;

    /** @var array<int, array{nombre: string, maximo: float|string}> */
    public array $criterios = [['nombre' => '', 'maximo' => 4], ['nombre' => '', 'maximo' => 3], ['nombre' => '', 'maximo' => 3]];

    public function mount(): void
    {
        $this->abre = now()->format('Y-m-d\TH:i');
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
            'criterios.*.nombre' => 'exclude_unless:conRubrica,true|required|string|max:150', 'criterios.*.maximo' => 'exclude_unless:conRubrica,true|required|numeric|min:0.5|max:10',
        ], [], ['titulo' => 'título', 'componente' => 'cómputo', 'gracia' => 'fecha de gracia', 'criterios.*.nombre' => 'criterio', 'criterios.*.maximo' => 'puntos']);

        $ulid = $actividades->crearTarea(auth()->user(), $this->seccionId(), [
            'componente' => (int) $this->componente, 'titulo' => $this->titulo, 'instrucciones' => $this->instrucciones ?: null,
            'abre' => $this->abre, 'cierra' => $this->cierra, 'peso' => $this->peso, 'politica' => $this->politica, 'gracia' => $this->gracia ?: null,
            'penalizacion' => $this->penalizacion, 'extensiones' => array_values(array_filter(array_map(fn ($e) => strtolower(trim($e, " .\t")), explode(',', $this->extensiones)))),
            'mb' => $this->mb, 'intentos' => $this->intentos, 'anonima' => $this->anonima, 'grupal' => $this->grupal,
            'criterios' => $this->conRubrica ? array_map(fn ($c) => ['nombre' => $c['nombre'], 'maximo' => (float) $c['maximo']], $this->criterios) : [],
        ]);
        session()->flash('aviso', ['tipo' => 'exito', 'mensaje' => __('Tarea creada.')]);
        $this->redirectRoute('secciones.tareas.show', ['seccion' => $this->seccion, 'tarea' => $ulid], navigate: true);
    }

    public function render(): View
    {
        return $this->paginaDeSeccion('modules.evaluaciones.nueva-tarea', [
            'computos' => DB::table('grading_components')->where('section_id', $this->seccionId())->orderBy('number')->get(),
            'politicas' => ActividadService::POLITICAS,
        ]);
    }
}
