<?php

namespace App\Modules\Evaluaciones\Services;

use App\Models\User;
use App\Support\Alcance;
use App\Support\DbContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rúbrica de una tarea (RF15, ADR-006, ADR-065). Se arma dentro de la tarea, no en una lista aparte, y solo la llevan
 * las tareas: los exámenes se califican por pregunta. Cada criterio tiene tres niveles (Excelente 100 %, Bueno 70 %
 * y Regular 40 % de su máximo) y los máximos suman 10. Queda fija desde la primera calificación.
 */
class RubricaService
{
    /** @var array<int, array{0: string, 1: float}> nivel y fracción del máximo del criterio */
    public const NIVELES = [['Excelente', 1.0], ['Bueno', 0.7], ['Regular', 0.4]];

    public function __construct(private Alcance $alcance) {}

    /**
     * Los criterios deben ser al menos uno y sus máximos sumar 10.
     *
     * @param  array<int, array{nombre: string, maximo: float}>  $criterios
     *
     * @throws ValidationException
     */
    public function validar(array $criterios): void
    {
        $total = array_sum(array_column($criterios, 'maximo'));
        if ($criterios === [] || abs($total - 10) > 0.001) {
            throw ValidationException::withMessages(['criterios' => __('Los puntos máximos de los criterios deben sumar 10 (suman :t).', ['t' => $total])]);
        }
    }

    /**
     * Inserta la rúbrica con sus criterios y niveles. Va dentro de la transacción de quien la llama.
     *
     * @param  array<int, array{nombre: string, maximo: float}>  $criterios
     */
    public function insertar(int $autorId, string $titulo, array $criterios): int
    {
        $rubrica = DB::table('rubrics')->insertGetId(['owner_id' => $autorId, 'title' => $titulo, 'created_at' => now(), 'updated_at' => now()]);
        foreach (array_values($criterios) as $i => $c) {
            $criterio = DB::table('rubric_criteria')->insertGetId(['rubric_id' => $rubrica, 'name' => $c['nombre'], 'max_points' => $c['maximo'], 'position' => $i, 'created_at' => now(), 'updated_at' => now()]);
            foreach (self::NIVELES as [$nivel, $fraccion]) {
                DB::table('rubric_levels')->insert(['criterion_id' => $criterio, 'name' => $nivel, 'points' => round($c['maximo'] * $fraccion, 2), 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        return $rubrica;
    }

    /**
     * Criterios de la rúbrica, cada uno con sus niveles de mayor a menor.
     *
     * @return Collection<int, \stdClass>
     */
    public function criterios(?int $rubricaId): Collection
    {
        if ($rubricaId === null) {
            return collect();
        }

        return DB::table('rubric_criteria')->where('rubric_id', $rubricaId)->orderBy('position')->get()
            ->each(fn ($c) => $c->niveles = DB::table('rubric_levels')->where('criterion_id', $c->id)->orderByDesc('points')->get());
    }

    /**
     * ¿Se puede agregar, cambiar o quitar la rúbrica? Solo mientras nadie tenga nota en la tarea ni puntaje con esa
     * rúbrica: los datos de demostración comparten una rúbrica entre tareas, así que cuenta cualquiera de ellas.
     */
    public function editable(int $itemId, ?int $rubricaId): bool
    {
        if (DB::table('grades')->where('grade_item_id', $itemId)->exists()) {
            return false;
        }

        return $rubricaId === null || ! DB::table('submission_rubric_scores')->join('rubric_criteria', 'rubric_criteria.id', '=', 'submission_rubric_scores.criterion_id')
            ->where('rubric_criteria.rubric_id', $rubricaId)->exists();
    }

    /**
     * Agrega o reemplaza la rúbrica de la tarea; sin criterios, la quita y la tarea vuelve a nota directa.
     *
     * @param  array<int, array{nombre: string, maximo: float}>  $criterios
     *
     * @throws ValidationException
     */
    public function guardar(User $docente, int $seccionId, string $ulid, array $criterios): void
    {
        abort_unless($this->alcance->puedeEnSeccion($docente, 'tareas.gestionar', $seccionId), 403);
        $tarea = DB::table('assessments')->where('section_id', $seccionId)->where('ulid', $ulid)->where('assessment_type', 'assignment')
            ->first(['id', 'title', 'rubric_id', 'grade_item_id']) ?? abort(404);
        $anterior = $tarea->rubric_id !== null ? (int) $tarea->rubric_id : null;
        if (! $this->editable((int) $tarea->grade_item_id, $anterior)) {
            throw ValidationException::withMessages(['criterios' => __('La tarea ya tiene calificaciones: la rúbrica no se puede cambiar.')]);
        }
        if ($criterios !== []) {
            $this->validar($criterios);
        }

        DbContext::run(function () use ($docente, $tarea, $anterior, $criterios) {
            $nueva = $criterios === [] ? null : $this->insertar($docente->id, $tarea->title, $criterios);
            DB::table('assessments')->where('id', $tarea->id)->update(['rubric_id' => $nueva, 'updated_at' => now()]);
            // La anterior se borra si ninguna otra tarea la usa; sus criterios y niveles caen en cascada.
            if ($anterior !== null && ! DB::table('assessments')->where('rubric_id', $anterior)->exists()) {
                DB::table('rubrics')->where('id', $anterior)->delete();
            }
        }, 'rubric.saved');
    }
}
