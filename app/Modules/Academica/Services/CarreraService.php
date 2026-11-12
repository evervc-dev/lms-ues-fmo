<?php

namespace App\Modules\Academica\Services;

use App\Models\User;
use App\Support\Alcance;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Carreras del ámbito del actor (RF07, RF08): el Jefe de Departamento ve las carreras de su
 * departamento y el Coordinador solo la que coordina, con su coordinador vigente, sus docentes y
 * las materias del plan con sus grupos y horarios del período en curso.
 *
 * Solo lectura: exige `carreras.ver` y aplica el alcance en este Service (ADR-026); no escribe,
 * así que no usa transacción ni auditoría.
 */
class CarreraService
{
    /** Permiso de la Matriz RBAC: D para el Jefe, C para el Coordinador. */
    public const PERMISO = 'carreras.ver';

    private const DIAS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

    public function __construct(private Alcance $alcance) {}

    /**
     * Carreras que el actor puede ver, con su coordinador vigente y sus conteos del período en curso.
     *
     * @return Collection<int, object>
     */
    public function listar(User $actor): Collection
    {
        $vision = $this->alcance->exigir($actor, self::PERMISO);
        $periodoId = PeriodoService::activoId();

        $carreras = $this->enAlcance(
            DB::table('careers as c')->join('departments as d', 'd.id', '=', 'c.department_id')
                ->select('c.id', 'c.code', 'c.name', 'd.name as departamento', 'd.code as departamentoCodigo')
                ->orderBy('d.code')->orderBy('c.code'),
            $actor,
            $vision
        )->get()->each(fn (object $carrera) => $carrera->id = (int) $carrera->id);

        $ids = $carreras->pluck('id')->all();
        $coordinadores = $this->coordinadores($ids);
        $materias = $this->materiasPorCarrera($ids);
        $oferta = $this->ofertaPorCarrera($ids, $periodoId);

        return $carreras->each(function (object $carrera) use ($coordinadores, $materias, $oferta): void {
            $carrera->coordinador = $coordinadores[$carrera->id]->nombre ?? null;
            $carrera->materias = (int) ($materias[$carrera->id] ?? 0);
            $carrera->grupos = (int) ($oferta[$carrera->id]->grupos ?? 0);
            $carrera->docentes = (int) ($oferta[$carrera->id]->docentes ?? 0);
        });
    }

    /**
     * Detalle de una carrera del ámbito del actor: coordinador, docentes y materias del plan con
     * sus grupos y horarios del período en curso.
     *
     * @return array{carrera: object, periodo: ?string, planes: array<int, string>, coordinador: ?object,
     *               docentes: Collection<int, object>, materias: Collection<int, object>}
     */
    public function detalle(User $actor, int $carreraId): array
    {
        $vision = $this->alcance->exigir($actor, self::PERMISO);

        $carrera = DB::table('careers as c')->join('departments as d', 'd.id', '=', 'c.department_id')
            ->where('c.id', $carreraId)
            ->first(['c.id', 'c.code', 'c.name', 'd.name as departamento', 'd.code as departamentoCodigo']);

        if ($carrera === null) {
            abort(404);
        }
        if (! $this->enAlcance(DB::table('careers as c')->where('c.id', $carreraId), $actor, $vision)->exists()) {
            abort(403);
        }

        $carrera->id = (int) $carrera->id;
        $periodoId = PeriodoService::activoId();
        $materias = $this->materiasDelPlan($carreraId);
        $secciones = $this->seccionesDe($materias->pluck('id')->all(), $periodoId);
        $docentes = $this->docentesDe($secciones->pluck('id')->all());

        $porMateria = $secciones->each(function (object $seccion) use ($docentes): void {
            $seccion->docentes = ($docentes[$seccion->id] ?? collect())->pluck('nombre')->all();
        })->groupBy('subject_id');

        return [
            'carrera' => $carrera,
            'periodo' => $periodoId === null ? null : DB::table('academic_periods')->where('id', $periodoId)->value('name'),
            'planes' => DB::table('study_plans')->where('career_id', $carreraId)->orderBy('name')->pluck('name')->all(),
            'coordinador' => $this->coordinadores([$carreraId])->first(),
            'docentes' => $docentes->flatten(1)->groupBy(fn (object $fila) => (int) $fila->id)
                ->map(function (Collection $asignaciones): object {
                    $docente = $asignaciones->first();

                    return (object) [
                        'id' => (int) $docente->id,
                        'nombre' => $docente->nombre,
                        'correo' => $docente->institutional_email,
                        'grupos' => $asignaciones->count(),
                    ];
                })->sortBy('nombre')->values(),
            'materias' => $materias->each(function (object $materia) use ($porMateria): void {
                $materia->grupos = ($porMateria[$materia->id] ?? collect())->values();
            }),
        ];
    }

    /**
     * Restringe una consulta de `careers as c` al alcance del permiso: G todas, D las de su
     * departamento y C la que coordina. Un alcance más estrecho (S, M, P) no ve ninguna carrera.
     */
    private function enAlcance(Builder $consulta, User $actor, string $vision): Builder
    {
        return match ($vision) {
            'G' => $consulta,
            'D' => $consulta->whereIn('c.department_id', $this->alcance->departamentos($actor)),
            'C' => $consulta->whereIn('c.id', $this->alcance->carreras($actor)),
            default => $consulta->whereRaw('1 = 0'),
        };
    }

    /**
     * Coordinador vigente de cada carrera (`career_coordinators.valid_to` nulo); si hay más de uno,
     * el de nombramiento más reciente.
     *
     * @param  array<int, int>  $carreras
     * @return Collection<int, object>
     */
    private function coordinadores(array $carreras): Collection
    {
        return DB::table('career_coordinators as cc')->join('users as u', 'u.id', '=', 'cc.user_id')
            ->whereIn('cc.career_id', $carreras)->whereNull('cc.valid_to')
            ->orderByDesc('cc.valid_from')
            ->get(['cc.career_id', 'cc.valid_from as desde', 'u.id', 'u.institutional_email as correo',
                DB::raw("u.first_name || ' ' || u.last_name as nombre")])
            ->groupBy(fn (object $fila) => (int) $fila->career_id)
            ->map(fn (Collection $filas) => $filas->first());
    }

    /**
     * Cuántas materias distintas tienen los planes de cada carrera.
     *
     * @param  array<int, int>  $carreras
     * @return Collection<int, int>
     */
    private function materiasPorCarrera(array $carreras): Collection
    {
        return DB::table('career_subjects as cs')->join('study_plans as sp', 'sp.id', '=', 'cs.study_plan_id')
            ->whereIn('sp.career_id', $carreras)->groupBy('sp.career_id')
            ->get(['sp.career_id', DB::raw('count(distinct cs.subject_id) as materias')])
            ->mapWithKeys(fn (object $fila) => [(int) $fila->career_id => (int) $fila->materias]);
    }

    /**
     * Grupos y docentes distintos de cada carrera en el período en curso.
     *
     * @param  array<int, int>  $carreras
     * @return Collection<int, object>
     */
    private function ofertaPorCarrera(array $carreras, ?int $periodoId): Collection
    {
        if ($periodoId === null) {
            return collect();
        }

        return DB::table('career_subjects as cs')->join('study_plans as sp', 'sp.id', '=', 'cs.study_plan_id')
            ->join('sections as s', 's.subject_id', '=', 'cs.subject_id')
            ->leftJoin('teaching_assignments as ta', fn ($union) => $union->on('ta.section_id', '=', 's.id')->whereNull('ta.ended_at'))
            ->whereIn('sp.career_id', $carreras)->where('s.period_id', $periodoId)
            ->groupBy('sp.career_id')
            ->get(['sp.career_id', DB::raw('count(distinct s.id) as grupos'), DB::raw('count(distinct ta.teacher_id) as docentes')])
            ->groupBy(fn (object $fila) => (int) $fila->career_id)
            ->map(fn (Collection $filas) => $filas->first());
    }

    /**
     * Materias de los planes de estudio de la carrera, por ciclo. Una materia de dos planes aparece
     * una vez, con el ciclo más temprano.
     *
     * @return Collection<int, object>
     */
    private function materiasDelPlan(int $carreraId): Collection
    {
        return DB::table('career_subjects as cs')->join('study_plans as sp', 'sp.id', '=', 'cs.study_plan_id')
            ->join('subjects as s', 's.id', '=', 'cs.subject_id')
            ->where('sp.career_id', $carreraId)
            ->groupBy('s.id', 's.code', 's.name', 's.credits', 's.is_active')
            ->orderByRaw('min(cs.cycle)')->orderBy('s.code')
            ->get(['s.id', 's.code', 's.name', 's.credits', 's.is_active', DB::raw('min(cs.cycle) as ciclo')])
            ->each(fn (object $materia) => $materia->id = (int) $materia->id);
    }

    /**
     * Grupos del período en curso de esas materias, con su cupo, sus matriculados y su horario.
     *
     * @param  array<int, int>  $materias
     * @return Collection<int, object>
     */
    private function seccionesDe(array $materias, ?int $periodoId): Collection
    {
        if ($materias === [] || $periodoId === null) {
            return collect();
        }

        $secciones = DB::table('sections as s')->whereIn('s.subject_id', $materias)->where('s.period_id', $periodoId)
            ->orderBy('s.code')
            ->get(['s.id', 's.ulid', 's.code as grupo', 's.subject_id', 's.max_capacity', 's.status',
                DB::raw("(select count(*) from enrollments e where e.section_id = s.id and e.status = 'active') as inscritos")])
            ->each(function (object $seccion): void {
                $seccion->id = (int) $seccion->id;
                $seccion->subject_id = (int) $seccion->subject_id;
            });

        $horarios = DB::table('section_schedules as h')->leftJoin('classrooms as a', 'a.id', '=', 'h.classroom_id')
            ->whereIn('h.section_id', $secciones->pluck('id'))->orderBy('h.weekday')->orderBy('h.start_time')
            ->get(['h.section_id', 'h.weekday', 'h.start_time', 'h.end_time', 'a.code as aula'])
            ->groupBy(fn (object $fila) => (int) $fila->section_id);

        return $secciones->each(function (object $seccion) use ($horarios): void {
            $seccion->horario = ($horarios[$seccion->id] ?? collect())->map(fn (object $h) => self::DIAS[(int) $h->weekday]
                .' '.substr($h->start_time, 0, 5).'–'.substr($h->end_time, 0, 5)
                .($h->aula ? ' · '.$h->aula : ''))->all();
        });
    }

    /**
     * Docentes vigentes de cada grupo, el responsable primero.
     *
     * @param  array<int, int>  $secciones
     * @return Collection<int, Collection<int, object>>
     */
    private function docentesDe(array $secciones): Collection
    {
        if ($secciones === []) {
            return collect();
        }

        return DB::table('teaching_assignments as ta')->join('users as u', 'u.id', '=', 'ta.teacher_id')
            ->whereIn('ta.section_id', $secciones)->whereNull('ta.ended_at')
            ->orderByDesc('ta.is_responsible')->orderBy('u.last_name')
            ->get(['ta.section_id', 'ta.is_responsible', 'u.id', 'u.institutional_email',
                DB::raw("u.first_name || ' ' || u.last_name as nombre")])
            ->groupBy(fn (object $fila) => (int) $fila->section_id);
    }
}
