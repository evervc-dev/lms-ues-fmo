<?php

namespace App\Modules\Identidad\Services;

use App\Models\User;
use App\Support\Alcance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Quién crea a quién y qué roles asigna (ADR-037, docs/04 Roles/Matriz RBAC.md).
 *
 * - Administrador: crea y asigna todos los roles; es el único que desactiva y reactiva.
 * - Soporte: crea y corrige cuentas de estudiantes; crea docentes solo con el permiso directo `usuarios.crear-docente`
 *   (no los quita ni les cambia el departamento). Nunca toca a usuarios con un rol que no asigna; sí da el primer
 *   rol a una cuenta sin roles, p. ej. la de un autorregistro (ADR-038).
 * - Jefe: no crea cuentas; da o quita Docente y Coordinador, solo en su departamento y sobre su personal
 *   o la cuenta que encontró escribiendo su correo completo.
 * - Coordinador: no asigna roles (importa estudiantes de su carrera: ImportacionService).
 *
 * Además, ningún camino de asignación deja convivir el rol Estudiante con el de Docente o el de Soporte
 * Técnico (ADR-053): exigirRolesCompatibles().
 */
class JerarquiaService
{
    /** Roles que cada rol puede dar o quitar. */
    public const ROLES_QUE_ASIGNA = [
        'administrador' => RolService::ROLES,
        'soporte_tecnico' => ['estudiante'],
        'jefe_departamento' => ['docente', 'coordinador'],
    ];

    /**
     * Roles que no pueden coexistir con el de Estudiante (ADR-053): quien estudia no ejerce como docente
     * ni atiende Soporte, porque el alcance S (sus secciones) y el M (sus matrículas) se cruzarían en la
     * misma sección. Los demás roles sí coexisten entre sí y con Estudiante.
     *
     * @var array<int, string>
     */
    public const INCOMPATIBLES_CON_ESTUDIANTE = ['docente', 'soporte_tecnico'];

    /** Permiso directo (por usuario, nunca por rol) que el Administrador concede a un Soporte para crear docentes. */
    public const PERMISO_CREAR_DOCENTE = 'usuarios.crear-docente';

    /** Cuentas que el Jefe encontró por correo exacto en esta sesión y puede incorporar. */
    private const SESION_INCORPORABLES = 'usuarios.incorporables';

    public function __construct(private Alcance $alcance) {}

    /**
     * Roles que el actor puede dar.
     *
     * @return array<int, string> En el orden de RolService::ROLES.
     */
    public function rolesAsignables(User $actor): array
    {
        $asignables = $this->rolesQueQuita($actor);
        if ($actor->hasRole('soporte_tecnico') && $actor->hasDirectPermission(self::PERMISO_CREAR_DOCENTE)) {
            $asignables[] = 'docente';
        }

        return array_values(array_intersect(RolService::ROLES, $asignables));
    }

    /**
     * Roles que el actor puede quitar: los de su rol, sin el permiso directo de crear docentes.
     *
     * @return array<int, string>
     */
    public function rolesQueQuita(User $actor): array
    {
        return collect(self::ROLES_QUE_ASIGNA)->only($actor->getRoleNames()->all())->flatten()->unique()->values()->all();
    }

    /**
     * Gestiona cuentas con alcance global (Administrador y Soporte). Un rol que reciba `usuarios.gestionar`
     * fuera de la matriz queda con alcance P y no gestiona.
     */
    public function gestionaGlobal(User $actor): bool
    {
        return $this->alcance->de($actor, 'usuarios.gestionar') === 'G';
    }

    /**
     * Control total: crea cualquier rol, desactiva y reactiva cuentas.
     */
    public function controlTotal(User $actor): bool
    {
        return $this->gestionaGlobal($actor) && in_array('administrador', $this->rolesAsignables($actor), true);
    }

    public function puedeCrear(User $actor): bool
    {
        return $this->gestionaGlobal($actor) && $this->rolesAsignables($actor) !== [];
    }

    /**
     * Editar nombres: el Administrador, o Soporte sobre cuentas cuyos roles asigna todos (también las que no tienen
     * ninguno, como un autorregistro: ADR-038).
     */
    public function puedeEditarDatos(User $actor, User $usuario): bool
    {
        if ($this->controlTotal($actor)) {
            return true;
        }

        return $this->gestionaGlobal($actor) && array_diff($usuario->getRoleNames()->all(), $this->rolesAsignables($actor)) === [];
    }

    /**
     * Abrir el formulario de roles: quien puede editar los datos, o el Jefe sobre una cuenta de su alcance.
     */
    public function puedeCambiarRoles(User $actor, User $usuario): bool
    {
        return $this->puedeEditarDatos($actor, $usuario)
            || ($this->alcance->de($actor, 'usuarios.roles.asignar') !== null && $this->enAlcanceDelJefe($actor, $usuario->id));
    }

    /**
     * Departamentos donde el actor asigna Docente y Coordinador; null = todos (alcance global).
     *
     * @return array<int, int>|null
     */
    public function departamentosPermitidos(User $actor): ?array
    {
        return $this->gestionaGlobal($actor) ? null : $this->alcance->departamentos($actor);
    }

    /**
     * Cuentas que ve el Jefe: su personal y las que encontró escribiendo el correo completo.
     */
    public function enAlcanceDelJefe(User $actor, int $usuarioId): bool
    {
        return in_array($usuarioId, $this->personalDe($this->alcance->departamentos($actor)), true)
            || in_array($usuarioId, session(self::SESION_INCORPORABLES, []), true);
    }

    public function recordarIncorporable(int $usuarioId): void
    {
        session()->put(self::SESION_INCORPORABLES, array_values(array_unique([...session(self::SESION_INCORPORABLES, []), $usuarioId])));
    }

    /**
     * @throws AuthorizationException
     */
    public function exigirCrear(User $actor, string $rol): void
    {
        if (! $this->puedeCrear($actor) || ! in_array($rol, $this->rolesAsignables($actor), true)) {
            throw new AuthorizationException(__('No puede crear usuarios con el rol :rol.', ['rol' => __('roles.'.$rol)]));
        }
    }

    /**
     * @throws AuthorizationException
     */
    public function exigirRol(User $actor, string $rol, bool $quitar = false): void
    {
        if (! in_array($rol, $quitar ? $this->rolesQueQuita($actor) : $this->rolesAsignables($actor), true)) {
            throw new AuthorizationException(__('No puede asignar ni quitar el rol :rol.', ['rol' => __('roles.'.$rol)]));
        }
    }

    /**
     * Combinación de roles válida (ADR-053): Estudiante no convive con Docente ni con Soporte Técnico.
     * Se exige en todo camino de asignación (crear, cambiar roles y carga masiva), así que vale igual para el
     * Administrador, para Soporte y para el Jefe en su departamento. No se aplica hacia atrás: las cuentas que
     * ya tengan la combinación quedan como están hasta que alguien les cambie los roles.
     *
     * @param  array<int, string>  $roles  Los roles que tendría la cuenta al terminar la operación.
     * @param  string  $campo  Clave del mensaje de error (la pantalla de roles usa «roles»; la de creación, «rol»).
     *
     * @throws ValidationException
     */
    public function exigirRolesCompatibles(array $roles, string $campo = 'roles'): void
    {
        if (! in_array('estudiante', $roles, true)) {
            return;
        }

        $choques = array_values(array_intersect(self::INCOMPATIBLES_CON_ESTUDIANTE, $roles));
        if ($choques === []) {
            return;
        }

        $nombres = array_map(fn (string $rol) => __('roles.'.$rol), $choques);
        throw ValidationException::withMessages([$campo => count($nombres) === 1
            ? __('Una cuenta con el rol Estudiante no puede tener además el rol :rol.', ['rol' => $nombres[0]])
            : __('Una cuenta con el rol Estudiante no puede tener además los roles :roles.', ['roles' => implode(' y ', $nombres)])]);
    }

    /**
     * El actor puede actuar sobre esta cuenta: el Administrador sobre todas; Soporte sobre las que puede editar;
     * el Jefe sobre las de su alcance.
     *
     * @throws AuthorizationException
     */
    public function exigirSobreUsuario(User $actor, int $usuarioId): void
    {
        if ($this->controlTotal($actor)) {
            return;
        }
        $usuario = User::query()->with('roles')->findOrFail($usuarioId);
        $permitido = $this->gestionaGlobal($actor)
            ? $this->puedeEditarDatos($actor, $usuario)
            : $this->alcance->de($actor, 'usuarios.roles.asignar') !== null && $this->enAlcanceDelJefe($actor, $usuarioId);

        if (! $permitido) {
            throw new AuthorizationException(__('No puede modificar a este usuario.'));
        }
    }

    /**
     * Departamento de la asignación vigente y de la nueva:
     * - Jefe: ambas de su departamento (el perfil docente cuenta aunque hoy no tenga el rol).
     * - Soporte: no cambia el departamento de un perfil docente que ya existe.
     *
     * @param  int|null  $unidadNueva  Departamento del docente o carrera que coordinará.
     *
     * @throws AuthorizationException
     */
    public function exigirJurisdiccion(User $actor, int $usuarioId, string $rol, ?int $unidadNueva = null): void
    {
        if ($this->controlTotal($actor) || ! in_array($rol, ['docente', 'coordinador'], true)) {
            return;
        }

        $actual = $rol === 'docente'
            ? DB::table('teacher_profiles')->where('user_id', $usuarioId)->value('department_id')
            : DB::table('career_coordinators')->join('careers', 'careers.id', '=', 'career_coordinators.career_id')
                ->where('career_coordinators.user_id', $usuarioId)->whereNull('career_coordinators.valid_to')->value('careers.department_id');
        $nuevo = $unidadNueva === null ? null : ($rol === 'docente' ? $unidadNueva : DB::table('careers')->where('id', $unidadNueva)->value('department_id'));

        if ($this->gestionaGlobal($actor)) {
            if ($actual !== null && $nuevo !== null && (int) $actual !== (int) $nuevo) {
                throw new AuthorizationException(__('No puede cambiar el departamento de un docente.'));
            }

            return;
        }

        $permitidos = $this->alcance->departamentos($actor);
        foreach ([$actual, $nuevo] as $departamento) {
            if ($departamento !== null && ! in_array((int) $departamento, $permitidos, true)) {
                throw new AuthorizationException(__('Esa asignación es de otro departamento.'));
            }
        }
    }

    /**
     * Personal del departamento que ve el Jefe: docentes con perfil en él y coordinadores de sus carreras.
     *
     * @param  array<int, int>  $departamentos
     * @return array<int, int>
     */
    public function personalDe(array $departamentos): array
    {
        return DB::table('teacher_profiles')->whereIn('department_id', $departamentos)->pluck('user_id')
            ->merge(DB::table('career_coordinators')->join('careers', 'careers.id', '=', 'career_coordinators.career_id')
                ->whereIn('careers.department_id', $departamentos)->whereNull('career_coordinators.valid_to')->pluck('career_coordinators.user_id'))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
    }
}
