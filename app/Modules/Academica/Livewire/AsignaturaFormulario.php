<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\CatalogoService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Crear o editar una asignatura del catálogo (RF07). Va en página, no en modal, porque sus carreras y planes crecen
 * con los datos (docs/13 Layout/Layout base.md). La ruta de edición lleva el código, que es único.
 */
#[Layout('layouts::app')]
class AsignaturaFormulario extends Component
{
    use PaginaLivewire;

    #[Locked]
    public ?int $editando = null;

    public string $codigo = '';

    public string $nombre = '';

    public int $creditos = 4;

    public ?int $departamentoId = null;

    /** @var array<int, int|string> */
    public array $prerrequisitos = [];

    /**
     * Filas «carrera → plan → ciclo» (career_subjects). Al crear se exige al menos una.
     *
     * @var array<int, array{carrera: int|string|null, plan: int|string|null, ciclo: int|string|null}>
     */
    public array $planes = [];

    public function mount(CatalogoService $catalogo, ?string $asignatura = null): void
    {
        if ($asignatura === null) {
            $this->planes = [$this->filaDePlan()];
            // El Jefe que dirige un solo departamento no lo elige: se usa el suyo (ADR-072).
            $propios = $catalogo->departamentosGestionables(auth()->user());
            if (! $catalogo->gestionaEstructura(auth()->user()) && $propios->count() === 1) {
                $this->departamentoId = (int) $propios->first()->id;
            }

            return;
        }

        $a = $catalogo->asignaturaParaEditar(auth()->user(), $asignatura);
        $this->editando = (int) $a->id;
        $this->codigo = $a->code;
        $this->nombre = $a->name;
        $this->creditos = (int) $a->credits;
        $this->departamentoId = (int) $a->department_id;
        $this->prerrequisitos = DB::table('subject_prerequisites')->where('subject_id', $a->id)->pluck('prerequisite_id')
            ->map(fn ($p) => (string) $p)->all(); // Texto, como el `value` de las casillas, para que salgan marcadas.
        $this->planes = $catalogo->planesDeAsignatura(auth()->user(), (int) $a->id);
    }

    public function guardar(CatalogoService $catalogo): void
    {
        $this->validate([
            'codigo' => 'required|string|max:12',
            'nombre' => 'required|string|max:120',
            'creditos' => 'required|integer|min:1|max:20',
            'departamentoId' => 'required|integer|exists:departments,id',
            'prerrequisitos' => 'array',
            'planes' => 'array',
            // Que cada fila tenga plan y ciclo válido lo exige el Service (regla de negocio), con un mensaje para todo el bloque.
            'planes.*.ciclo' => 'nullable|integer|min:1|max:12',
        ], [], ['codigo' => 'código', 'nombre' => 'nombre', 'creditos' => 'créditos', 'departamentoId' => 'departamento', 'planes.*.ciclo' => 'ciclo']);

        $catalogo->guardarAsignatura(auth()->user(), $this->editando, [
            'codigo' => $this->codigo, 'nombre' => $this->nombre, 'creditos' => $this->creditos, 'departamento' => (int) $this->departamentoId,
            'prerrequisitos' => array_map('intval', $this->prerrequisitos),
            'planes' => array_map(fn (array $fila) => ['plan' => (int) ($fila['plan'] ?? 0), 'ciclo' => (int) ($fila['ciclo'] ?? 0)], $this->planes),
        ]);
        session()->flash('aviso', ['tipo' => 'exito', 'mensaje' => __('Asignatura guardada.')]);
        $this->redirectRoute('catalogo', navigate: true);
    }

    public function agregarPlan(): void
    {
        $this->planes[] = $this->filaDePlan();
    }

    public function quitarPlan(int $indice): void
    {
        unset($this->planes[$indice]);
        $this->planes = array_values($this->planes);
    }

    /**
     * Al cambiar la carrera de una fila se borra su plan, y si la carrera tiene un solo plan se elige solo.
     */
    public function updatedPlanes(mixed $valor, ?string $clave = null): void
    {
        [$indice, $campo] = array_pad(explode('.', (string) $clave), 2, null);
        if ($campo !== 'carrera' || ! isset($this->planes[(int) $indice])) {
            return;
        }
        $planes = DB::table('study_plans')->where('career_id', (int) $valor)->pluck('id');
        $this->planes[(int) $indice]['plan'] = $planes->count() === 1 ? (int) $planes->first() : null;
    }

    /**
     * @return array{carrera: null, plan: null, ciclo: int}
     */
    private function filaDePlan(): array
    {
        return ['carrera' => null, 'plan' => null, 'ciclo' => 1];
    }

    public function render(CatalogoService $catalogo): View
    {
        $departamentos = $catalogo->departamentosGestionables(auth()->user());
        $todas = DB::table('subjects')->orderBy('code')->get(['id', 'code', 'name']);

        return $this->pagina('modules.academica.asignatura-formulario', [
            'departamentos' => $departamentos,
            // El Jefe que dirige un solo departamento no lo elige: la asignatura queda en el suyo (ADR-072).
            'eligeDepartamento' => $catalogo->gestionaEstructura(auth()->user()) || $departamentos->count() > 1,
            'carreras' => $catalogo->carrerasConPlanes(auth()->user()),
            'todas' => $todas,
            'elegidas' => $todas->whereIn('id', array_map('intval', $this->prerrequisitos))->values(),
        ], titulo: $this->editando ? __('Editar :c', ['c' => $this->codigo]) : null, miga: $this->editando ? $this->codigo : null);
    }
}
