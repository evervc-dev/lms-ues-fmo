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
    public string $departamento = '';

    #[Url(except: '')]
    public string $carrera = '';

    #[Url(as: 'creditos', except: '')]
    public string $filtroCreditos = '';

    public function updating(string $propiedad): void
    {
        if (in_array($propiedad, ['buscar', 'departamento', 'carrera', 'filtroCreditos'], true)) {
            $this->resetPage();
        }
    }

    public function updatedDepartamento(): void
    {
        if ($this->carrera !== '' && $this->departamento !== '') {
            $deptoId = DB::table('departments')->where('code', $this->departamento)->value('id');
            $pertenece = DB::table('careers')->where('id', (int) $this->carrera)->where('department_id', $deptoId)->exists();
            if (! $pertenece) {
                $this->carrera = '';
            }
        }
    }

    public function seleccionarDepartamento(string $codigo): void
    {
        $this->departamento = $codigo;
        $this->updatedDepartamento();
        $this->resetPage();
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
        $actor = auth()->user();
        $asignaturas = $catalogo->asignaturas($actor, [
            'buscar' => $this->buscar,
            'departamento' => $this->departamento,
            'carrera' => $this->carrera,
            'creditos' => $this->filtroCreditos,
        ]);

        $ids = $asignaturas->pluck('id')->all();

        return $this->pagina('modules.academica.catalogo', [
            'asignaturas' => $asignaturas,
            'prerrequisitosDe' => $catalogo->prerrequisitos($ids),
            'carrerasDe' => $catalogo->carreras($actor, $ids),
            'dependientesDe' => $catalogo->dependientesActivas($ids),
            'veDepartamento' => $catalogo->veDepartamento($actor),
            'gestionaEstructura' => $catalogo->gestionaEstructura($actor),
            'departamentos' => $catalogo->departamentosGestionables($actor),
            'conteosDepto' => $catalogo->conteosPorDepartamento($actor),
            'carreras' => $catalogo->carrerasConPlanes($actor),
            'valoresCreditos' => DB::table('subjects')->distinct()->orderBy('credits')->pluck('credits'),
        ]);
    }
}
