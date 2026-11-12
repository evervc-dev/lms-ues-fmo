<?php

namespace App\Modules\Identidad\Livewire;

use App\Modules\Identidad\Services\RolService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Roles y permisos (RF03): matriz de permisos por rol según la Matriz RBAC.
 */
#[Layout('layouts::app')]
class RolesPermisos extends Component
{
    use PaginaLivewire;

    /** Filtra la matriz por el nombre del permiso o el de su grupo; son más de cien filas. */
    #[Url(except: '')]
    public string $buscar = '';

    public function limpiar(): void
    {
        $this->reset('buscar');
    }

    public function alternar(string $rol, string $permiso, RolService $roles): void
    {
        try {
            $ahora = $roles->alternar(auth()->user(), $rol, $permiso);
            $this->dispatch('aviso', tipo: 'exito', mensaje: $ahora
                ? __('Se concedió :permiso a :rol.', ['permiso' => $permiso, 'rol' => __('roles.'.$rol)])
                : __('Se quitó :permiso a :rol.', ['permiso' => $permiso, 'rol' => __('roles.'.$rol)]));
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
        }
    }

    public function render(RolService $roles): View
    {
        $matriz = $roles->matriz(auth()->user());
        $buscar = mb_strtolower(trim($this->buscar));
        if ($buscar !== '') {
            $matriz['grupos'] = collect($matriz['grupos'])
                ->map(fn ($permisos, string $grupo) => str_contains(mb_strtolower($grupo), $buscar)
                    ? collect($permisos)
                    : collect($permisos)->filter(fn (string $p) => str_contains(mb_strtolower($p), $buscar))->values())
                ->filter(fn ($permisos) => $permisos->isNotEmpty())
                ->all();
        }

        return $this->pagina('modules.identidad.roles-permisos', $matriz);
    }
}
