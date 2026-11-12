<?php

namespace App\Modules\Identidad\Livewire;

use App\Modules\Identidad\Services\JerarquiaService;
use App\Modules\Identidad\Services\PerfilContextualService;
use App\Modules\Identidad\Services\RolService;
use App\Modules\Identidad\Services\UsuarioService;
use App\Support\Navegacion\PaginaLivewire;
use App\Support\Nombre;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Detalle de usuario (RF04): roles, asignaciones vigentes, dispositivo y últimos accesos.
 * Qué edita cada rol lo decide JerarquiaService (ADR-037): el Administrador todo; Soporte, cuentas de estudiantes
 * (y docentes con permiso); el Jefe, los roles Docente y Coordinador de su departamento.
 */
#[Layout('layouts::app')]
class UsuarioDetalle extends Component
{
    use PaginaLivewire;

    #[Locked]
    public int $usuarioId;

    public string $nombres = '';

    public string $apellidos = '';

    public function mount(int $usuario, UsuarioService $usuarios): void
    {
        $this->usuarioId = $usuario;
        $this->precargar($usuarios);
    }

    public function editarDatos(UsuarioService $usuarios): void
    {
        $this->resetValidation();
        $this->precargar($usuarios);
        $this->dispatch('abrir-modal', 'editar-datos');
    }

    public function guardarDatos(UsuarioService $usuarios): void
    {
        $this->validate([
            'nombres' => ['required', 'string', Nombre::regla()],
            'apellidos' => ['required', 'string', Nombre::regla()],
        ], [], ['nombres' => __('nombres'), 'apellidos' => __('apellidos')]);

        $usuarios->actualizarDatos(auth()->user(), $this->usuarioId, ['nombres' => $this->nombres, 'apellidos' => $this->apellidos]);

        $this->dispatch('cerrar-modal', 'editar-datos');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Se actualizaron los datos del usuario.'));
    }

    /**
     * El Administrador concede o retira a un Soporte el permiso de crear docentes (ADR-037).
     */
    public function permitirCrearDocentes(bool $permitir, UsuarioService $usuarios): void
    {
        $usuarios->permitirCrearDocentes(auth()->user(), $this->usuarioId, $permitir);
        $this->dispatch('aviso', tipo: 'exito', mensaje: $permitir ? __('Ahora puede crear docentes.') : __('Ya no puede crear docentes.'));
    }

    public function render(UsuarioService $usuarios, JerarquiaService $jerarquia, PerfilContextualService $perfiles): View
    {
        $actor = auth()->user();
        $ficha = $usuarios->ficha($actor, $this->usuarioId);

        return $this->pagina('modules.identidad.usuario-detalle', $ficha + [
            'editaDatos' => $jerarquia->puedeEditarDatos($actor, $ficha['usuario']),
            'cambiaRoles' => $jerarquia->puedeCambiarRoles($actor, $ficha['usuario']),
            'esPropio' => $this->usuarioId === $actor->id,
            'rolesDisponibles' => RolService::ROLES,
            'rolesAsignables' => $jerarquia->rolesAsignables($actor),
            'concedeCrearDocente' => $jerarquia->controlTotal($actor) && $ficha['usuario']->hasRole('soporte_tecnico'),
            'creaDocentes' => $ficha['usuario']->hasDirectPermission(JerarquiaService::PERMISO_CREAR_DOCENTE),
            // RF36: el Jefe puede tener delante una cuenta que encontró por su correo, sin vínculo para el perfil.
            'verPerfil' => $this->usuarioId !== $actor->id && $actor->checkPermissionTo('perfiles.ver') && $perfiles->nivel($actor, $this->usuarioId) !== null,
        ], titulo: $ficha['usuario']->fullName());
    }

    private function precargar(UsuarioService $usuarios): void
    {
        $usuario = $usuarios->ficha(auth()->user(), $this->usuarioId)['usuario'];

        $this->nombres = (string) $usuario->first_name;
        $this->apellidos = (string) $usuario->last_name;
    }
}
