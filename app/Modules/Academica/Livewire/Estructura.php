<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\CatalogoService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Departamentos, carreras, planes y aulas (RF07, ADR-034): los mantiene el Administrador.
 * Los departamentos se crean, se editan (código y nombre), se archivan y reactivan (ADR-076) y se eliminan solo si
 * nada los usa.
 */
#[Layout('layouts::app')]
class Estructura extends Component
{
    use PaginaLivewire;

    public string $aulaCodigo = '';

    public int $aulaCapacidad = 40;

    public ?int $departamentoEditando = null;

    public string $departamentoCodigo = '';

    public string $departamentoNombre = '';

    public function agregarAula(CatalogoService $catalogo): void
    {
        $this->validate(['aulaCodigo' => 'required|string|max:20', 'aulaCapacidad' => 'required|integer|min:1|max:500'], [], ['aulaCodigo' => 'código', 'aulaCapacidad' => 'capacidad']);
        $catalogo->guardarAula(auth()->user(), $this->aulaCodigo, $this->aulaCapacidad);
        $this->reset('aulaCodigo', 'aulaCapacidad');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Aula agregada.'));
    }

    public function nuevoDepartamento(): void
    {
        $this->reset('departamentoEditando', 'departamentoCodigo', 'departamentoNombre');
        $this->resetValidation();
        $this->dispatch('abrir-modal', 'departamento');
    }

    public function editarDepartamento(int $id): void
    {
        $departamento = DB::table('departments')->where('id', $id)->first() ?? abort(404);
        $this->departamentoEditando = $id;
        $this->departamentoCodigo = $departamento->code;
        $this->departamentoNombre = $departamento->name;
        $this->resetValidation();
        $this->dispatch('abrir-modal', 'departamento');
    }

    public function guardarDepartamento(CatalogoService $catalogo): void
    {
        $this->validate(
            ['departamentoCodigo' => 'required|string|max:20', 'departamentoNombre' => 'required|string|max:255'],
            [],
            ['departamentoCodigo' => 'código', 'departamentoNombre' => 'nombre'],
        );
        $mensaje = $this->departamentoEditando === null ? __('Departamento creado.') : __('Departamento actualizado.');
        $catalogo->guardarDepartamento(auth()->user(), $this->departamentoEditando, $this->departamentoCodigo, $this->departamentoNombre);
        $this->reset('departamentoEditando', 'departamentoCodigo', 'departamentoNombre');
        $this->dispatch('cerrar-modal', 'departamento');
        $this->dispatch('aviso', tipo: 'exito', mensaje: $mensaje);
    }

    /**
     * Elimina el departamento si nada lo usa; si no, el Service explica qué lo usa (RF07).
     */
    public function eliminarDepartamento(int $id, CatalogoService $catalogo): void
    {
        try {
            $catalogo->eliminarDepartamento(auth()->user(), $id);
            $this->dispatch('aviso', tipo: 'exito', mensaje: __('Departamento eliminado.'));
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
        }
    }

    /**
     * Archiva el departamento; si tiene grupos en un período sin cerrar, el Service lo impide y se avisa (ADR-076).
     */
    public function archivarDepartamento(int $id, CatalogoService $catalogo): void
    {
        try {
            $catalogo->archivarDepartamento(auth()->user(), $id);
            $this->dispatch('aviso', tipo: 'exito', mensaje: __('Departamento archivado: ya no se ofrece a docentes ni a estudiantes. Su historial se conserva.'));
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
        }
    }

    public function reactivarDepartamento(int $id, CatalogoService $catalogo): void
    {
        $catalogo->reactivarDepartamento(auth()->user(), $id);
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Departamento reactivado.'));
    }

    public function render(CatalogoService $catalogo): View
    {
        return $this->pagina('modules.academica.estructura', $catalogo->estructura(auth()->user()));
    }
}
