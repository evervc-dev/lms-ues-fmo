<?php

namespace App\Modules\Identidad\Services;

use App\Models\User;
use App\Support\Alcance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Perfil contextual (RF36, ADR-056): quién ve el perfil de quién y qué ve.
 *
 * Nadie navega un directorio abierto: se ve a otra persona solo si hay un vínculo académico o jerárquico en el
 * período en curso, y cada rol del visitante aporta el suyo (permiso `perfiles.ver`):
 * - Administrador y Soporte (G): a todos.
 * - Jefe (D): a los docentes, estudiantes y coordinadores de su departamento.
 * - Coordinador (C): a los estudiantes de su carrera, a los docentes que imparten materias de su plan y a su Jefe.
 * - Docente (S): a sus estudiantes, a los docentes de su departamento, a su Jefe y a los coordinadores de las
 *   carreras donde imparte.
 * - Estudiante (M): a sus compañeros de sección, a sus docentes y al coordinador de su carrera.
 *
 * El rol que da el vínculo fija el nivel: completo para Administrador, Soporte, Jefe y Coordinador; mínimo para
 * Docente y Estudiante, sin carné, ciclo, matrículas, notas ni accesos.
 */
class PerfilContextualService
{
    public const COMPLETO = 'completo';

    public const MINIMO = 'minimo';

    /** Roles del visitante en orden de nivel: el primero que da el vínculo decide qué se muestra. */
    private const NIVELES = [
        'administrador' => self::COMPLETO,
        'soporte_tecnico' => self::COMPLETO,
        'jefe_departamento' => self::COMPLETO,
        'coordinador' => self::COMPLETO,
        'docente' => self::MINIMO,
        'estudiante' => self::MINIMO,
    ];

    public function __construct(private Alcance $alcance) {}

    /**
     * Perfil de otra persona (o el propio) con los bloques de sus roles al nivel que corresponde al visitante.
     *
     * @return array{usuario: User, nivel: string, roles: array<int, string>, bloques: array<string, array<string, mixed>>}
     *
     * @throws AuthorizationException
     */
    public function perfil(User $actor, int $usuarioId): array
    {
        $this->alcance->exigir($actor, 'perfiles.ver');
        $usuario = User::query()->with('roles')->findOrFail($usuarioId);
        $nivel = $this->nivel($actor, $usuarioId) ?? throw new AuthorizationException(__('No tiene permiso para ver este perfil.'));
        $roles = $usuario->roles->pluck('name')->all();

        return ['usuario' => $usuario, 'nivel' => $nivel, 'roles' => $roles, 'bloques' => $this->bloques($usuarioId, $roles, $nivel === self::COMPLETO)];
    }

    /**
     * Nivel con que el visitante ve a la persona, o null si no hay vínculo. Lo propio se ve completo.
     */
    public function nivel(User $actor, int $usuarioId): ?string
    {
        if ($actor->id === $usuarioId) {
            return self::COMPLETO;
        }
        foreach ($this->rolesConPermiso($actor) as $rol) {
            if ($this->visibles($actor, [$usuarioId], [$rol]) !== []) {
                return self::NIVELES[$rol];
            }
        }

        return null;
    }

    /**
     * De las personas indicadas, las que el visitante puede ver (con cualquiera de sus roles). Sirve para listas,
     * como los participantes de una sección: una consulta en lugar de una por fila.
     *
     * @param  array<int, int>  $ids
     * @param  array<int, string>|null  $roles  Solo estos roles del visitante (por defecto, todos los que tienen el permiso).
     * @return array<int, int>
     */
    public function visibles(User $actor, array $ids, ?array $roles = null): array
    {
        $roles ??= $this->rolesConPermiso($actor);
        if ($ids === [] || $roles === []) {
            return [];
        }
        if (array_intersect($roles, ['administrador', 'soporte_tecnico']) !== []) {
            return array_values(array_map('intval', $ids));
        }

        $visibles = DB::table('users')->whereIn('users.id', $ids)->where(function ($q) use ($actor, $roles) {
            $q->where('users.id', $actor->id);
            foreach ($roles as $rol) {
                foreach ($this->vinculos($actor, $rol) as $subconsulta) {
                    $q->orWhereIn('users.id', $subconsulta);
                }
            }
        })->pluck('users.id')->map(fn ($id) => (int) $id)->all();

        return array_values($visibles);
    }

    /**
     * Bloques del perfil según los roles de la persona visitada (RF36). Con `$completo`, el del estudiante suma
     * plan, ciclo, carné, año de ingreso y materias inscritas.
     *
     * @param  array<int, string>  $roles
     * @return array<string, array<string, mixed>>
     */
    public function bloques(int $usuarioId, array $roles, bool $completo): array
    {
        $bloques = [];

        if (in_array('jefe_departamento', $roles, true)) {
            $bloques['jefe_departamento'] = ['departamentos' => DB::table('department_heads')->join('departments', 'departments.id', '=', 'department_heads.department_id')
                ->where('department_heads.user_id', $usuarioId)->whereNull('department_heads.valid_to')->orderBy('departments.name')->pluck('departments.name')->all()];
        }
        if (in_array('coordinador', $roles, true)) {
            $bloques['coordinador'] = ['carreras' => DB::table('career_coordinators')->join('careers', 'careers.id', '=', 'career_coordinators.career_id')
                ->where('career_coordinators.user_id', $usuarioId)->whereNull('career_coordinators.valid_to')->orderBy('careers.name')->pluck('careers.name')->all()];
        }
        if (in_array('docente', $roles, true)) {
            $bloques['docente'] = $this->bloqueDocente($usuarioId);
        }
        if (in_array('estudiante', $roles, true)) {
            $bloques['estudiante'] = $this->bloqueEstudiante($usuarioId, $completo);
        }

        return $bloques;
    }

    /**
     * Departamento, materias que imparte en el período con su ciclo en cada plan, y las carreras que salen de esos
     * planes: un docente no pertenece a una carrera en el modelo, imparte materias que están en sus planes.
     *
     * @return array{departamento: string|null, carreras: array<int, string>, materias: array<int, object>}
     */
    private function bloqueDocente(int $usuarioId): array
    {
        $materias = DB::table('teaching_assignments')->join('sections', 'sections.id', '=', 'teaching_assignments.section_id')
            ->join('subjects', 'subjects.id', '=', 'sections.subject_id')
            ->where('teaching_assignments.teacher_id', $usuarioId)->whereNull('teaching_assignments.ended_at')
            ->whereIn('sections.id', $this->seccionesEnCurso())
            ->orderBy('subjects.code')->orderBy('sections.code')
            ->get(['subjects.id as asignatura', 'subjects.code', 'subjects.name', 'sections.code as grupo', 'teaching_assignments.is_responsible']);

        $planes = DB::table('career_subjects')->join('study_plans', 'study_plans.id', '=', 'career_subjects.study_plan_id')
            ->join('careers', 'careers.id', '=', 'study_plans.career_id')
            ->whereIn('career_subjects.subject_id', $materias->pluck('asignatura')->unique()->all())
            ->orderBy('careers.code')->get(['career_subjects.subject_id', 'career_subjects.cycle', 'careers.code', 'careers.name']);

        $materias->each(function (object $m) use ($planes) {
            $m->ciclos = $planes->where('subject_id', $m->asignatura)->map(fn (object $p) => __(':carrera · ciclo :ciclo', ['carrera' => $p->code, 'ciclo' => $p->cycle]))->unique()->values()->all();
        });

        return [
            'departamento' => DB::table('teacher_profiles')->join('departments', 'departments.id', '=', 'teacher_profiles.department_id')
                ->where('teacher_profiles.user_id', $usuarioId)->value('departments.name'),
            'carreras' => $planes->pluck('name')->unique()->sort()->values()->all(),
            'materias' => $materias->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bloqueEstudiante(int $usuarioId, bool $completo): array
    {
        $carrera = DB::table('student_careers')->join('careers', 'careers.id', '=', 'student_careers.career_id')
            ->join('study_plans', 'study_plans.id', '=', 'student_careers.study_plan_id')
            ->leftJoin('academic_periods', 'academic_periods.id', '=', 'student_careers.admission_period_id')
            ->where('student_careers.student_id', $usuarioId)->where('student_careers.status', 'active')
            ->first(['careers.name as carrera', 'study_plans.name as plan', 'student_careers.current_cycle as ciclo', 'academic_periods.name as periodo_ingreso', 'academic_periods.start_date as inicio_ingreso']);

        $bloque = ['carrera' => $carrera?->carrera];
        if (! $completo) {
            return $bloque;
        }

        return $bloque + [
            'plan' => $carrera?->plan,
            'ciclo' => $carrera?->ciclo === null ? null : (int) $carrera->ciclo,
            'carne' => DB::table('student_profiles')->where('user_id', $usuarioId)->value('student_code'),
            'ingreso' => $carrera?->inicio_ingreso === null ? null : ['anio' => (int) substr((string) $carrera->inicio_ingreso, 0, 4), 'periodo' => $carrera->periodo_ingreso],
            'materias' => DB::table('enrollments')->join('sections', 'sections.id', '=', 'enrollments.section_id')
                ->join('subjects', 'subjects.id', '=', 'sections.subject_id')
                ->where('enrollments.student_id', $usuarioId)->where('enrollments.status', 'active')
                ->whereIn('sections.id', $this->seccionesEnCurso())
                ->orderBy('subjects.code')->get(['subjects.code', 'subjects.name', 'sections.code as grupo'])->all(),
        ];
    }

    /**
     * Subconsultas de user ids con vínculo para un rol del visitante (las de Administrador y Soporte no hacen falta).
     *
     * @return array<int, Builder>
     */
    private function vinculos(User $actor, string $rol): array
    {
        return match ($rol) {
            'jefe_departamento' => $this->vinculosDeJefe($this->alcance->departamentos($actor)),
            'coordinador' => $this->vinculosDeCoordinador($this->alcance->carreras($actor)),
            'docente' => $this->vinculosDeDocente($actor->id),
            'estudiante' => $this->vinculosDeEstudiante($actor->id),
            default => [],
        };
    }

    /**
     * @param  array<int, int>  $departamentos
     * @return array<int, Builder>
     */
    private function vinculosDeJefe(array $departamentos): array
    {
        return [
            DB::table('teacher_profiles')->whereIn('department_id', $departamentos)->select('user_id'),
            DB::table('student_careers')->join('careers', 'careers.id', '=', 'student_careers.career_id')
                ->whereIn('careers.department_id', $departamentos)->select('student_careers.student_id'),
            DB::table('career_coordinators')->join('careers', 'careers.id', '=', 'career_coordinators.career_id')
                ->whereNull('career_coordinators.valid_to')->whereIn('careers.department_id', $departamentos)->select('career_coordinators.user_id'),
        ];
    }

    /**
     * @param  array<int, int>  $carreras
     * @return array<int, Builder>
     */
    private function vinculosDeCoordinador(array $carreras): array
    {
        $asignaturas = DB::table('career_subjects')->join('study_plans', 'study_plans.id', '=', 'career_subjects.study_plan_id')
            ->whereIn('study_plans.career_id', $carreras)->select('career_subjects.subject_id');

        return [
            DB::table('student_careers')->whereIn('career_id', $carreras)->select('student_id'),
            DB::table('teaching_assignments')->join('sections', 'sections.id', '=', 'teaching_assignments.section_id')
                ->whereNull('teaching_assignments.ended_at')->whereIn('sections.id', $this->seccionesEnCurso())
                ->whereIn('sections.subject_id', $asignaturas)->select('teaching_assignments.teacher_id'),
            DB::table('department_heads')->whereNull('valid_to')
                ->whereIn('department_id', DB::table('careers')->whereIn('id', $carreras)->select('department_id'))->select('user_id'),
        ];
    }

    /**
     * @return array<int, Builder>
     */
    private function vinculosDeDocente(int $docenteId): array
    {
        $misSecciones = DB::table('teaching_assignments')->where('teacher_id', $docenteId)->whereNull('ended_at')
            ->whereIn('section_id', $this->seccionesEnCurso())->select('section_id');
        $miDepartamento = DB::table('teacher_profiles')->where('user_id', $docenteId)->select('department_id');

        return [
            DB::table('enrollments')->where('status', 'active')->whereIn('section_id', $misSecciones)->select('student_id'),
            DB::table('teacher_profiles')->whereIn('department_id', $miDepartamento)->select('user_id'),
            DB::table('department_heads')->whereNull('valid_to')->whereIn('department_id', $miDepartamento)->select('user_id'),
            DB::table('career_coordinators')->whereNull('valid_to')->whereIn('career_id', DB::table('career_subjects')
                ->join('study_plans', 'study_plans.id', '=', 'career_subjects.study_plan_id')
                ->whereIn('career_subjects.subject_id', DB::table('sections')->whereIn('id', $misSecciones)->select('subject_id'))
                ->select('study_plans.career_id'))->select('user_id'),
        ];
    }

    /**
     * @return array<int, Builder>
     */
    private function vinculosDeEstudiante(int $estudianteId): array
    {
        $misSecciones = DB::table('enrollments')->where('student_id', $estudianteId)->where('status', 'active')
            ->whereIn('section_id', $this->seccionesEnCurso())->select('section_id');

        return [
            DB::table('enrollments')->where('status', 'active')->whereIn('section_id', $misSecciones)->select('student_id'),
            DB::table('teaching_assignments')->whereNull('ended_at')->whereIn('section_id', $misSecciones)->select('teacher_id'),
            DB::table('career_coordinators')->whereNull('valid_to')->whereIn('career_id', DB::table('student_careers')
                ->where('student_id', $estudianteId)->where('status', 'active')->select('career_id'))->select('user_id'),
        ];
    }

    /** Secciones del período en curso: el vínculo es el de este ciclo, no el de los anteriores. */
    private function seccionesEnCurso(): Builder
    {
        return DB::table('sections')->join('academic_periods', 'academic_periods.id', '=', 'sections.period_id')
            ->where('academic_periods.status', 'active')->select('sections.id');
    }

    /**
     * Roles del visitante que tienen `perfiles.ver`, en orden de nivel (el Administrador puede quitárselo a un rol, RF03).
     *
     * @return array<int, string>
     */
    private function rolesConPermiso(User $actor): array
    {
        $roles = $actor->roles()->whereHas('permissions', fn ($q) => $q->where('name', 'perfiles.ver'))->pluck('name')->all();

        return array_values(array_filter(array_keys(self::NIVELES), fn (string $rol) => in_array($rol, $roles, true)));
    }
}
