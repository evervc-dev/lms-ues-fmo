<?php

namespace App\Modules\Contenidos\Livewire;

use App\Modules\Academica\Services\CatalogoService;
use App\Modules\Contenidos\Services\ContenidoService;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Vista de sección › Contenidos (RF11, RF18, maquetas 06 y 07).
 */
#[Layout('layouts::app')]
class ContenidosSeccion extends Component
{
    use PaginaDeSeccion;
    use WithFileUploads;

    #[Url(except: '')]
    public string $buscar = '';

    /** Filtro por tipo de recurso: clave de ContenidoService::TIPOS o '' para todos. */
    #[Url(except: '')]
    public string $tipo = '';

    public string $tituloUnidad = '';

    /** Descripción de la materia que ve el estudiante al inscribirse (RF29, ADR-050). */
    public ?string $descripcion = null;

    public function crearUnidad(ContenidoService $contenidos): void
    {
        $this->validate(['tituloUnidad' => 'required|string|max:150'], [], ['tituloUnidad' => 'título']);
        $contenidos->crearUnidad(auth()->user(), $this->seccionId(), $this->tituloUnidad);
        $this->reset('tituloUnidad');
        $this->dispatch('cerrar-modal', 'nueva-unidad');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Unidad creada.'));
    }

    /**
     * Abre el modal con la descripción actual de la asignatura de la sección.
     */
    public function abrirDescripcion(): void
    {
        $this->descripcion = DB::table('subjects')->where('id', $this->asignaturaId())->value('description');
        $this->resetErrorBag('descripcion');
        $this->dispatch('abrir-modal', 'descripcion-materia');
    }

    public function guardarDescripcion(CatalogoService $catalogo): void
    {
        $this->validate(['descripcion' => 'nullable|string|max:2000'], [], ['descripcion' => 'descripción']);
        $catalogo->editarDescripcion(auth()->user(), $this->asignaturaId(), $this->descripcion);
        $this->dispatch('cerrar-modal', 'descripcion-materia');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Se guardó la descripción de la materia.'));
    }

    public function marcarLeido(int $recurso, ContenidoService $contenidos): void
    {
        $contenidos->marcarLeido(auth()->user(), $this->seccionId(), $recurso);
    }

    public function eliminar(int $recurso, ContenidoService $contenidos): void
    {
        $contenidos->eliminar(auth()->user(), $this->seccionId(), $recurso);
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Se movió a la papelera. Puede restaurarlo durante 30 días.'));
    }

    public function eliminarUnidad(int $unidad, ContenidoService $contenidos): void
    {
        $contenidos->eliminarUnidad(auth()->user(), $this->seccionId(), $unidad);
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('La unidad se movió a la papelera. Puede restaurarla durante 30 días.'));
    }

    private function asignaturaId(): int
    {
        return (int) DB::table('sections')->where('id', $this->seccionId())->value('subject_id');
    }

    public function render(ContenidoService $contenidos): View
    {
        $seccion = $this->seccionId();
        $unidades = $contenidos->unidades(auth()->user(), $seccion, $this->buscar, $this->tipo);
        $gestiona = $contenidos->puedeGestionar(auth()->user(), $seccion);
        $esEstudiante = auth()->user()->hasRole('estudiante') && ! $gestiona;
        $total = $unidades->sum(fn ($u) => $u->recursos->count());

        return $this->paginaDeSeccion('modules.contenidos.contenidos', [
            'unidades' => $unidades,
            'gestiona' => $gestiona,
            'esEstudiante' => $esEstudiante,
            'avance' => $total ? 100 * $unidades->sum(fn ($u) => $u->recursos->where('completado', true)->count()) / $total : 0,
            'tipos' => ContenidoService::TIPOS,
        ]);
    }
}
