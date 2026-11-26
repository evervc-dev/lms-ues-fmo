<?php

namespace App\Modules\Academica\Livewire\Concerns;

use App\Modules\Academica\Services\CatalogoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Crear, editar y eliminar departamentos desde un componente (RF07, CP-RF07-08 a 12). Lo usan Catálogo › Estructura y
 * la pantalla Departamentos (ADR-083), con el modal `modules.academica.partials.departamento-modal`. El permiso lo exige
 * CatalogoService (asignaturas.gestionar con alcance G, el Administrador).
 */
trait GestionaDepartamentos
{
    public ?int $departamentoEditando = null;

    public string $departamentoCodigo = '';

    public string $departamentoNombre = '';

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
     * Archiva o desarchiva el departamento (ADR-092); si algo lo impide, el Service explica qué.
     */
    public function archivarDepartamento(int $id, bool $archivar, CatalogoService $catalogo): void
    {
        try {
            $catalogo->archivarDepartamento(auth()->user(), $id, $archivar);
            $this->dispatch('aviso', tipo: 'exito', mensaje: $archivar ? __('Departamento archivado.') : __('Departamento desarchivado.'));
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
        }
    }

    /**
     * Elimina el departamento si nada lo usa; si no, el Service explica qué lo usa (CP-RF07-10).
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
}
