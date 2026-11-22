<?php

namespace App\Modules\Identidad\Livewire;

use App\Modules\Identidad\Services\CatalogoPermisos;
use App\Modules\Identidad\Services\RolService;
use App\Support\Busqueda;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Roles y permisos (RF03): matriz de permisos por rol. Los cambios se acumulan y se guardan juntos.
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

    /**
     * Cambios sin guardar: «rol|permiso» => si quedará concedido. Se aplican todos juntos con guardar(); antes, cada
     * clic se guardaba al instante y un clic de más le quitaba un permiso a todo un rol (QA admin 21/11).
     *
     * @var array<string, bool>
     */
    public array $cambios = [];

    /** Rol cuya columna se ve en el teléfono, donde no caben las seis. */
    public string $rolMovil = 'administrador';

    /**
     * Marca o desmarca una casilla. Volver al estado guardado quita el cambio de la lista.
     */
    public function marcar(string $rol, string $permiso, RolService $roles): void
    {
        $clave = $rol.'|'.$permiso;
        $guardado = in_array($permiso, $roles->matriz(auth()->user())['roles'][$rol]['permisos'] ?? [], true);
        $deseado = ! ($this->cambios[$clave] ?? $guardado);

        if ($deseado === $guardado) {
            unset($this->cambios[$clave]);
        } else {
            $this->cambios[$clave] = $deseado;
        }
    }

    public function descartar(): void
    {
        $this->reset('cambios');
    }

    public function guardar(RolService $roles): void
    {
        $lista = collect($this->cambios)->map(function (bool $conceder, string $clave): array {
            [$rol, $permiso] = explode('|', $clave, 2);

            return ['rol' => $rol, 'permiso' => $permiso, 'conceder' => $conceder];
        })->values()->all();

        try {
            $total = $roles->aplicar(auth()->user(), $lista);
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());

            return;
        }

        $this->reset('cambios');
        $this->dispatch('aviso', tipo: 'exito', mensaje: trans_choice('{0} No había cambios que guardar.|{1} Se guardó 1 cambio de permisos.|[2,*] Se guardaron :count cambios de permisos.', $total));
    }

    public function render(RolService $roles): View
    {
        $matriz = $roles->matriz(auth()->user());
        $buscar = Busqueda::normalizar($this->buscar);
        // Se busca por la clave, por el nombre legible y por el grupo.
        $coincide = fn (string $p) => str_contains(Busqueda::normalizar($p), $buscar) || str_contains(Busqueda::normalizar(CatalogoPermisos::de($p)['nombre']), $buscar);
        if ($buscar !== '') {
            $matriz['grupos'] = collect($matriz['grupos'])
                ->map(fn ($permisos, string $grupo) => str_contains(Busqueda::normalizar($grupo.' '.CatalogoPermisos::grupo($grupo)), $buscar)
                    ? collect($permisos)
                    : collect($permisos)->filter($coincide)->values())
                ->filter(fn ($permisos) => $permisos->isNotEmpty())
                ->all();
        }

        return $this->pagina('modules.identidad.roles-permisos', $matriz);
    }
}
