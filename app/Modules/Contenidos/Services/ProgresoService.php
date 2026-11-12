<?php

namespace App\Modules\Contenidos\Services;

use App\Models\User;
use App\Support\Alcance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Progreso por unidad (RF18, ADR-067). Un recurso se completa a mano («ya lo leí») o solo, cuando se cumple su regla
 * (`resources.completion_rule`, una por recurso):
 * - `{"type": "opened"}`: al abrirlo.
 * - `{"type": "min_score", "assessment_id": N, "min": 6.0}`: nota publicada del cuestionario igual o mayor.
 * - `{"type": "submission", "assessment_id": N}`: entrega de la tarea (en una grupal, la del equipo).
 * - `{"type": "forum_posts", "thread_id": N, "count": 2}`: mensajes del estudiante en ese hilo del foro.
 *
 * La regla se evalúa cuando ocurre el evento (listeners de Contenidos) y, al fijarla, para quienes ya la cumplen.
 * Un recurso completado no se desmarca aunque la regla deje de cumplirse (decidido por el usuario el 01/11/2026).
 */
class ProgresoService
{
    public const REGLAS = [
        'opened' => 'Al abrirlo',
        'min_score' => 'Nota mínima en un cuestionario',
        'submission' => 'Entrega de una tarea',
        'forum_posts' => 'Mensajes en un hilo del foro',
    ];

    public function __construct(private Alcance $alcance) {}

    /**
     * Valida y normaliza la regla de un recurso de la sección: la evaluación o el hilo deben ser de esa sección.
     *
     * @param  array<string, mixed>  $regla
     * @return array<string, int|float|string>
     *
     * @throws ValidationException
     */
    public function validarRegla(int $seccionId, array $regla): array
    {
        $tipo = (string) ($regla['type'] ?? '');
        // La evaluación debe estar programada: sin cómputo no existe para el estudiante (ADR-066), así que una regla
        // sobre ella sería imposible de cumplir y revelaría su título en la página del recurso.
        $deLaSeccion = fn (string $tipoEvaluacion) => DB::table('assessments')->where('id', (int) ($regla['assessment_id'] ?? 0))
            ->where('section_id', $seccionId)->where('assessment_type', $tipoEvaluacion)->whereNotNull('grade_item_id')->exists();

        return match ($tipo) {
            'opened' => ['type' => 'opened'],
            'min_score' => $deLaSeccion('quiz') && is_numeric($regla['min'] ?? null) && (float) $regla['min'] >= 0 && (float) $regla['min'] <= 10
                ? ['type' => 'min_score', 'assessment_id' => (int) $regla['assessment_id'], 'min' => round((float) $regla['min'], 1)]
                : throw ValidationException::withMessages(['regla' => __('Elija un cuestionario de esta sección y una nota entre 0 y 10.')]),
            'submission' => $deLaSeccion('assignment')
                ? ['type' => 'submission', 'assessment_id' => (int) $regla['assessment_id']]
                : throw ValidationException::withMessages(['regla' => __('Elija una tarea de esta sección.')]),
            'forum_posts' => DB::table('forum_threads')->where('id', (int) ($regla['thread_id'] ?? 0))->where('section_id', $seccionId)->exists()
                && is_numeric($regla['count'] ?? null) && (int) $regla['count'] >= 1 && (int) $regla['count'] <= 20
                ? ['type' => 'forum_posts', 'thread_id' => (int) $regla['thread_id'], 'count' => (int) $regla['count']]
                : throw ValidationException::withMessages(['regla' => __('Elija un hilo del foro de esta sección y de 1 a 20 mensajes.')]),
            default => throw ValidationException::withMessages(['regla' => __('Elija cómo se completa el recurso.')]),
        };
    }

    /**
     * Cuestionarios, tareas e hilos de la sección para armar una regla.
     *
     * @return array{cuestionarios: Collection<int, \stdClass>, tareas: Collection<int, \stdClass>, hilos: Collection<int, \stdClass>}
     */
    public function opciones(int $seccionId): array
    {
        $evaluaciones = DB::table('assessments')->where('section_id', $seccionId)->whereNotNull('grade_item_id')
            ->orderBy('title')->get(['id', 'title', 'assessment_type']);

        return [
            'cuestionarios' => $evaluaciones->where('assessment_type', 'quiz')->values(),
            'tareas' => $evaluaciones->where('assessment_type', 'assignment')->values(),
            'hilos' => DB::table('forum_threads')->where('section_id', $seccionId)->orderBy('title')->get(['id', 'title']),
        ];
    }

    /**
     * La regla en palabras, para el estudiante: qué le falta para completar el recurso.
     *
     * @param  array<string, mixed>|null  $regla
     */
    public function describir(?array $regla): ?string
    {
        // A medio llenar (el asistente de «Nuevo recurso» antes de elegir) no se describe: evita «la evaluación «»».
        if (in_array($regla['type'] ?? null, ['min_score', 'submission'], true) && ! ($regla['assessment_id'] ?? null)) {
            return null;
        }
        if (($regla['type'] ?? null) === 'forum_posts' && ! ($regla['thread_id'] ?? null)) {
            return null;
        }

        return match ($regla['type'] ?? null) {
            'opened' => __('Se completa al abrirlo.'),
            'min_score' => __('Se completa al obtener :n o más en «:e», cuando se publique la nota.', ['n' => number_format((float) $regla['min'], 1), 'e' => $this->titulo((int) $regla['assessment_id'])]),
            'submission' => __('Se completa al entregar la tarea «:e».', ['e' => $this->titulo((int) $regla['assessment_id'])]),
            'forum_posts' => trans_choice('{1} Se completa al escribir 1 mensaje en el hilo «:h» del foro.|[2,*] Se completa al escribir :count mensajes en el hilo «:h» del foro.',
                (int) $regla['count'], ['h' => (string) DB::table('forum_threads')->where('id', (int) $regla['thread_id'])->value('title')]),
            default => null,
        };
    }

    /**
     * Regla guardada de un recurso; las páginas automáticas de antes, sin regla, se completan al abrirlas.
     *
     * @return array<string, mixed>|null
     */
    public static function regla(\stdClass $recurso): ?array
    {
        if ($recurso->completion_mode !== 'automatic') {
            return null;
        }
        $regla = is_string($recurso->completion_rule) ? json_decode($recurso->completion_rule, true) : $recurso->completion_rule;

        return is_array($regla) && isset($regla['type']) ? $regla : ['type' => 'opened'];
    }

    /**
     * Marca el recurso completado para la matrícula. Idempotente.
     */
    public function completar(int $matricula, int $recursoId, string $origen): void
    {
        DB::table('resource_progress')->insertOrIgnore(['resource_id' => $recursoId, 'enrollment_id' => $matricula, 'origin' => $origen, 'completed_at' => now()]);
    }

    /**
     * Evalúa la regla del recurso para las matrículas activas de su sección (o solo las indicadas) y completa a
     * quienes la cumplen. Devuelve cuántas completó.
     *
     * @param  array<int, int>|null  $matriculas
     */
    public function evaluarRecurso(int $recursoId, ?array $matriculas = null): int
    {
        $recurso = DB::table('resources')->join('units', 'units.id', '=', 'resources.unit_id')->where('resources.id', $recursoId)->whereNull('resources.deleted_at')
            ->first(['resources.id', 'resources.completion_mode', 'resources.completion_rule', 'units.section_id']);
        $regla = $recurso ? self::regla($recurso) : null;
        if ($recurso === null || $regla === null) {
            return 0;
        }
        $candidatas = DB::table('enrollments')->where('section_id', $recurso->section_id)->where('status', 'active')
            ->when($matriculas !== null, fn ($q) => $q->whereIn('id', $matriculas))
            ->whereNotExists(fn ($q) => $q->from('resource_progress')->whereColumn('resource_progress.enrollment_id', 'enrollments.id')->where('resource_progress.resource_id', $recursoId))
            ->get(['id', 'student_id']);

        $completadas = 0;
        foreach ($candidatas as $m) {
            if ($this->cumple($regla, (int) $recurso->id, (int) $m->id, (int) $m->student_id)) {
                $this->completar((int) $m->id, (int) $recurso->id, 'rule');
                $completadas++;
            }
        }

        return $completadas;
    }

    /**
     * Evalúa los recursos cuya regla depende de esa evaluación (nota mínima o entrega).
     *
     * @param  array<int, int>|null  $matriculas
     */
    public function evaluarPorEvaluacion(int $evaluacionId, ?array $matriculas = null): void
    {
        $this->recursosConRegla(fn ($r) => in_array($r['type'] ?? null, ['min_score', 'submission'], true) && (int) ($r['assessment_id'] ?? 0) === $evaluacionId,
            DB::table('assessments')->where('id', $evaluacionId)->value('section_id'))
            ->each(fn (int $recurso) => $this->evaluarRecurso($recurso, $matriculas));
    }

    /**
     * Evalúa los recursos cuya regla cuenta mensajes en ese hilo, para el autor del mensaje.
     */
    public function evaluarPorHilo(int $hiloId, int $autorId): void
    {
        $seccion = DB::table('forum_threads')->where('id', $hiloId)->value('section_id');
        $matricula = DB::table('enrollments')->where('section_id', $seccion)->where('student_id', $autorId)->where('status', 'active')->value('id');
        if ($matricula === null) {
            return;
        }
        $this->recursosConRegla(fn ($r) => ($r['type'] ?? null) === 'forum_posts' && (int) ($r['thread_id'] ?? 0) === $hiloId, $seccion)
            ->each(fn (int $recurso) => $this->evaluarRecurso($recurso, [(int) $matricula]));
    }

    /**
     * Avance por unidad de los estudiantes de la sección (RF18): % de recursos completados en cada unidad y en total.
     * El docente ve su sección, el Coordinador las de su carrera y el estudiante solo su fila.
     *
     * @return array{unidades: Collection<int, \stdClass>, estudiantes: Collection<int, \stdClass>}
     */
    public function avance(User $usuario, int $seccionId): array
    {
        abort_unless($this->alcance->puedeEnSeccion($usuario, 'progreso.ver', $seccionId), 403);
        $unidades = DB::table('units')->where('section_id', $seccionId)->whereNull('deleted_at')->orderBy('position')->get(['id', 'title']);
        $recursos = DB::table('resources')->whereIn('unit_id', $unidades->pluck('id'))->whereNull('deleted_at')->get(['id', 'unit_id']);
        $estudiantes = $this->alcance->estudiantes(DB::table('enrollments')->join('users', 'users.id', '=', 'enrollments.student_id'), $usuario, 'progreso.ver', 'enrollments.student_id')
            ->where('enrollments.section_id', $seccionId)->where('enrollments.status', 'active')->orderBy('users.last_name')->orderBy('users.first_name')
            ->get(['enrollments.id', 'users.first_name', 'users.last_name']);
        $hechos = DB::table('resource_progress')->whereIn('enrollment_id', $estudiantes->pluck('id'))->whereIn('resource_id', $recursos->pluck('id'))
            ->get(['enrollment_id', 'resource_id'])->groupBy('enrollment_id');
        $porUnidad = $recursos->groupBy('unit_id');
        $pct = fn (int $hechos, int $total) => $total === 0 ? null : (int) round(100 * $hechos / $total);

        $estudiantes->each(function (\stdClass $e) use ($unidades, $porUnidad, $hechos, $recursos, $pct): void {
            $suyos = $hechos->get($e->id, collect())->pluck('resource_id')->flip();
            $e->unidades = $unidades->mapWithKeys(fn ($u) => [$u->id => $pct($porUnidad->get($u->id, collect())->filter(fn ($r) => $suyos->has($r->id))->count(), $porUnidad->get($u->id, collect())->count())])->all();
            $e->total = $pct($suyos->count(), $recursos->count());
        });

        return ['unidades' => $unidades, 'estudiantes' => $estudiantes];
    }

    /**
     * @param  array<string, mixed>  $regla
     */
    private function cumple(array $regla, int $recursoId, int $matricula, int $estudianteId): bool
    {
        return match ($regla['type']) {
            'opened' => DB::table('resource_access_logs')->where('resource_id', $recursoId)->where('enrollment_id', $matricula)->where('event_type', 'open')->exists(),
            'min_score' => DB::table('grades')->join('assessments', 'assessments.grade_item_id', '=', 'grades.grade_item_id')
                ->where('assessments.id', (int) $regla['assessment_id'])->where('grades.enrollment_id', $matricula)
                ->whereNotNull('grades.published_at')->where('grades.score', '>=', (float) $regla['min'])->exists(),
            'submission' => DB::table('submissions')->where('assessment_id', (int) $regla['assessment_id'])->where('status', 'submitted')
                ->where(fn ($q) => $q->where('enrollment_id', $matricula)
                    ->orWhereIn('team_id', DB::table('team_members')->where('enrollment_id', $matricula)->select('team_id')))->exists(),
            'forum_posts' => DB::table('forum_posts')->where('thread_id', (int) $regla['thread_id'])->where('author_id', $estudianteId)
                ->where('is_hidden', false)->count() >= (int) $regla['count'],
            default => false,
        };
    }

    /**
     * Recursos automáticos de la sección cuya regla cumple la condición.
     *
     * @param  callable(array<string, mixed>): bool  $condicion
     * @return Collection<int, int>
     */
    private function recursosConRegla(callable $condicion, mixed $seccionId): Collection
    {
        return DB::table('resources')->join('units', 'units.id', '=', 'resources.unit_id')->where('units.section_id', $seccionId)
            ->where('resources.completion_mode', 'automatic')->whereNotNull('resources.completion_rule')->whereNull('resources.deleted_at')
            ->get(['resources.id', 'resources.completion_rule'])
            ->filter(fn (\stdClass $r) => $condicion((array) json_decode((string) $r->completion_rule, true)))
            ->map(fn (\stdClass $r) => (int) $r->id)->values();
    }

    private function titulo(int $evaluacionId): string
    {
        return (string) DB::table('assessments')->where('id', $evaluacionId)->value('title');
    }
}
