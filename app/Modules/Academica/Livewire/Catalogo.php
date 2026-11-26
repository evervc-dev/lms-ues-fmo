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
 * El Administrador ve todo; el Jefe, solo las asignaturas de su departamento (ADR-072). Crear y editar van en su
 * propia página (AsignaturaFormulario).
 */
#[Layout('layouts::app')]
class Catalogo extends Component
{
    use PaginaLivewire;
    use WithPagination;

    #[Url(except: '')]
    public string $buscar = '';

    #[Url(except: '')]
    public string $carrera = '';

    #[Url(as: 'creditos', except: '')]
    public string $filtroCreditos = '';

    public function updating(string $propiedad): void
    {
        if (in_array($propiedad, ['buscar', 'carrera', 'filtroCreditos'], true)) {
            $this->resetPage();
        }
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
        try {
            $cascada = $catalogo->cambiarEstadoAsignatura(auth()->user(), $id, $activa);
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());

            return;
        }

        $mensaje = match (true) {
            $activa => __('Asignatura reactivada. Las que dependen de ella se reactivan aparte.'),
            $cascada !== [] => __('Asignatura desactivada junto con las que dependen de ella: :codigos. No se ofrecerán al crear grupos teóricos.', ['codigos' => implode(', ', $cascada)]),
            default => __('Asignatura desactivada: no se ofrecerá al crear grupos teóricos.'),
        };
        $this->dispatch('aviso', tipo: 'exito', mensaje: $mensaje);
    }

    public function render(CatalogoService $catalogo): View
    {
        $asignaturas = $catalogo->asignaturas(auth()->user(), ['buscar' => $this->buscar, 'carrera' => $this->carrera, 'creditos' => $this->filtroCreditos]);

        $ids = $asignaturas->pluck('id')->all();

        return $this->pagina('modules.academica.catalogo', [
            'asignaturas' => $asignaturas,
            'prerrequisitosDe' => $catalogo->prerrequisitos($ids),
            'carrerasDe' => $catalogo->carreras(auth()->user(), $ids),
            'dependientesDe' => $catalogo->dependientesActivas($ids),
            'veDepartamento' => $catalogo->veDepartamento(auth()->user()),
            'gestionaEstructura' => $catalogo->gestionaEstructura(auth()->user()),
            'carreras' => $catalogo->carrerasConPlanes(auth()->user()),
            'valoresCreditos' => DB::table('subjects')->distinct()->orderBy('credits')->pluck('credits'),
        ]);
    }
}
