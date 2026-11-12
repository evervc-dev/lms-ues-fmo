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

    public function updating(string $propiedad): void
    {
        if (in_array($propiedad, ['buscar', 'departamento', 'filtroCreditos'], true)) {
            $this->resetPage();
        }
    }

    public function nueva(): void
    {
        $this->reset('editando', 'codigo', 'nombre', 'creditos', 'departamentoId', 'prerrequisitos');
        $this->resetValidation();
        $this->dispatch('abrir-modal', 'asignatura');
    }

    public function editar(int $id): void
    {
        $a = DB::table('subjects')->where('id', $id)->first() ?? abort(404);
        $this->editando = $id;
        $this->codigo = $a->code;
        $this->nombre = $a->name;
        $this->creditos = (int) $a->credits;
        $this->departamentoId = (int) $a->department_id;
        $this->prerrequisitos = DB::table('subject_prerequisites')->where('subject_id', $id)->pluck('prerequisite_id')
            ->map(fn ($p) => (string) $p)->all(); // Texto, como el `value` de las casillas, para que salgan marcadas.
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
        ], [], ['codigo' => 'código', 'nombre' => 'nombre', 'creditos' => 'créditos', 'departamentoId' => 'departamento']);

        $catalogo->guardarAsignatura(auth()->user(), $this->editando, [
            'codigo' => $this->codigo, 'nombre' => $this->nombre, 'creditos' => $this->creditos, 'departamento' => (int) $this->departamentoId,
            'prerrequisitos' => array_map('intval', $this->prerrequisitos),
        ]);
        $this->dispatch('cerrar-modal', 'asignatura');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Asignatura guardada.'));
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
            'departamentos' => DB::table('departments')->orderBy('code')->get(['id', 'code', 'name']),
            'valoresCreditos' => DB::table('subjects')->distinct()->orderBy('credits')->pluck('credits'),
            'todas' => DB::table('subjects')->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }
}
