<?php

namespace App\Modules\Identidad\Services;

use App\Models\User;
use App\Support\Alcance;
use App\Support\Busqueda;
use App\Support\DbContext;
use App\Support\Nombre;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Gestión de usuarios (RF04, ADR-037). Ver: `usuarios.ver` (Administrador y Soporte, G; Jefe, D: su personal).
 * Crear y editar datos: `usuarios.gestionar` (Administrador; Soporte solo estudiantes y, con permiso, docentes).
 * Roles: según JerarquiaService (el Jefe, con `usuarios.roles.asignar`, da Docente y Coordinador en su departamento).
 * Desactivar y reactivar: solo el Administrador. Nunca se eliminan.
 * Los roles con ámbito van atados a su asignación (AsignacionService).
 */
class UsuarioService
{
    /** Filtro de la lista: cuentas sin ningún rol, p. ej. las de un autorregistro (ADR-038). */
    public const SIN_ROL = 'sin_rol';

    public function __construct(private Alcance $alcance, private AsignacionService $asignaciones, private JerarquiaService $jerarquia, private PerfilContextualService $perfiles) {}

    /**
     * El filtro «telefono» mira el teléfono de asistencia de los estudiantes con carrera activa (RF27): es el
     * detalle de la tarjeta «Estudiantes sin teléfono» del panel de Soporte, así que cuenta lo mismo que ella.
     *
     * @param  array{buscar?: string, rol?: string, estado?: string, telefono?: string}  $filtros
     */
    public function listar(User $actor, array $filtros, int $porPagina = 20): LengthAwarePaginator
    {
        $alcance = $this->alcance->exigir($actor, 'usuarios.ver');
        $buscar = trim($filtros['buscar'] ?? '');
        $correo = mb_strtolower($buscar);
        $telefono = $filtros['telefono'] ?? '';

        return User::query()->with('roles')
            ->when($alcance !== 'G', fn ($q) => $this->soloPersonal($q, $actor, $correo))
            ->when($buscar !== '', fn ($q) => $q->where(fn ($q) => $q->whereRaw("lower(institutional_email) like ? escape '\'", [Busqueda::fragmento($buscar)])
                ->orWhere(Busqueda::porNombre($buscar, ['first_name', 'last_name']))))
            ->when($filtros['rol'] ?? null, fn ($q, $rol) => $rol === self::SIN_ROL ? $q->doesntHave('roles') : $q->role($rol))
            ->when(($filtros['estado'] ?? '') === 'activos', fn ($q) => $q->where('is_active', true))
            ->when(($filtros['estado'] ?? '') === 'inactivos', fn ($q) => $q->where('is_active', false))
            ->when(in_array($telefono, ['sin', 'con'], true), fn ($q) => $this->porTelefono($q, $telefono))
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate($porPagina);
    }

    /**
     * Estudiantes con carrera activa que tienen («con») o no tienen («sin») teléfono de asistencia activo.
     *
     * @param  Builder<User>  $consulta
     * @return Builder<User>
     */
    private function porTelefono(Builder $consulta, string $filtro): Builder
    {
        $activos = DB::table('student_devices')->where('status', 'active')->select('student_id');

        return $consulta->whereIn('id', DB::table('student_careers')->where('status', 'active')->select('student_id'))
            ->when($filtro === 'sin', fn ($q) => $q->whereNotIn('id', $activos), fn ($q) => $q->whereIn('id', $activos));
    }

    /**
     * @return array{total: int, activos: int, sinRol: int, porRol: array<string, int>}
     */
    public function resumen(User $actor): array
    {
        $personal = $this->alcance->exigir($actor, 'usuarios.ver') === 'G' ? null : $this->jerarquia->personalDe($this->alcance->departamentos($actor));

        return [
            'total' => User::query()->when($personal !== null, fn ($q) => $q->whereKey($personal))->count(),
            'activos' => User::query()->when($personal !== null, fn ($q) => $q->whereKey($personal))->where('is_active', true)->count(),
            'sinRol' => User::query()->when($personal !== null, fn ($q) => $q->whereKey($personal))->doesntHave('roles')->count(),
            'porRol' => DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->when($personal !== null, fn ($q) => $q->whereIn('model_has_roles.model_id', $personal))
                ->groupBy('roles.name')->selectRaw('roles.name as rol, count(*) as total')->pluck('total', 'rol')->map(fn ($n) => (int) $n)->all(),
        ];
    }

    /**
     * Ficha del usuario: roles, asignaciones vigentes, perfil académico, dispositivo y últimos accesos.
     *
     * @return array<string, mixed>
     */
    public function ficha(User $actor, int $usuarioId): array
    {
        $global = $this->alcance->exigir($actor, 'usuarios.ver') === 'G';
        $usuario = User::query()->with('roles')->findOrFail($usuarioId);
        if (! $global && ! $this->jerarquia->enAlcanceDelJefe($actor, $usuarioId)) {
            throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        }
        $vigentes = $this->asignaciones->vigentes($usuarioId);
        $departamento = fn (?int $id): ?string => $id === null ? null : DB::table('departments')->where('id', $id)->value('name');

        return [
            'usuario' => $usuario,
            'rolesActuales' => $usuario->roles->pluck('name')->all(),
            'esEmergencia' => (bool) $usuario->is_emergency,
            'asignacion' => $vigentes,
            'docenteEn' => $departamento($vigentes['departamentoDocente']),
            'estudiante' => DB::table('student_careers')->join('careers', 'careers.id', '=', 'student_careers.career_id')
                ->join('study_plans', 'study_plans.id', '=', 'student_careers.study_plan_id')
                ->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'student_careers.student_id')
                ->where('student_careers.student_id', $usuarioId)->where('student_careers.status', 'active')
                ->first(['careers.name as carrera', 'study_plans.name as plan', 'student_careers.current_cycle as ciclo', 'student_profiles.student_code as carne']),
            'secciones' => DB::table('teaching_assignments')->join('sections', 'sections.id', '=', 'teaching_assignments.section_id')
                ->join('subjects', 'subjects.id', '=', 'sections.subject_id')->join('academic_periods', 'academic_periods.id', '=', 'sections.period_id')
                ->where('teacher_id', $usuarioId)->whereNull('ended_at')->where('academic_periods.status', 'active')
                ->orderBy('subjects.code')->get(['subjects.code', 'subjects.name', 'sections.code as grupo', 'teaching_assignments.is_responsible']),
            'coordina' => array_filter([$vigentes['carreraCoordina'] === null ? null : DB::table('careers')->where('id', $vigentes['carreraCoordina'])->value('name')]),
            'dirige' => array_filter([$departamento($vigentes['departamentoJefatura'])]),
            // El Jefe (alcance D) no ve el dispositivo ni los accesos (ADR-037).
            'dispositivo' => $global ? DB::table('student_devices')->where('student_id', $usuarioId)->where('status', 'active')->first(['created_at', 'last_used_at']) : null,
            'accesos' => $global ? DB::table('login_history')->where('user_id', $usuarioId)->orderByDesc('occurred_at')->limit(10)
                ->get(['occurred_at', 'ip_address', 'result', 'failure_reason']) : collect(),
            'verAccesos' => $global,
            // RF36 (CP-RF36-08): carreras y ciclo de las materias del docente; año de ingreso y materias del estudiante.
            'perfil' => $this->perfiles->bloques($usuarioId, $usuario->roles->pluck('name')->all(), true),
        ];
    }

    /**
     * El correo es institucional y nadie lo usa todavía (sin distinguir mayúsculas). La usa crear() y el
     * formulario de alta, para avisarlo en el primer paso y no recién al confirmar.
     *
     * @throws ValidationException
     */
    public function exigirCorreoDisponible(string $correo): void
    {
        $correo = mb_strtolower(trim($correo));
        if (! str_ends_with($correo, '@ues.edu.sv')) {
            throw ValidationException::withMessages(['correo' => __('Solo se aceptan correos institucionales @ues.edu.sv.')]);
        }
        if (User::query()->whereRaw('lower(institutional_email) = ?', [$correo])->exists()) {
            throw ValidationException::withMessages(['correo' => __('Ya existe un usuario con ese correo.')]);
        }
    }

    /**
     * Crea la cuenta con su rol y, si el rol la requiere, su asignación, en la misma transacción.
     *
     * @param  array{correo: string, nombres: string, apellidos: string, rol: string, asignacion?: array<string, mixed>}  $datos
     */
    public function crear(User $actor, array $datos): User
    {
        if (! $this->jerarquia->gestionaGlobal($actor)) {
            throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        }
        $correo = mb_strtolower(trim($datos['correo']));
        $rol = $datos['rol'];
        $asignacion = $datos['asignacion'] ?? [];

        $this->exigirCorreoDisponible($correo);
        $this->exigirFormatoNombre($datos['nombres'], $datos['apellidos']);
        if (! in_array($rol, RolService::ROLES, true)) {
            throw ValidationException::withMessages(['rol' => __('El rol elegido no existe.')]);
        }
        $this->jerarquia->exigirCrear($actor, $rol);
        // ADR-053: la cuenta nace con un solo rol, pero el camino de asignación es el mismo para todos.
        $this->jerarquia->exigirRolesCompatibles([$rol], 'rol');
        if (in_array($rol, AsignacionService::ROLES_CON_ASIGNACION, true) && ($asignacion === [] || ! $this->asignaciones->datosCompletos($rol, $asignacion))) {
            throw ValidationException::withMessages(['asignacion' => __('El rol :rol requiere su asignación.', ['rol' => __('roles.'.$rol)])]);
        }

        return DbContext::run(function () use ($actor, $correo, $datos, $rol, $asignacion) {
            $usuario = User::query()->create([
                'institutional_email' => $correo, 'first_name' => Nombre::normalizar($datos['nombres']), 'last_name' => Nombre::normalizar($datos['apellidos']), 'is_active' => true,
            ]);
            $usuario->assignRole($rol);
            $this->escribirAsignacion($actor, $usuario->id, $rol, $asignacion, 'asignacion');

            return $usuario;
        }, 'user.created');
    }

    /**
     * Edita nombres y apellidos. El correo institucional no se edita.
     *
     * @param  array{nombres: string, apellidos: string}  $datos
     */
    public function actualizarDatos(User $actor, int $usuarioId, array $datos): void
    {
        $this->alcance->exigir($actor, 'usuarios.gestionar');
        if (! $this->jerarquia->puedeEditarDatos($actor, User::query()->with('roles')->findOrFail($usuarioId))) {
            throw new AuthorizationException(__('No puede modificar los datos de este usuario.'));
        }
        $nombres = trim($datos['nombres']);
        $apellidos = trim($datos['apellidos']);

        $errores = array_filter([
            'nombres' => $nombres === '' ? __('Ingrese los nombres.') : null,
            'apellidos' => $apellidos === '' ? __('Ingrese los apellidos.') : null,
        ]);
        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
        $this->exigirFormatoNombre($nombres, $apellidos);

        DbContext::run(fn () => User::query()->whereKey($usuarioId)->update([
            'first_name' => Nombre::normalizar($nombres), 'last_name' => Nombre::normalizar($apellidos),
        ]), 'user.updated');
    }

    /**
     * Nombres y apellidos con el formato de ADR-060: como mucho 60 caracteres y ninguna palabra de más de 25 letras.
     *
     * @throws ValidationException
     */
    private function exigirFormatoNombre(string $nombres, string $apellidos): void
    {
        $errores = array_filter(['nombres' => Nombre::error($nombres), 'apellidos' => Nombre::error($apellidos)]);
        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }
    }

    /**
     * Cambia los roles del usuario. Cada rol nuevo con ámbito necesita su asignación (o tenerla vigente);
     * al quitar coordinador o jefe se cierran sus vigencias. Los perfiles docente y de estudiante se conservan.
     * Nadie cambia sus propios roles. No se quita Docente con secciones vigentes ni Estudiante con matrículas activas.
     * Los errores de campo de una asignación usan la clave `asignaciones.<rol>.<campo>`.
     *
     * @param  array<int, string>  $roles
     * @param  array<string, array<string, mixed>>  $asignaciones  Datos de AsignacionService::aplicar() indexados por rol.
     */
    public function cambiarRoles(User $actor, int $usuarioId, array $roles, array $asignaciones = []): void
    {
        $usuario = User::query()->with('roles')->findOrFail($usuarioId);
        if (! $this->jerarquia->puedeCambiarRoles($actor, $usuario)) {
            throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        }
        if ($usuarioId === $actor->id) {
            throw ValidationException::withMessages(['roles' => __('No puede modificar sus propios roles; pídaselo a otro Administrador.')]);
        }
        $roles = array_values(array_unique(array_map('strval', $roles)));

        if ($roles === []) {
            throw ValidationException::withMessages(['roles' => __('El usuario debe tener al menos un rol.')]);
        }
        if (array_diff($roles, RolService::ROLES) !== []) {
            throw ValidationException::withMessages(['roles' => __('Uno de los roles elegidos no existe.')]);
        }
        if ($usuario->is_emergency) {
            throw ValidationException::withMessages(['roles' => __('La cuenta de emergencia no admite cambios de roles.')]);
        }
        $actuales = $usuario->roles->pluck('name')->all();
        $quitados = array_values(array_diff($actuales, $roles));
        $nuevos = array_values(array_diff($roles, $actuales));
        foreach ($nuevos as $rol) {
            $this->jerarquia->exigirRol($actor, $rol);
        }
        foreach ($quitados as $rol) {
            $this->jerarquia->exigirRol($actor, $rol, quitar: true);
        }
        // La asignación vigente (o el perfil docente, aunque hoy no tenga el rol) debe estar en su jurisdicción.
        foreach ([...$quitados, ...$nuevos] as $rol) {
            $this->jerarquia->exigirJurisdiccion($actor, $usuarioId, $rol);
        }
        // ADR-053: Estudiante no convive con Docente ni con Soporte Técnico. Después de la autorización, para que
        // quien no puede tocar la cuenta reciba 403 y no una pista sobre sus roles.
        $this->jerarquia->exigirRolesCompatibles($roles);

        if (in_array('administrador', $quitados, true) && $this->esUltimoAdministrador($usuarioId)) {
            throw ValidationException::withMessages(['roles' => __('Es el último Administrador activo: asigne el rol a otra persona antes de quitárselo.')]);
        }
        $this->exigirSinDependencias($usuarioId, $quitados);

        $faltantes = [];
        foreach ($nuevos as $rol) {
            if (! $this->asignaciones->datosCompletos($rol, $asignaciones[$rol] ?? []) && $this->asignaciones->faltaAsignacion($usuarioId, $rol)) {
                $faltantes["asignaciones.{$rol}"] = __('El rol :rol requiere su asignación.', ['rol' => __('roles.'.$rol)]);
            }
        }
        if ($faltantes !== []) {
            throw ValidationException::withMessages($faltantes);
        }

        DbContext::run(function () use ($actor, $usuario, $roles, $quitados, $nuevos, $asignaciones) {
            if ($quitados !== []) {
                $usuario->removeRole($quitados);
            }
            if ($nuevos !== []) {
                $usuario->assignRole($nuevos);
            }
            foreach ($quitados as $rol) {
                $this->asignaciones->cerrarPorRol($actor, $usuario->id, $rol);
            }
            foreach ($roles as $rol) {
                if (! empty($asignaciones[$rol])) {
                    $this->escribirAsignacion($actor, $usuario->id, $rol, $asignaciones[$rol], "asignaciones.{$rol}");
                }
            }
        }, 'user.roles_changed');
    }

    /**
     * No se quita Docente con secciones vigentes en períodos no cerrados, ni Estudiante con matrículas activas.
     *
     * @param  array<int, string>  $quitados
     */
    private function exigirSinDependencias(int $usuarioId, array $quitados): void
    {
        if (in_array('docente', $quitados, true)) {
            $secciones = DB::table('teaching_assignments')->join('sections', 'sections.id', '=', 'teaching_assignments.section_id')
                ->join('subjects', 'subjects.id', '=', 'sections.subject_id')->join('academic_periods', 'academic_periods.id', '=', 'sections.period_id')
                ->where('teaching_assignments.teacher_id', $usuarioId)->whereNull('teaching_assignments.ended_at')
                ->where('academic_periods.status', '!=', 'closed')
                ->orderBy('subjects.code')->orderBy('sections.code')
                ->get(['subjects.code', 'sections.code as grupo'])->map(fn ($s) => $s->code.' · '.$s->grupo);

            if ($secciones->isNotEmpty()) {
                throw ValidationException::withMessages(['roles' => __('Tiene secciones asignadas en el período (:secciones). Reasígnelas en Carga académica antes de quitar el rol Docente.', ['secciones' => $secciones->implode(', ')])]);
            }
        }

        if (in_array('estudiante', $quitados, true)
            && DB::table('enrollments')->join('academic_periods', 'academic_periods.id', '=', 'enrollments.period_id')
                ->where('enrollments.student_id', $usuarioId)->where('enrollments.status', 'active')
                ->where('academic_periods.status', '!=', 'closed')->exists()) {
            throw ValidationException::withMessages(['roles' => __('Tiene matrículas activas. Retírelas antes de quitar el rol Estudiante.')]);
        }
    }

    /**
     * Escribe la asignación y antepone el prefijo a las claves de sus errores de campo
     * (p. ej. `asignaciones.estudiante.carne` o `asignacion.carne`).
     *
     * @param  array<string, mixed>  $datos
     */
    private function escribirAsignacion(User $actor, int $usuarioId, string $rol, array $datos, string $prefijo): void
    {
        try {
            $this->asignaciones->escribir($actor, $usuarioId, $rol, $datos);
        } catch (ValidationException $e) {
            $errores = [];
            foreach ($e->errors() as $campo => $mensajes) {
                $errores["{$prefijo}.{$campo}"] = $mensajes;
            }

            throw ValidationException::withMessages($errores);
        }
    }

    /**
     * Concede o retira a un Soporte el permiso directo de crear docentes (ADR-037). Solo el Administrador.
     */
    public function permitirCrearDocentes(User $actor, int $usuarioId, bool $permitir): void
    {
        $this->exigirControlTotal($actor);
        $usuario = User::query()->findOrFail($usuarioId);
        if (! $usuario->hasRole('soporte_tecnico')) {
            throw ValidationException::withMessages(['roles' => __('Solo se concede a usuarios con el rol Soporte técnico.')]);
        }

        DbContext::run(fn () => $permitir
            ? $usuario->givePermissionTo(JerarquiaService::PERMISO_CREAR_DOCENTE)
            : $usuario->revokePermissionTo(JerarquiaService::PERMISO_CREAR_DOCENTE), 'user.permission_changed');
    }

    /**
     * Desactivar y reactivar: solo con control total, el Administrador (ADR-037).
     *
     * @throws AuthorizationException
     */
    private function exigirControlTotal(User $actor): void
    {
        if (! $this->jerarquia->controlTotal($actor)) {
            throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        }
    }

    /**
     * Alcance D (Jefe): el personal de su departamento y, para incorporar a alguien que ya existe,
     * la cuenta cuyo correo escribe completo.
     *
     * @param  Builder<User>  $consulta
     * @return Builder<User>
     */
    private function soloPersonal(Builder $consulta, User $actor, string $buscar): Builder
    {
        $personal = $this->jerarquia->personalDe($this->alcance->departamentos($actor));
        $encontrado = filter_var($buscar, FILTER_VALIDATE_EMAIL) === false ? null
            : User::query()->whereRaw('lower(institutional_email) = ?', [$buscar])->value('id');
        if ($encontrado !== null) {
            $this->jerarquia->recordarIncorporable((int) $encontrado);
            $personal[] = (int) $encontrado;
        }

        return $consulta->whereKey($personal);
    }

    /**
     * Desactiva la cuenta y cierra sus sesiones abiertas (driver de sesión database) en la misma transacción.
     */
    public function desactivar(User $actor, int $usuarioId, string $motivo): void
    {
        $this->exigirControlTotal($actor);
        if ($usuarioId === $actor->id) {
            throw ValidationException::withMessages(['motivo' => __('No puede desactivar su propia cuenta.')]);
        }
        if ($this->esUltimoAdministrador($usuarioId)) {
            throw ValidationException::withMessages(['motivo' => __('Es el último Administrador activo: asigne el rol a otra persona antes de desactivarlo.')]);
        }

        DbContext::run(function () use ($actor, $usuarioId) {
            User::query()->whereKey($usuarioId)->update([
                'is_active' => false, 'deactivated_at' => now(), 'deactivated_by' => $actor->id,
            ]);
            DB::table('sessions')->where('user_id', $usuarioId)->delete();
        }, 'user.deactivated', $motivo);
    }

    /**
     * CP-RF03-03: el sistema nunca se queda sin un Administrador activo.
     */
    public function esUltimoAdministrador(int $usuarioId): bool
    {
        $administradores = User::role('administrador')->where('is_active', true)->pluck('id');

        return $administradores->contains($usuarioId) && $administradores->count() === 1;
    }

    public function reactivar(User $actor, int $usuarioId): void
    {
        $this->exigirControlTotal($actor);

        DbContext::run(fn () => User::query()->whereKey($usuarioId)->update([
            'is_active' => true, 'deactivated_at' => null, 'deactivated_by' => null,
        ]), 'user.reactivated');
    }
}
