<?php

namespace App\Modules\Identidad\Livewire;

use App\Modules\Academica\Services\CargaAcademicaService;
use App\Modules\Academica\Services\PeriodoService;
use App\Modules\Identidad\Services\JerarquiaService;
use App\Modules\Identidad\Services\PerfilContextualService;
use App\Modules\Identidad\Services\RolService;
use App\Modules\Identidad\Services\UsuarioService;
use App\Support\Alcance;
use App\Support\Navegacion\PaginaLivewire;
use App\Support\Nombre;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Detalle de usuario (RF04): roles, asignaciones vigentes, dispositivo y últimos accesos.
 * Qué edita cada rol lo decide JerarquiaService (ADR-037): el Administrador todo; Soporte, cuentas de estudiantes
 * (y docentes con permiso); el Jefe, los roles Docente y Coordinador de su departamento. Desde la ficha de un docente,
 * quien asigna docentes (RF08) le asigna un grupo teórico sin pasar por Carga académica.
 */
#[Layout('layouts::app')]
class UsuarioDetalle extends Component
{
    use PaginaLivewire;

    #[Locked]
    public int $usuarioId;

    public string $nombres = '';

    public string $apellidos = '';

    /** Atajo «Asignar grupo teórico» (RF08): solo se consultan los grupos mientras el modal está abierto. */
    public bool $asignandoGrupo = false;

    public ?int $periodoGrupo = null;

    public string $buscarGrupo = '';

    public bool $responsableGrupo = true;

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

    public function abrirAsignarGrupo(): void
    {
        $this->asignandoGrupo = true;
        $this->periodoGrupo = PeriodoService::preparableOActivo($this->periodoGrupo);
        $this->reset('buscarGrupo');
        $this->responsableGrupo = true;
        $this->dispatch('abrir-modal', 'asignar-grupo');
    }

    /**
     * Asigna este docente al grupo elegido. Las reglas (perfil docente activo, alcance, choque de horario, máximo de
     * docentes) son las de Carga académica: el Service es el mismo.
     */
    public function asignarGrupo(int $seccion, CargaAcademicaService $carga): void
    {
        try {
            $carga->asignarDocente(auth()->user(), $seccion, $this->usuarioId, $this->responsableGrupo);
            $this->dispatch('aviso', tipo: 'exito', mensaje: __('Grupo teórico asignado.'));
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
        }
    }

    /**
     * Grupos del período al alcance de quien asigna (Administrador G, Jefe D, Coordinador C).
     *
     * @return Collection<int, object>
     */
    private function gruposParaAsignar(CargaAcademicaService $carga): Collection
    {
        if (! $this->asignandoGrupo) {
            return collect();
        }

        return $carga->secciones(auth()->user(), PeriodoService::preparableOActivo($this->periodoGrupo), ['buscar' => $this->buscarGrupo]);
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
            // Atajo de RF08: a un docente activo, quien tiene secciones.asignar-docente le asigna un grupo desde aquí.
            'asignaGrupos' => $ficha['usuario']->is_active && $ficha['usuario']->hasRole('docente') && app(Alcance::class)->de($actor, 'secciones.asignar-docente') !== null,
            'gruposParaAsignar' => $this->gruposParaAsignar(app(CargaAcademicaService::class)),
            'periodosGrupo' => PeriodoService::preparables(),
        ], titulo: $ficha['usuario']->fullName());
    }

    private function precargar(UsuarioService $usuarios): void
    {
        $usuario = $usuarios->ficha(auth()->user(), $this->usuarioId)['usuario'];

        $this->nombres = (string) $usuario->first_name;
        $this->apellidos = (string) $usuario->last_name;
    }
}
