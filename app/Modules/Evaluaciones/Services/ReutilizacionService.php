<?php

namespace App\Modules\Evaluaciones\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Copia de evaluaciones de un período anterior (RF14, ADR-066). Cada tarea y cada cuestionario llega «sin programar»:
 * sin fechas ni cómputo, invisible para el estudiante hasta que el docente lo programa (ActividadService::programar).
 * La rúbrica se copia con su tarea (ADR-065); el banco de un cuestionario se comparte con el docente si no es suyo.
 * No se copian entregas, intentos, notas, equipos ni prórrogas.
 */
class ReutilizacionService
{
    public function __construct(private RubricaService $rubricas) {}

    /**
     * Copia las evaluaciones de la sección de origen a la de destino. Va dentro de la transacción de
     * ContenidoService::importar(), que ya validó el permiso y el origen. Devuelve cuántas copió.
     */
    public function copiar(User $docente, int $origenId, int $destinoId): int
    {
        /** @var array<int, true> $accesibles bancos propios o compartidos con el docente */
        $accesibles = DB::table('question_banks')->where('owner_id', $docente->id)->pluck('id')
            ->merge(DB::table('question_bank_shares')->where('user_id', $docente->id)->pluck('bank_id'))
            ->mapWithKeys(fn ($id) => [(int) $id => true])->all();
        $copiadas = 0;

        foreach (DB::table('assessments')->where('section_id', $origenId)->orderBy('id')->get() as $a) {
            $rubrica = $a->rubric_id === null ? null : $this->rubricas->insertar($docente->id, $a->title, $this->rubricas->criterios((int) $a->rubric_id)
                ->map(fn (\stdClass $c) => ['nombre' => $c->name, 'maximo' => (float) $c->max_points])->all());
            $id = DB::table('assessments')->insertGetId(['ulid' => (string) Str::ulid(), 'section_id' => $destinoId, 'grade_item_id' => null, 'assessment_type' => $a->assessment_type,
                'title' => $a->title, 'instructions' => $a->instructions, 'opens_at' => null, 'closes_at' => null, 'max_attempts' => $a->max_attempts,
                'rubric_id' => $rubrica, 'is_anonymous' => $a->is_anonymous, 'created_at' => now(), 'updated_at' => now()]);

            if ($a->assessment_type === 'assignment') {
                $ajustes = DB::table('assignment_settings')->where('assessment_id', $a->id)->first();
                if ($ajustes !== null) {
                    DB::table('assignment_settings')->insert(['assessment_id' => $id, 'is_group' => $ajustes->is_group, 'late_policy' => $ajustes->late_policy, 'grace_until' => null,
                        'late_penalty_percent' => $ajustes->late_penalty_percent, 'allowed_extensions' => $ajustes->allowed_extensions, 'max_file_mb' => $ajustes->max_file_mb]);
                }
            } else {
                $ajustes = DB::table('quiz_settings')->where('assessment_id', $a->id)->first();
                if ($ajustes !== null) {
                    DB::table('quiz_settings')->insert(['assessment_id' => $id, 'time_limit_minutes' => $ajustes->time_limit_minutes, 'autosave_seconds' => $ajustes->autosave_seconds,
                        'navigation' => $ajustes->navigation, 'review_published' => false]);
                }
                foreach (DB::table('quiz_question_rules')->where('assessment_id', $a->id)->orderBy('id')->get() as $regla) {
                    if (! isset($accesibles[(int) $regla->bank_id])) {
                        DB::table('question_bank_shares')->insertOrIgnore(['bank_id' => $regla->bank_id, 'user_id' => $docente->id]);
                        $accesibles[(int) $regla->bank_id] = true;
                    }
                    DB::table('quiz_question_rules')->insert(['assessment_id' => $id, 'bank_id' => $regla->bank_id, 'topic' => $regla->topic, 'difficulty' => $regla->difficulty,
                        'quantity' => $regla->quantity, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
            $copiadas++;
        }

        return $copiadas;
    }
}
