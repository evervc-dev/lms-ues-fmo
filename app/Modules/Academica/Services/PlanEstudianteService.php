<?php

namespace App\Modules\Academica\Services;

use App\Models\User;
use App\Modules\Calificaciones\Services\CalificacionService;
use App\Support\Alcance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mi plan (RF37): el plan de estudios del estudiante por ciclo, con cada asignatura marcada como aprobada (con su nota
 * final), en curso (con su nota actual), reprobada (con la última nota) o pendiente, y sus prerrequisitos.
 *
 * El estado es por asignatura, no por plan: una asignatura aprobada sale aprobada en cualquier plan de la carrera donde
 * esté, así el estudiante que cambió del Plan 98 al Plan 25 ve en los dos lo que ya aprobó. Solo ve su propio plan (P).
 */
class PlanEstudianteService
{
    public function __construct(private Alcance $alcance, private CalificacionService $calificaciones) {}

    /**
     * @return array{
     *     planes: Collection<int, \stdClass>,
     *     plan: ?\stdClass,
     *     ciclos: Collection<int|string, Collection<int, \stdClass>>,
     *     resumen: array{aprobadas: int, total: int, uvAprobadas: int, uvTotal: int, enCurso: int, cum: ?float}
     * }
     */
    public function plan(User $estudiante, ?int $planId = null): array
    {
        $this->alcance->exigir($estudiante, 'plan.ver');

        $carrera = DB::table('student_careers')->where('student_id', $estudiante->id)->where('status', 'active')
            ->orderByDesc('id')->first(['career_id', 'study_plan_id', 'current_cycle']);
        $vacio = ['planes' => collect(), 'plan' => null, 'ciclos' => collect(),
            'resumen' => ['aprobadas' => 0, 'total' => 0, 'uvAprobadas' => 0, 'uvTotal' => 0, 'enCurso' => 0, 'cum' => null]];
        if ($carrera === null) {
            return $vacio;
        }

        // Los planes de su carrera para el selector; el suyo va primero y es el que se ve si no elige otro.
        $planes = DB::table('study_plans')->where('career_id', $carrera->career_id)->orderByDesc('id')->get(['id', 'name'])
            // map y no each: each se detiene cuando la función devuelve false, y la asignación de «no es el suyo» lo es.
            ->map(function (object $p) use ($carrera): object {
                $p->propio = (int) $p->id === (int) $carrera->study_plan_id;

                return $p;
            })
            ->sortByDesc('propio')->values();
        $plan = $planes->firstWhere('id', $planId) ?? $planes->firstWhere('propio', true) ?? $planes->first();
        if ($plan === null) {
            return $vacio;
        }

        $materias = DB::table('career_subjects as cs')->join('subjects as a', 'a.id', '=', 'cs.subject_id')
            ->where('cs.study_plan_id', $plan->id)
            ->orderBy('cs.cycle')->orderBy('a.code')
            ->get(['a.id', 'a.code', 'a.name', 'a.credits', 'cs.cycle']);
        $ids = $materias->pluck('id')->all();

        $prerrequisitos = DB::table('subject_prerequisites')->whereIn('subject_id', $ids)->get(['subject_id', 'prerequisite_id'])
            ->groupBy('subject_id')->map(fn ($filas) => $filas->pluck('prerequisite_id')->map(fn ($id) => (int) $id)->all());
        $codigos = DB::table('subjects')->whereIn('id', $prerrequisitos->flatten()->unique()->all())->pluck('code', 'id');

        [$aprobadas, $reprobadas] = $this->resultados($estudiante->id, $ids);
        $enCurso = $this->enCurso($estudiante, $ids);

        $materias->each(function (object $m) use ($aprobadas, $reprobadas, $enCurso, $prerrequisitos, $codigos, $materias): void {
            $id = (int) $m->id;
            $m->prerrequisitos = $prerrequisitos->get($id, []);
            $m->codigosPrerrequisitos = collect($m->prerrequisitos)->map(fn ($p) => $codigos[$p] ?? null)->filter()->values()->all();
            $m->dependientes = $materias->filter(fn ($o) => in_array($id, $prerrequisitos->get((int) $o->id, []), true))->pluck('id')->map(fn ($i) => (int) $i)->values()->all();
            [$m->estado, $m->nota, $m->evaluado, $m->periodo] = match (true) {
                isset($aprobadas[$id]) => ['aprobada', (float) $aprobadas[$id]->final_score, null, $aprobadas[$id]->periodo],
                isset($enCurso[$id]) => ['en_curso', $enCurso[$id]['nota'], $enCurso[$id]['evaluado'], null],
                isset($reprobadas[$id]) => ['reprobada', (float) $reprobadas[$id]->final_score, null, $reprobadas[$id]->periodo],
                default => ['pendiente', null, null, null],
            };
        });

        $hechas = $materias->where('estado', 'aprobada');
        $uvHechas = (int) $hechas->sum('credits');

        return [
            'planes' => $planes,
            'plan' => $plan,
            'ciclos' => $materias->groupBy('cycle')->sortKeys(),
            'resumen' => [
                'aprobadas' => $hechas->count(),
                'total' => $materias->count(),
                'uvAprobadas' => $uvHechas,
                'uvTotal' => (int) $materias->sum('credits'),
                'enCurso' => $materias->where('estado', 'en_curso')->count(),
                // CUM: promedio de las notas finales aprobadas ponderado por sus unidades valorativas.
                'cum' => $uvHechas > 0 ? CalificacionService::redondear($hechas->sum(fn ($m) => $m->nota * (int) $m->credits) / $uvHechas) : null,
            ],
        ];
    }

    /**
     * Notas finales de los períodos cerrados (enrollment_results): la mejor aprobada de cada asignatura y, para las que
     * aún no aprueba, la última reprobada.
     *
     * @param  array<int, int>  $asignaturas
     * @return array{0: array<int, object>, 1: array<int, object>}
     */
    private function resultados(int $estudianteId, array $asignaturas): array
    {
        $filas = DB::table('enrollment_results as r')->join('enrollments as e', 'e.id', '=', 'r.enrollment_id')
            ->join('academic_periods as p', 'p.id', '=', 'e.period_id')
            ->where('e.student_id', $estudianteId)->whereIn('e.subject_id', $asignaturas)
            ->orderBy('p.start_date')
            ->get(['e.subject_id', 'r.final_score', 'r.result', 'p.name as periodo']);

        $aprobadas = [];
        $reprobadas = [];
        foreach ($filas as $f) {
            $id = (int) $f->subject_id;
            if ($f->result === 'passed') {
                if (! isset($aprobadas[$id]) || (float) $f->final_score > (float) $aprobadas[$id]->final_score) {
                    $aprobadas[$id] = $f;
                }
            } else {
                $reprobadas[$id] = $f;
            }
        }

        return [$aprobadas, $reprobadas];
    }

    /**
     * Lo que cursa en el período en curso, con la nota actual de su boleta: el promedio de lo evaluado y qué parte del
     * curso está evaluada (la misma lectura que en Notas, RF17).
     *
     * @param  array<int, int>  $asignaturas
     * @return array<int, array{nota: ?float, evaluado: float}>
     */
    private function enCurso(User $estudiante, array $asignaturas): array
    {
        $periodo = PeriodoService::activoId();
        if ($periodo === null) {
            return [];
        }

        return DB::table('enrollments')->where('student_id', $estudiante->id)->where('period_id', $periodo)->where('status', 'active')
            ->whereIn('subject_id', $asignaturas)->get(['subject_id', 'section_id'])
            ->mapWithKeys(function (object $m) use ($estudiante) {
                $boleta = $this->calificaciones->boleta($estudiante, (int) $m->section_id);

                return [(int) $m->subject_id => ['nota' => $boleta['evaluado'] > 0 ? $boleta['final'] : null, 'evaluado' => $boleta['evaluado']]];
            })->all();
    }
}
