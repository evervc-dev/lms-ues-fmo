<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\CatalogoService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Catálogo de asignaturas (RF07): código único, créditos, departamento y prerrequisitos.
 * El Administrador ve todo; el Jefe, solo las asignaturas de su departamento (ADR-072).
 */
#[Layout('layouts::app')]
class Catalogo extends Component
{
    use PaginaLivewire;
    use WithPagination;

    #[Url(except: '')]
    public string $buscar = '';

    #[Url(except: '')]
    public string $departamento = '';

    #[Url(as: 'creditos', except: '')]
    public string $filtroCreditos = '';

    public ?int $editando = null;

    public string $codigo = '';

    public string $nombre = '';

    public int $creditos = 4;

    public ?int $departamentoId = null;

    /** @var array<int, int|string> */
    public array $prerrequisitos = [];

    /**
     * Filas «carrera → plan → ciclo» del formulario (career_subjects). Al crear se exige al menos una.
     *
     * @var array<int, array{carrera: int|string|null, plan: int|string|null, ciclo: int|string|null}>
     */
    public array $planes = [];

    public function updating(string $propiedad): void
    {
        if (in_array($propiedad, ['buscar', 'departamento', 'filtroCreditos'], true)) {
            $this->resetPage();
        }
    }

    public function nueva(CatalogoService $catalogo): void
    {
        $this->reset('editando', 'codigo', 'nombre', 'creditos', 'departamentoId', 'prerrequisitos');
        $this->planes = [$this->filaDePlan()];
        // El Jefe que dirige un solo departamento no lo elige: se usa el suyo (ADR-072).
        $propios = $catalogo->departamentosGestionables(auth()->user());
        if (! $catalogo->gestionaEstructura(auth()->user()) && $propios->count() === 1) {
            $this->departamentoId = (int) $propios->first()->id;
        }
        $this->resetValidation();
        $this->dispatch('abrir-modal', 'asignatura');
    }

    public function editar(int $id, CatalogoService $catalogo): void
    {
        $a = DB::table('subjects')->where('id', $id)->first() ?? abort(404);
        $this->editando = $id;
        $this->codigo = $a->code;
        $this->nombre = $a->name;
        $this->creditos = (int) $a->credits;
        $this->departamentoId = (int) $a->department_id;
        $this->prerrequisitos = DB::table('subject_prerequisites')->where('subject_id', $id)->pluck('prerequisite_id')
            ->map(fn ($p) => (string) $p)->all(); // Texto, como el `value` de las casillas, para que salgan marcadas.
        $this->planes = $catalogo->planesDeAsignatura(auth()->user(), $id);
        $this->resetValidation();
        $this->dispatch('abrir-modal', 'asignatura');
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
        $this->dispatch('cerrar-modal', 'asignatura');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Asignatura guardada.'));
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

    /**
     * Elimina la asignatura si nada la usa; si no, el Service explica qué la usa y sugiere desactivarla (RF07).
     */
    public function eliminar(int $id, CatalogoService $catalogo): void
    {
        try {
            $catalogo->eliminarAsignatura(auth()->user(), $id);
            $this->dispatch('aviso', tipo: 'exito', mensaje: __('Asignatura eliminada.'));
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
        }
    }

    public function alternarEstado(int $id, CatalogoService $catalogo): void
    {
        $activa = ! (bool) (DB::table('subjects')->where('id', $id)->value('is_active') ?? abort(404));
        $catalogo->cambiarEstadoAsignatura(auth()->user(), $id, $activa);
        $this->dispatch('aviso', tipo: 'exito', mensaje: $activa ? __('Asignatura reactivada.') : __('Asignatura desactivada: no se ofrecerá al crear secciones.'));
    }

    public function render(CatalogoService $catalogo): View
    {
        $asignaturas = $catalogo->asignaturas(auth()->user(), ['buscar' => $this->buscar, 'departamento' => $this->departamento, 'creditos' => $this->filtroCreditos]);

        return $this->pagina('modules.academica.catalogo', [
            'asignaturas' => $asignaturas,
            'prerrequisitosDe' => $catalogo->prerrequisitos($asignaturas->pluck('id')->all()),
            'departamentos' => $catalogo->departamentosGestionables(auth()->user()),
            'gestionaEstructura' => $catalogo->gestionaEstructura(auth()->user()),
            'carreras' => $catalogo->carrerasConPlanes(auth()->user()),
            'valoresCreditos' => DB::table('subjects')->distinct()->orderBy('credits')->pluck('credits'),
            'todas' => DB::table('subjects')->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }
}
