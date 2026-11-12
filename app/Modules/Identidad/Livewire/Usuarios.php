<?php

namespace App\Modules\Identidad\Livewire;

use App\Modules\Identidad\Services\JerarquiaService;
use App\Modules\Identidad\Services\UsuarioService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Usuarios (RF04, maqueta 29, ADR-037). Administrador: todo; Soporte: crea estudiantes (y docentes con permiso);
 * Jefe: ve su personal; Coordinador: importa estudiantes de su carrera.
 */
#[Layout('layouts::app')]
class Usuarios extends Component
{
    use PaginaLivewire;
    use WithPagination;

    #[Url(except: '')]
    public string $buscar = '';

    #[Url(except: '')]
    public string $rol = '';

    #[Url(except: '')]
    public string $estado = '';

    /** «sin» o «con» teléfono de asistencia (RF27): el detalle de la tarjeta del panel de Soporte. */
    #[Url(except: '')]
    public string $telefono = '';

    public ?int $desactivarId = null;

    public string $motivo = '';

    public function updating(string $propiedad): void
    {
        if (in_array($propiedad, ['buscar', 'rol', 'estado', 'telefono'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function usuarios(): LengthAwarePaginator
    {
        return app(UsuarioService::class)->listar(auth()->user(), ['buscar' => $this->buscar, 'rol' => $this->rol, 'estado' => $this->estado, 'telefono' => $this->telefono]);
    }

    /** Deja la lista sin filtros de una vez: quitarlos uno por uno era molesto. */
    public function limpiar(): void
    {
        $this->reset('buscar', 'rol', 'estado', 'telefono');
        $this->resetPage();
    }

    public function confirmarDesactivar(int $usuario): void
    {
        $this->desactivarId = $usuario;
        $this->motivo = '';
        $this->dispatch('abrir-modal', 'desactivar-usuario');
    }

    public function desactivar(UsuarioService $usuarios): void
    {
        $this->validate(['motivo' => 'required|string|min:5|max:300'], [], ['motivo' => 'motivo']);
        $usuarios->desactivar(auth()->user(), (int) $this->desactivarId, $this->motivo);

        $this->reset('desactivarId', 'motivo');
        $this->dispatch('cerrar-modal', 'desactivar-usuario');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('La cuenta quedó desactivada. Su historial se conserva.'));
    }

    public function reactivar(int $usuario, UsuarioService $usuarios): void
    {
        $usuarios->reactivar(auth()->user(), $usuario);
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('La cuenta quedó activa de nuevo.'));
    }

    public function render(UsuarioService $usuarios, JerarquiaService $jerarquia): View
    {
        $actor = auth()->user();

        return $this->pagina('modules.identidad.usuarios', [
            'resumen' => $usuarios->resumen($actor),
            'puedeCrear' => $jerarquia->puedeCrear($actor),
            'importa' => $actor->checkPermissionTo('usuarios.importar'),
            'controlTotal' => $jerarquia->controlTotal($actor),
        ]);
    }
}
