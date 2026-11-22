<?php

namespace App\Modules\Academica\Services;

use Illuminate\Support\Facades\DB;

/**
 * Pensum (plan de estudios) al que pertenece cada asignatura, para mostrarlo junto a ella en todas las listas: «Plan 98 ·
 * Plan 25». Una asignatura de varias carreras antepone la carrera: «ISI: Plan 98, Plan 25 · IND: Plan 2010». Solo arma
 * rótulos; quien la llama ya exigió el permiso y filtró por alcance.
 */
class PlanesService
{
    /**
     * @param  iterable<int, int|string>  $asignaturas  ids de subjects
     * @param  array<int, int>|null  $carreras  solo los planes de estas carreras (p. ej. la del estudiante)
     * @return array<int, string> subject_id => rótulo
     */
    public function etiquetas(iterable $asignaturas, ?array $carreras = null): array
    {
        $ids = collect($asignaturas)->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($ids === []) {
            return [];
        }

        return DB::table('career_subjects')->join('study_plans', 'study_plans.id', '=', 'career_subjects.study_plan_id')
            ->join('careers', 'careers.id', '=', 'study_plans.career_id')
            ->whereIn('career_subjects.subject_id', $ids)
            ->when($carreras !== null, fn ($q) => $q->whereIn('careers.id', $carreras))
            ->orderBy('careers.code')->orderBy('study_plans.id')
            ->get(['career_subjects.subject_id', 'careers.code as carrera', 'study_plans.name as plan'])
            ->groupBy('subject_id')
            ->map(function ($filas) {
                $porCarrera = $filas->groupBy('carrera')->map(fn ($g) => $g->pluck('plan')->unique()->implode(', '));
                if ($porCarrera->count() === 1) {
                    return $filas->pluck('plan')->unique()->implode(' · ');
                }

                return $porCarrera->map(fn ($planes, $carrera) => "{$carrera}: {$planes}")->implode(' · ');
            })
            ->mapWithKeys(fn ($rotulo, $id) => [(int) $id => $rotulo])
            // En el orden de las asignaturas pedidas: el groupBy sigue el orden de las filas, que PostgreSQL no
            // garantiza (PlanDeEstudiosTest fallaba allí y pasaba en SQLite).
            ->sortBy(fn ($rotulo, $id) => array_search($id, $ids, true))->all();
    }

    /**
     * Rótulos para un estudiante, por código de asignatura: solo los planes de su carrera activa («Plan 25», o
     * «Plan 98 · Plan 25» si la llevan los dos). Para pantallas cuyas filas traen el código y no el id.
     *
     * @param  iterable<int, string>  $codigos
     * @return array<string, string> código => rótulo
     */
    public function delEstudiante(int $estudianteId, iterable $codigos): array
    {
        $porId = DB::table('subjects')->whereIn('code', collect($codigos)->unique()->values()->all())->pluck('code', 'id');
        $etiquetas = $this->etiquetas($porId->keys(), $this->carrerasDelEstudiante($estudianteId));

        return $porId->mapWithKeys(fn ($codigo, $id) => isset($etiquetas[(int) $id]) ? [$codigo => $etiquetas[(int) $id]] : [])->all();
    }

    /**
     * Carreras activas del estudiante, o null si no tiene (entonces no se filtra).
     *
     * @return array<int, int>|null
     */
    public function carrerasDelEstudiante(int $estudianteId): ?array
    {
        $carreras = DB::table('student_careers')->where('student_id', $estudianteId)->where('status', 'active')->pluck('career_id')->map(fn ($id) => (int) $id)->all();

        return $carreras === [] ? null : $carreras;
    }

    public function deAsignatura(int $asignatura, ?array $carreras = null): ?string
    {
        return $this->etiquetas([$asignatura], $carreras)[$asignatura] ?? null;
    }
}
