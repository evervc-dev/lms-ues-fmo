<?php

namespace App\Modules\Academica\Services;

use App\Models\User;
use App\Support\Alcance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Estadísticas de oferta académica (RF10): inscritos, aprobados y reprobados por asignatura, y una
 * propuesta de grupos para el próximo período.
 *
 * Fórmula PROVISIONAL (el RF10 la tiene pendiente): demanda = reprobados de la materia + aprobados de
 * su prerrequisito en el último período cerrado; grupos = ⌈demanda ÷ 40⌉.
 */
class OfertaService
{
    public const CUPO_REFERENCIA = 40;

    public function __construct(private Alcance $alcance) {}

    /**
     * Estadísticas de la oferta, con la carrera y el ciclo del plan de cada asignatura.
     *
     * @param  int|null  $carrera  Carrera por la que se filtra; se ignora si queda fuera del alcance del actor.
     * @return array{filas: Collection<int, \stdClass>, cerrado: ?string, totales: array<string, int>, carreras: Collection<int, \stdClass>, carrera: ?int}
     */
    public function estadisticas(User $actor, ?int $carrera = null): array
    {
        $alcance = $this->alcance->exigir($actor, 'oferta.estadisticas.ver');
        $activo = PeriodoService::activoId();
        $cerrado = DB::table('academic_periods')->where('status', 'closed')->orderByDesc('end_date')->first(['id', 'name']);

        $carreras = $this->alcance->carrerasVisibles($actor, 'oferta.estadisticas.ver');
        $carrera = $carreras->contains(fn (\stdClass $c) => (int) $c->id === $carrera) ? $carrera : null;

        $asignaturas = DB::table('subjects')->where('is_active', true)
            ->when($alcance === 'D', fn ($q) => $q->whereIn('department_id', $this->alcance->departamentos($actor)))
            ->when($alcance === 'C', fn ($q) => $q->whereIn('id', DB::table('career_subjects')->join('study_plans', 'study_plans.id', '=', 'career_subjects.study_plan_id')
                ->whereIn('study_plans.career_id', $this->alcance->carreras($actor))->select('career_subjects.subject_id')))
            ->when($carrera !== null, fn ($q) => $q->whereIn('id', $this->alcance->asignaturasDeCarreras([(int) $carrera])))
            ->orderBy('code')->get(['id', 'code', 'name']);
        $ids = $asignaturas->pluck('id')->all();
        $planes = $this->planesPorAsignatura($ids);

        $inscritos = DB::table('enrollments')->where('period_id', $activo)->where('status', 'active')->whereIn('subject_id', $ids)
            ->groupBy('subject_id')->selectRaw('subject_id, count(*) as total')->pluck('total', 'subject_id');
        $resultados = $cerrado ? DB::table('enrollment_results')->join('enrollments', 'enrollments.id', '=', 'enrollment_results.enrollment_id')
            ->where('enrollments.period_id', $cerrado->id)->groupBy('enrollments.subject_id', 'enrollment_results.result')
            ->get(['enrollments.subject_id', 'enrollment_results.result', DB::raw('count(*) as n')])->groupBy('subject_id') : collect();
        $prerrequisitos = DB::table('subject_prerequisites')->whereIn('subject_id', $ids)->get()->groupBy('subject_id');

        $aprobados = fn (int $id) => (int) ($resultados[$id] ?? collect())->firstWhere('result', 'passed')?->n;
        $filas = $asignaturas->map(function ($a) use ($inscritos, $resultados, $prerrequisitos, $aprobados, $planes) {
            $a->planes = ($planes[$a->id] ?? collect())->values();
            $a->inscritos = (int) ($inscritos[$a->id] ?? 0);
            $a->aprobados = $aprobados($a->id);
            $a->reprobados = (int) ($resultados[$a->id] ?? collect())->firstWhere('result', 'failed')?->n;
            $a->demanda = $a->reprobados + ($prerrequisitos[$a->id] ?? collect())->sum(fn ($p) => $aprobados((int) $p->prerequisite_id));
            $a->grupos = (int) ceil($a->demanda / self::CUPO_REFERENCIA);

            return $a;
        });

        return [
            'filas' => $filas,
            'cerrado' => $cerrado === null ? null : (string) $cerrado->name,
            'totales' => ['inscritos' => (int) $filas->sum('inscritos'), 'aprobados' => (int) $filas->sum('aprobados'), 'reprobados' => (int) $filas->sum('reprobados'), 'grupos' => (int) $filas->sum('grupos')],
            'carreras' => $carreras,
            'carrera' => $carrera,
        ];
    }

    /**
     * Carreras y ciclo del plan de cada asignatura, en una sola consulta (una asignatura puede estar en varios planes).
     *
     * @param  array<int, mixed>  $ids
     * @return Collection<int|string, Collection<int, \stdClass>>
     */
    private function planesPorAsignatura(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return DB::table('career_subjects as cs')
            ->join('study_plans as sp', 'sp.id', '=', 'cs.study_plan_id')
            ->join('careers as c', 'c.id', '=', 'sp.career_id')
            ->whereIn('cs.subject_id', $ids)
            ->orderBy('c.code')->orderBy('cs.cycle')
            ->get(['cs.subject_id', 'cs.cycle', 'c.code as carrera_codigo', 'c.name as carrera', 'sp.name as plan'])
            ->groupBy('subject_id');
    }
}
