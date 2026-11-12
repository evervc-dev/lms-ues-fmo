<?php

namespace App\Modules\Academica\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Choques de horario (RF08, RF09): de aula, de docente y de estudiante. En PostgreSQL el EXCLUDE de
 * section_schedules también impide el choque de aula; aquí se valida antes para dar un mensaje claro.
 */
class HorarioService
{
    /**
     * Bloques de una o varias secciones.
     *
     * @param  array<int, int>  $secciones
     * @return Collection<int, object>
     */
    public function bloques(array $secciones): Collection
    {
        return DB::table('section_schedules')->whereIn('section_id', $secciones)->get(['section_id', 'weekday', 'start_time', 'end_time', 'classroom_id']);
    }

    /**
     * ¿Algún bloque de A se cruza con alguno de B?
     *
     * @param  Collection<int, object>  $a
     * @param  Collection<int, object>  $b
     */
    public function seCruzan(Collection $a, Collection $b): bool
    {
        return $a->contains(fn ($x) => $b->contains(fn ($y) => (int) $x->weekday === (int) $y->weekday
            && substr($x->start_time, 0, 5) < substr($y->end_time, 0, 5) && substr($y->start_time, 0, 5) < substr($x->end_time, 0, 5)));
    }

    public function chocaAula(int $periodoId, ?int $aulaId, int $dia, string $inicio, string $fin, ?int $excepto = null): ?string
    {
        if ($aulaId === null) {
            return null;
        }

        return DB::table('section_schedules')->join('sections', 'sections.id', '=', 'section_schedules.section_id')
            ->join('subjects', 'subjects.id', '=', 'sections.subject_id')
            ->where('section_schedules.period_id', $periodoId)->where('classroom_id', $aulaId)->where('weekday', $dia)
            ->where('start_time', '<', $fin)->where('end_time', '>', $inicio)
            ->when($excepto, fn ($q) => $q->where('section_schedules.section_id', '!=', $excepto))
            ->value(DB::raw("subjects.code || ' · ' || sections.code"));
    }

    /**
     * Sección del docente que choca con la sección dada, si hay.
     */
    public function chocaDocente(int $docenteId, int $seccionId): ?string
    {
        $periodo = DB::table('sections')->where('id', $seccionId)->value('period_id');
        $otras = DB::table('teaching_assignments')->join('sections', 'sections.id', '=', 'teaching_assignments.section_id')
            ->where('teacher_id', $docenteId)->whereNull('ended_at')->where('sections.period_id', $periodo)
            ->where('sections.id', '!=', $seccionId)->pluck('sections.id')->all();

        return $this->primeraQueChoca($seccionId, $otras);
    }

    /**
     * Sección donde el estudiante ya está matriculado que choca con la dada, si hay.
     */
    public function chocaEstudiante(int $estudianteId, int $seccionId, ?int $ignorarSeccion = null): ?string
    {
        $periodo = DB::table('sections')->where('id', $seccionId)->value('period_id');
        $otras = DB::table('enrollments')->where('student_id', $estudianteId)->where('status', 'active')->where('period_id', $periodo)
            ->when($ignorarSeccion, fn ($q) => $q->where('section_id', '!=', $ignorarSeccion))
            ->pluck('section_id')->all();

        return $this->primeraQueChoca($seccionId, $otras);
    }

    /**
     * @param  array<int, int>  $otras
     */
    private function primeraQueChoca(int $seccionId, array $otras): ?string
    {
        if ($otras === []) {
            return null;
        }
        $propios = $this->bloques([$seccionId]);
        foreach ($this->bloques($otras)->groupBy('section_id') as $otra => $bloques) {
            if ($this->seCruzan($propios, $bloques)) {
                return (string) DB::table('sections')->join('subjects', 'subjects.id', '=', 'sections.subject_id')
                    ->where('sections.id', $otra)->value(DB::raw("subjects.code || ' · ' || sections.code"));
            }
        }

        return null;
    }
}
