<?php

namespace App\Modules\Identidad\Services;

use App\Models\User;
use App\Support\Alcance;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Asignaciones atadas a los roles (RF04): coordinación de carrera, jefatura de departamento,
 * perfil docente (departamento) y perfil de estudiante (carrera, plan, ciclo y carné).
 * Escribir exige `usuarios.gestionar` con alcance G (Administrador) y que el usuario ya tenga el rol.
 *
 * Coordinación y jefatura son vigencias: el relevo cierra la anterior (`valid_to = hoy`), abre la nueva
 * y le quita el rol a quien queda sin vigencias de ese tipo.
 * Los perfiles docente y de estudiante se conservan como historial aunque se quite el rol.
 */
class AsignacionService
{
    /** Ciclo máximo de un estudiante (también lo usa la carga masiva). */
    public const CICLO_MAXIMO = 12;

    /** Roles que solo existen con una asignación vigente. */
    public const ROLES_CON_ASIGNACION = ['coordinador', 'jefe_departamento', 'docente', 'estudiante'];

    /** Campos obligatorios de la asignación de cada rol (el carné solo se exige si aún no existe el perfil). */
    private const CAMPOS = [
        'coordinador' => ['carrera'],
        'jefe_departamento' => ['departamento'],
        'docente' => ['departamento'],
        'estudiante' => ['carrera', 'plan', 'ciclo'],
    ];

    /** Rol que depende de cada tabla de vigencias. */
    private const ROL_DE_VIGENCIA = ['career_coordinators' => 'coordinador', 'department_heads' => 'jefe_departamento'];

    public function __construct(private Alcance $alcance, private JerarquiaService $jerarquia) {}

    public function asignarCoordinacion(User $actor, int $usuarioId, int $carreraId): void
    {
        $this->exigir($actor, $usuarioId, 'coordinador', $carreraId);
        DbContext::run(fn () => $this->escribirCoordinacion($actor->id, $usuarioId, $carreraId), 'user.assignment_changed');
    }

    public function asignarJefatura(User $actor, int $usuarioId, int $departamentoId): void
    {
        $this->exigir($actor, $usuarioId, 'jefe_departamento', $departamentoId);
        DbContext::run(fn () => $this->escribirJefatura($actor->id, $usuarioId, $departamentoId), 'user.assignment_changed');
    }

    public function perfilDocente(User $actor, int $usuarioId, int $departamentoId): void
    {
        $this->exigir($actor, $usuarioId, 'docente', $departamentoId);
        DbContext::run(fn () => $this->escribirDocente($usuarioId, $departamentoId), 'user.assignment_changed');
    }

    /**
     * @param  array{carrera?: int|null, plan?: int|null, ciclo?: int|null, carne?: string|null}  $datos
     */
    public function perfilEstudiante(User $actor, int $usuarioId, array $datos): void
    {
        $this->exigir($actor, $usuarioId, 'estudiante');
        DbContext::run(fn () => $this->escribirEstudiante($usuarioId, $datos), 'user.assignment_changed');
    }

    /**
     * Aplica la asignación que corresponde al rol. Los roles sin asignación no hacen nada.
     *
     * @param  array<string, mixed>  $datos
     */
    public function aplicar(User $actor, int $usuarioId, string $rol, array $datos): void
    {
        if (! in_array($rol, self::ROLES_CON_ASIGNACION, true)) {
            $this->exigir($actor, $usuarioId);

            return;
        }

        $this->exigir($actor, $usuarioId, $rol);
        DbContext::run(fn () => $this->escribir($actor, $usuarioId, $rol, $datos), 'user.assignment_changed');
    }

    /**
     * Igual que aplicar(), pero sin abrir transacción (el rol ya debe estar asignado): para usarlo
     * dentro del DbContext::run() de UsuarioService justo después de asignar el rol (DbContext no se anida:
     * al terminar limpia el actor y las escrituras siguientes de la transacción exterior quedarían sin él).
     * Los errores de campo usan la clave del campo (`carrera`, `plan`, `ciclo`, `carne`, `departamento`).
     *
     * @param  array<string, mixed>  $datos
     *
     * @throws AuthorizationException
     */
    public function escribir(User $actor, int $usuarioId, string $rol, array $datos): void
    {
        $this->exigirRol($actor, $usuarioId, $rol, match ($rol) {
            'docente' => is_numeric($datos['departamento'] ?? null) ? (int) $datos['departamento'] : null,
            'coordinador' => is_numeric($datos['carrera'] ?? null) ? (int) $datos['carrera'] : null,
            default => null,
        });
        if (in_array($rol, self::ROLES_CON_ASIGNACION, true) && ! User::query()->findOrFail($usuarioId)->hasRole($rol)) {
            throw ValidationException::withMessages(['roles' => __('El usuario no tiene el rol :rol.', ['rol' => __('roles.'.$rol)])]);
        }

        match ($rol) {
            'coordinador' => $this->escribirCoordinacion($actor->id, $usuarioId, $this->entero($datos, 'carrera', __('Elija la carrera que coordinará.'))),
            'jefe_departamento' => $this->escribirJefatura($actor->id, $usuarioId, $this->entero($datos, 'departamento', __('Elija el departamento que dirigirá.'))),
            'docente' => $this->escribirDocente($usuarioId, $this->entero($datos, 'departamento', __('Elija el departamento del docente.'))),
            'estudiante' => $this->escribirEstudiante($usuarioId, $datos),
            default => null,
        };
    }

    /**
     * Cierra las vigencias que dependen del rol quitado. Los perfiles docente y de estudiante se conservan.
     * No abre transacción: se llama dentro del DbContext::run() de UsuarioService.
     *
     * @throws AuthorizationException
     */
    public function cerrarPorRol(User $actor, int $usuarioId, string $rol): void
    {
        // Se llama tras quitar el rol en UsuarioService::cambiarRoles(), que ya validó la cuenta.
        $this->exigirPermiso($actor);
        $this->jerarquia->exigirRol($actor, $rol, quitar: true);
        $this->jerarquia->exigirJurisdiccion($actor, $usuarioId, $rol);

        $tabla = match ($rol) {
            'coordinador' => 'career_coordinators',
            'jefe_departamento' => 'department_heads',
            default => null,
        };

        if ($tabla !== null) {
            DB::table($tabla)->where('user_id', $usuarioId)->whereNull('valid_to')
                ->update(['valid_to' => $this->hoy(), 'updated_at' => now()]);
        }
    }

    /**
     * ¿Trae los datos con todos los campos obligatorios de la asignación del rol?
     *
     * @param  array<string, mixed>  $datos
     */
    public function datosCompletos(string $rol, array $datos): bool
    {
        foreach (self::CAMPOS[$rol] ?? [] as $campo) {
            if (($datos[$campo] ?? null) === null || $datos[$campo] === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * ¿El rol requiere una asignación que el usuario no tiene vigente?
     */
    public function faltaAsignacion(int $usuarioId, string $rol): bool
    {
        return match ($rol) {
            'coordinador' => ! DB::table('career_coordinators')->where('user_id', $usuarioId)->whereNull('valid_to')->exists(),
            'jefe_departamento' => ! DB::table('department_heads')->where('user_id', $usuarioId)->whereNull('valid_to')->exists(),
            'docente' => ! DB::table('teacher_profiles')->where('user_id', $usuarioId)->exists(),
            'estudiante' => ! DB::table('student_profiles')->where('user_id', $usuarioId)->exists()
                || ! DB::table('student_careers')->where('student_id', $usuarioId)->where('status', 'active')->exists(),
            default => false,
        };
    }

    /**
     * Titular vigente que perdería la unidad si se asigna el rol (una coordinación por carrera, una jefatura
     * por departamento), para avisarlo antes de guardar. Null si la unidad está libre o ya es del usuario.
     *
     * @return object{id: int, nombre: string, correo: string}|null
     */
    public function titularARelevar(string $rol, ?int $unidadId, ?int $usuarioId): ?object
    {
        [$tabla, $columna] = match ($rol) {
            'coordinador' => ['career_coordinators', 'career_id'],
            'jefe_departamento' => ['department_heads', 'department_id'],
            default => [null, null],
        };
        if ($tabla === null || $unidadId === null) {
            return null;
        }

        $titular = DB::table($tabla)->join('users', 'users.id', '=', "{$tabla}.user_id")
            ->where("{$tabla}.{$columna}", $unidadId)->whereNull("{$tabla}.valid_to")
            ->first(['users.id', 'users.first_name', 'users.last_name', 'users.institutional_email']);
        if ($titular === null || (int) $titular->id === $usuarioId) {
            return null;
        }

        return (object) ['id' => (int) $titular->id, 'nombre' => trim($titular->first_name.' '.$titular->last_name), 'correo' => $titular->institutional_email];
    }

    /**
     * Asignaciones vigentes del usuario, para precargar los formularios.
     *
     * @return array{carreraCoordina: int|null, departamentoJefatura: int|null, departamentoDocente: int|null, carreraEstudiante: int|null, planEstudiante: int|null, cicloEstudiante: int|null, carne: string|null}
     */
    public function vigentes(int $usuarioId): array
    {
        $estudiante = DB::table('student_careers')->where('student_id', $usuarioId)->where('status', 'active')
            ->first(['career_id', 'study_plan_id', 'current_cycle']);
        $entero = fn (mixed $valor): ?int => $valor === null ? null : (int) $valor;

        return [
            'carreraCoordina' => $entero(DB::table('career_coordinators')->where('user_id', $usuarioId)->whereNull('valid_to')->value('career_id')),
            'departamentoJefatura' => $entero(DB::table('department_heads')->where('user_id', $usuarioId)->whereNull('valid_to')->value('department_id')),
            'departamentoDocente' => $entero(DB::table('teacher_profiles')->where('user_id', $usuarioId)->value('department_id')),
            'carreraEstudiante' => $entero($estudiante?->career_id),
            'planEstudiante' => $entero($estudiante?->study_plan_id),
            'cicloEstudiante' => $entero($estudiante?->current_cycle),
            'carne' => DB::table('student_profiles')->where('user_id', $usuarioId)->value('student_code'),
        ];
    }

    /**
     * Catálogos para los selectores de asignación. Con actor, el Jefe solo ve su departamento y sus carreras.
     *
     * @return array{departamentos: Collection<int, object>, carreras: Collection<int, object>, planes: Collection<int, object>}
     */
    public function opciones(?User $actor = null): array
    {
        $permitidos = $actor === null ? null : $this->jerarquia->departamentosPermitidos($actor);

        return [
            'departamentos' => DB::table('departments')->when($permitidos !== null, fn ($q) => $q->whereIn('id', $permitidos))->orderBy('name')->get(['id', 'code', 'name']),
            'carreras' => DB::table('careers')->when($permitidos !== null, fn ($q) => $q->whereIn('department_id', $permitidos))->orderBy('name')->get(['id', 'code', 'name', 'department_id']),
            'planes' => DB::table('study_plans')->orderBy('name')->get(['id', 'career_id', 'name']),
        ];
    }

    /**
     * Rol que el actor asigna (ADR-037), usuario existente (404) y, si se indica, que ya tenga el rol (rol atado).
     */
    private function exigir(User $actor, int $usuarioId, ?string $rol = null, ?int $unidadNueva = null): void
    {
        $rol === null ? $this->exigirGlobal($actor) : $this->exigirRol($actor, $usuarioId, $rol, $unidadNueva);
        $usuario = User::query()->findOrFail($usuarioId);

        if ($rol !== null && ! $usuario->hasRole($rol)) {
            throw ValidationException::withMessages(['roles' => __('El usuario no tiene el rol :rol.', ['rol' => __('roles.'.$rol)])]);
        }
    }

    /**
     * El actor asigna el rol y, si es el Jefe, la asignación vigente y la nueva son de su departamento.
     *
     * @throws AuthorizationException
     */
    private function exigirRol(User $actor, int $usuarioId, string $rol, ?int $unidadNueva = null): void
    {
        $this->exigirPermiso($actor);
        $this->jerarquia->exigirRol($actor, $rol);
        $this->jerarquia->exigirSobreUsuario($actor, $usuarioId);
        $this->jerarquia->exigirJurisdiccion($actor, $usuarioId, $rol, $unidadNueva);
    }

    /**
     * `usuarios.gestionar` con alcance G (Administrador y Soporte) o `usuarios.roles.asignar` (Jefe).
     *
     * @throws AuthorizationException
     */
    private function exigirPermiso(User $actor): void
    {
        if (! $this->jerarquia->gestionaGlobal($actor)) {
            $this->alcance->exigir($actor, 'usuarios.roles.asignar');
        }
    }

    /**
     * @throws AuthorizationException
     */
    private function exigirGlobal(User $actor): void
    {
        if ($this->alcance->exigir($actor, 'usuarios.gestionar') !== 'G') {
            throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        }
    }

    /**
     * Relevo: cierra la coordinación que el usuario tuviera en otra carrera y la del coordinador anterior.
     */
    private function escribirCoordinacion(int $actorId, int $usuarioId, int $carreraId): void
    {
        if (! DB::table('careers')->where('id', $carreraId)->exists()) {
            throw ValidationException::withMessages(['carrera' => __('La carrera elegida no existe.')]);
        }

        $this->relevar($actorId, 'career_coordinators', 'career_id', $usuarioId, $carreraId);
    }

    private function escribirJefatura(int $actorId, int $usuarioId, int $departamentoId): void
    {
        if (! DB::table('departments')->where('id', $departamentoId)->exists()) {
            throw ValidationException::withMessages(['departamento' => __('El departamento elegido no existe.')]);
        }

        $this->relevar($actorId, 'department_heads', 'department_id', $usuarioId, $departamentoId);
    }

    /**
     * Una vigencia por unidad y una unidad vigente por persona. Si ya es la vigente, no hace nada.
     *
     * El relevado (quien tenía la unidad) pierde en la misma transacción el rol que depende de la tabla
     * (coordinador o jefe_departamento) si ya no le queda ninguna vigencia de ese tipo; conserva sus otros roles.
     * Aquí no se aplica la regla de «al menos un rol» de UsuarioService::cambiarRoles(): si era su único rol,
     * el relevado queda sin roles y entra sin módulos hasta que el Administrador le asigne otro.
     * No se releva al propio actor (nadie modifica sus propios roles) ni a la cuenta de emergencia (ADR-036).
     */
    private function relevar(int $actorId, string $tabla, string $columna, int $usuarioId, int $unidadId): void
    {
        $vigente = DB::table($tabla)->where($columna, $unidadId)->whereNull('valid_to')->value('user_id');
        if ($vigente !== null && (int) $vigente === $usuarioId) {
            return;
        }
        if ($vigente !== null) {
            $campo = $columna === 'career_id' ? 'carrera' : 'departamento';
            if ((int) $vigente === $actorId) {
                throw ValidationException::withMessages([$campo => __('Usted es el titular vigente; el relevo le quitaría su propio rol. Pídaselo a otro Administrador.')]);
            }
            if (User::query()->whereKey($vigente)->where('is_emergency', true)->exists()) {
                throw ValidationException::withMessages([$campo => __('El titular vigente es la cuenta de emergencia, cuyos roles no se editan.')]);
            }
        }

        $hoy = $this->hoy();
        // Primero se cierran las vigencias: el índice único parcial (valid_to IS NULL) no admite dos abiertas.
        DB::table($tabla)->whereNull('valid_to')
            ->where(fn ($q) => $q->where('user_id', $usuarioId)->orWhere($columna, $unidadId))
            ->update(['valid_to' => $hoy, 'updated_at' => now()]);

        DB::table($tabla)->insert([
            'user_id' => $usuarioId, $columna => $unidadId, 'valid_from' => $hoy, 'valid_to' => null,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        if ($vigente !== null) {
            $this->quitarRolSinVigencias($tabla, (int) $vigente);
        }
    }

    private function quitarRolSinVigencias(string $tabla, int $relevadoId): void
    {
        $rol = self::ROL_DE_VIGENCIA[$tabla];
        if (DB::table($tabla)->where('user_id', $relevadoId)->whereNull('valid_to')->exists()) {
            return;
        }

        $relevado = User::query()->find($relevadoId);
        if ($relevado?->hasRole($rol)) {
            $relevado->removeRole($rol);
        }
    }

    private function escribirDocente(int $usuarioId, int $departamentoId): void
    {
        if (! DB::table('departments')->where('id', $departamentoId)->exists()) {
            throw ValidationException::withMessages(['departamento' => __('El departamento elegido no existe.')]);
        }

        $actual = DB::table('teacher_profiles')->where('user_id', $usuarioId)->value('department_id');
        if ($actual === null) {
            DB::table('teacher_profiles')->insert(['user_id' => $usuarioId, 'department_id' => $departamentoId, 'created_at' => now(), 'updated_at' => now()]);
        } elseif ((int) $actual !== $departamentoId) {
            DB::table('teacher_profiles')->where('user_id', $usuarioId)->update(['department_id' => $departamentoId, 'updated_at' => now()]);
        }
    }

    /**
     * Carné fijo desde que se crea el perfil. Cambio de carrera: la activa pasa a `changed` y se abre la nueva.
     *
     * @param  array<string, mixed>  $datos
     */
    private function escribirEstudiante(int $usuarioId, array $datos): void
    {
        $carreraId = $this->entero($datos, 'carrera', __('Elija la carrera del estudiante.'));
        $planId = $this->entero($datos, 'plan', __('Elija el plan de estudios.'));
        $ciclo = $this->entero($datos, 'ciclo', __('Indique el ciclo del estudiante.'));

        if (! DB::table('careers')->where('id', $carreraId)->exists()) {
            throw ValidationException::withMessages(['carrera' => __('La carrera elegida no existe.')]);
        }
        if (! DB::table('study_plans')->where('id', $planId)->where('career_id', $carreraId)->exists()) {
            throw ValidationException::withMessages(['plan' => __('El plan de estudios no pertenece a la carrera elegida.')]);
        }
        if ($ciclo < 1 || $ciclo > self::CICLO_MAXIMO) {
            throw ValidationException::withMessages(['ciclo' => __('El ciclo debe estar entre 1 y :maximo.', ['maximo' => self::CICLO_MAXIMO])]);
        }

        $this->fijarCarne($usuarioId, $datos['carne'] ?? null);

        $activa = DB::table('student_careers')->where('student_id', $usuarioId)->where('status', 'active')
            ->first(['id', 'career_id', 'study_plan_id', 'current_cycle']);

        if ($activa !== null && (int) $activa->career_id === $carreraId) {
            if ((int) $activa->study_plan_id !== $planId || (int) $activa->current_cycle !== $ciclo) {
                DB::table('student_careers')->where('id', $activa->id)
                    ->update(['study_plan_id' => $planId, 'current_cycle' => $ciclo, 'updated_at' => now()]);
            }

            return;
        }

        if ($activa !== null) {
            // En este orden por el índice único parcial: una sola carrera activa por estudiante.
            DB::table('student_careers')->where('id', $activa->id)->update(['status' => 'changed', 'updated_at' => now()]);
        }

        DB::table('student_careers')->insert([
            'student_id' => $usuarioId, 'career_id' => $carreraId, 'study_plan_id' => $planId, 'current_cycle' => $ciclo,
            'admission_period_id' => null, 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Carné que corresponde al correo institucional: la parte antes de la @, en minúsculas
     * (ll22017@ues.edu.sv → ll22017). Null si no cabe en los 20 caracteres del carné.
     */
    public static function carneDeCorreo(string $correo): ?string
    {
        $carne = mb_strtolower(trim(Str::before(trim($correo), '@')));

        return $carne === '' || mb_strlen($carne) > 20 ? null : $carne;
    }

    /**
     * El carné sale del correo; solo se escribe a mano si el correo no da uno válido.
     */
    private function fijarCarne(int $usuarioId, mixed $carne): void
    {
        $carne = mb_strtolower(trim((string) $carne));
        $actual = DB::table('student_profiles')->where('user_id', $usuarioId)->value('student_code');

        if ($actual !== null) {
            if ($carne !== '' && $carne !== mb_strtolower((string) $actual)) {
                throw ValidationException::withMessages(['carne' => __('El carné no se puede modificar.')]);
            }

            return;
        }

        $delCorreo = self::carneDeCorreo((string) DB::table('users')->where('id', $usuarioId)->value('institutional_email'));
        if ($delCorreo !== null) {
            if ($carne !== '' && $carne !== $delCorreo) {
                throw ValidationException::withMessages(['carne' => __('El carné es el del correo institucional: :carne.', ['carne' => $delCorreo])]);
            }
            $carne = $delCorreo;
        }

        if ($carne === '') {
            throw ValidationException::withMessages(['carne' => __('Ingrese el carné del estudiante.')]);
        }
        if (mb_strlen($carne) > 20) {
            throw ValidationException::withMessages(['carne' => __('El carné no puede tener más de 20 caracteres.')]);
        }
        if (DB::table('student_profiles')->whereRaw('lower(student_code) = ?', [$carne])->exists()) {
            throw ValidationException::withMessages(['carne' => __('Ese carné ya pertenece a otro estudiante.')]);
        }

        DB::table('student_profiles')->insert(['user_id' => $usuarioId, 'student_code' => $carne, 'created_at' => now(), 'updated_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function entero(array $datos, string $clave, string $mensaje): int
    {
        $valor = $datos[$clave] ?? null;
        if ($valor === null || $valor === '' || filter_var($valor, FILTER_VALIDATE_INT) === false) {
            throw ValidationException::withMessages([$clave => $mensaje]);
        }

        return (int) $valor;
    }

    /**
     * Las vigencias son fechas, no instantes (misma convención que teaching_assignments).
     */
    private function hoy(): string
    {
        return now()->toDateString();
    }
}
