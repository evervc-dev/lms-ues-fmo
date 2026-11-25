<?php

namespace App\Modules\Academica\Services;

use App\Models\User;
use App\Support\Alcance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Panel académico del Administrador (RF07): departamento → carreras → materias del plan → grupos teóricos del
 * período, con enlace a la Vista de sección en solo lectura (ADR-075). Solo lectura: el alta, la edición, el archivo
 * y el borrado de departamentos están en CatalogoService (Catálogo › Estructura).
 *
 * Un departamento archivado (`is_active = false`, ADR-076) conserva su historial, pero deja de ofrecerse a docentes,
 * estudiantes y listas de trabajo: los demás Services lo filtran con activos().
 *
 * Todo exige `asignaturas.gestionar` con alcance G (el Administrador) en este Service (ADR-026).
 */
class DepartamentoService
{
    public const PERMISO = 'asignaturas.gestionar';

    public function __construct(private Alcance $alcance) {}

    /**
     * Ids de los departamentos activos, como subconsulta para `whereIn(... department_id, DepartamentoService::activos())`.
     */
    public static function activos(): Builder
    {
        return DB::table('departments')->where('is_active', true)->select('id');
    }

    /**
     * Todos los departamentos, archivados incluidos, con sus conteos de carreras, asignaturas y secciones en
     * períodos sin cerrar (estas últimas impiden archivarlo).
     *
     * @return Collection<int, \stdClass>
     */
    public function listar(User $actor): Collection
    {
        $this->exigir($actor);

        return DB::table('departments as d')
            ->select('d.id', 'd.code', 'd.name', 'd.is_active',
                DB::raw('(select count(*) from careers c where c.department_id = d.id) as carreras'),
                DB::raw('(select count(*) from subjects s where s.department_id = d.id) as asignaturas'),
                DB::raw("(select count(*) from sections x join subjects s on s.id = x.subject_id join academic_periods p on p.id = x.period_id where s.department_id = d.id and p.status <> 'closed') as secciones"))
            ->orderBy('d.code')->get()
            ->each(function (object $departamento): void {
                $departamento->id = (int) $departamento->id;
                $departamento->is_active = (bool) $departamento->is_active;
                $departamento->carreras = (int) $departamento->carreras;
                $departamento->asignaturas = (int) $departamento->asignaturas;
                $departamento->secciones = (int) $departamento->secciones;
            });
    }

    /**
     * Un departamento con sus carreras, sus planes y cuántas materias y estudiantes activos tiene cada una.
     *
     * @return array{departamento: object, carreras: Collection<int, \stdClass>}
     */
    public function carreras(User $actor, int $departamentoId): array
    {
        $this->exigir($actor);
        $departamento = DB::table('departments')->where('id', $departamentoId)->first(['id', 'code', 'name', 'is_active']) ?? abort(404);
        $departamento->id = (int) $departamento->id;
        $departamento->is_active = (bool) $departamento->is_active;

        $carreras = DB::table('careers as c')->where('c.department_id', $departamentoId)
            ->select('c.id', 'c.code', 'c.name',
                DB::raw('(select count(distinct cs.subject_id) from career_subjects cs join study_plans sp on sp.id = cs.study_plan_id where sp.career_id = c.id) as materias'),
                DB::raw("(select count(*) from student_careers sc where sc.career_id = c.id and sc.status = 'active') as estudiantes"))
            ->orderBy('c.code')->get();
        $planes = DB::table('study_plans')->whereIn('career_id', $carreras->pluck('id'))->orderBy('name')->get(['career_id', 'name'])
            ->groupBy(fn (\stdClass $plan) => (int) $plan->career_id);
        $carreras->each(function (\stdClass $carrera) use ($planes): void {
            $carrera->id = (int) $carrera->id;
            $carrera->materias = (int) $carrera->materias;
            $carrera->estudiantes = (int) $carrera->estudiantes;
            $carrera->planes = ($planes[$carrera->id] ?? collect())->pluck('name')->all();
        });

        return ['departamento' => $departamento, 'carreras' => $carreras];
    }

    /**
     * Materias de los planes de estudio de la carrera, por ciclo (una materia de dos planes sale una vez, con el
     * ciclo más temprano). Con `$departamentoId`, una carrera de otro departamento da 404.
     *
     * @return array{carrera: object, materias: Collection<int, \stdClass>}
     */
    public function materiasDeCarrera(User $actor, int $carreraId, ?int $departamentoId = null): array
    {
        $this->exigir($actor);
        $carrera = DB::table('careers as c')->join('departments as d', 'd.id', '=', 'c.department_id')->where('c.id', $carreraId)
            ->first(['c.id', 'c.code', 'c.name', 'c.department_id', 'd.code as departamentoCodigo', 'd.name as departamento']) ?? abort(404);
        if ($departamentoId !== null && (int) $carrera->department_id !== $departamentoId) {
            abort(404);
        }
        $carrera->id = (int) $carrera->id;
        $carrera->department_id = (int) $carrera->department_id;

        $materias = DB::table('career_subjects as cs')->join('study_plans as sp', 'sp.id', '=', 'cs.study_plan_id')
            ->join('subjects as s', 's.id', '=', 'cs.subject_id')
            ->where('sp.career_id', $carreraId)
            ->groupBy('s.id', 's.code', 's.name', 's.credits', 's.is_active')
            ->orderByRaw('min(cs.cycle)')->orderBy('s.code')
            ->get(['s.id', 's.code', 's.name', 's.credits', 's.is_active', DB::raw('min(cs.cycle) as ciclo')])
            ->each(function (object $materia): void {
                $materia->id = (int) $materia->id;
                $materia->is_active = (bool) $materia->is_active;
                $materia->ciclo = $materia->ciclo === null ? null : (int) $materia->ciclo;
            });

        return ['carrera' => $carrera, 'materias' => $materias];
    }

    /**
     * Secciones de la asignatura en el período (por defecto, el que está en curso), con su docente responsable,
     * sus matriculados activos y el ulid para enlazar a la Vista de sección.
     *
     * @return array{asignatura: object, periodo: ?object, secciones: Collection<int, \stdClass>}
     */
    public function seccionesDeMateria(User $actor, int $asignaturaId, ?int $periodoId = null): array
    {
        $this->exigir($actor);
        $asignatura = DB::table('subjects')->where('id', $asignaturaId)->first(['id', 'code', 'name']) ?? abort(404);
        $asignatura->id = (int) $asignatura->id;
        $periodoId ??= PeriodoService::activoId();
        $periodo = $periodoId === null ? null : DB::table('academic_periods')->where('id', $periodoId)->first(['id', 'name', 'status']);
        if ($periodo === null) {
            return ['asignatura' => $asignatura, 'periodo' => null, 'secciones' => collect()];
        }
        $periodo->id = (int) $periodo->id;

        $secciones = DB::table('sections as s')->where('s.subject_id', $asignaturaId)->where('s.period_id', $periodo->id)
            ->orderBy('s.code')
            ->get(['s.id', 's.ulid', 's.code', 's.max_capacity', 's.status',
                DB::raw("(select count(*) from enrollments e where e.section_id = s.id and e.status = 'active') as inscritos")])
            ->each(function (object $seccion): void {
                $seccion->id = (int) $seccion->id;
                $seccion->inscritos = (int) $seccion->inscritos;
                $seccion->max_capacity = (int) $seccion->max_capacity;
            });

        $docentes = DB::table('teaching_assignments as ta')->join('users as u', 'u.id', '=', 'ta.teacher_id')
            ->whereIn('ta.section_id', $secciones->pluck('id'))->whereNull('ta.ended_at')
            ->orderByDesc('ta.is_responsible')->orderBy('u.last_name')
            ->get(['ta.section_id', DB::raw("u.first_name || ' ' || u.last_name as nombre")])
            ->groupBy(fn (object $fila) => (int) $fila->section_id);

        $secciones->each(fn (object $seccion) => $seccion->docentes = ($docentes[$seccion->id] ?? collect())->pluck('nombre')->all());

        return ['asignatura' => $asignatura, 'periodo' => $periodo, 'secciones' => $secciones];
    }

    /**
     * Períodos para elegir de cuál se ven las secciones, el más reciente primero.
     *
     * @return Collection<int, \stdClass>
     */
    public function periodos(User $actor): Collection
    {
        $this->exigir($actor);

        return DB::table('academic_periods')->orderByDesc('start_date')->get(['id', 'name', 'status'])
            ->each(fn (object $periodo) => $periodo->id = (int) $periodo->id);
    }

    /**
     * Solo el Administrador (alcance G): el Jefe también tiene `asignaturas.gestionar`, pero con D (ADR-072).
     *
     * @throws AuthorizationException
     */
    private function exigir(User $actor): void
    {
        if ($this->alcance->exigir($actor, self::PERMISO) !== 'G') {
            throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        }
    }
}
