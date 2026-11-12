<?php

namespace App\Modules\Calificaciones\Services;

use App\Models\User;
use App\Modules\Calificaciones\Events\RevisionResuelta;
use App\Modules\Calificaciones\Events\RevisionSolicitada;
use App\Modules\Calificaciones\Jobs\EvaluarRiesgoAcademico;
use App\Modules\Evaluaciones\Services\ActividadService;
use App\Modules\Evaluaciones\Services\CuestionarioService;
use App\Modules\Evaluaciones\Services\EquipoService;
use App\Modules\Evaluaciones\Services\ProrrogaService;
use App\Support\Alcance;
use App\Support\DbContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Solicitud de revisión de una calificación publicada (RF35, ADR-040; docs/08 Flujos/Flujo Solicitud de revisión.md).
 *
 * El estudiante marca qué revisar (preguntas del intento que cuenta, criterios de la rúbrica o la nota general) y
 * explica por qué; el docente de la sección resuelve ítem por ítem y la nota se recalcula. Una solicitud por
 * actividad, sin retiro, dentro del plazo desde la publicación de la nota (o de la prórroga que dé el docente) y
 * antes de que se publique el acta. El Coordinador las ve en su carrera, sin resolverlas.
 */
class RevisionService
{
    public const ESTADOS = [
        'pending' => 'Pendiente',
        'resolved_changed' => 'Resuelta: nota modificada',
        'resolved_unchanged' => 'Resuelta: sin cambios',
    ];

    public function __construct(private Alcance $alcance, private ActividadService $actividades, private CuestionarioService $cuestionarios, private ProrrogaService $prorrogas, private CalificacionService $calificaciones) {}

    /**
     * Días para solicitar revisión desde que se publica la nota (system_settings.grade_review_days).
     */
    public static function plazoDias(): int
    {
        return (int) (json_decode((string) DB::table('system_settings')->where('key', 'grade_review_days')->value('value'), true) ?? 5);
    }

    /**
     * Lo que ve el estudiante en la actividad: su última solicitud, si puede pedir una y qué puede impugnar.
     *
     * @return array{visible: bool, solicitud?: object|null, items?: Collection<int, object>, noPuede?: string|null, candidatos?: Collection<int, object>, vence?: Carbon|null}
     */
    public function paraEstudiante(User $estudiante, int $seccionId, string $actividadUlid): array
    {
        $matricula = $this->actividades->matricula($estudiante, $seccionId);
        if ($matricula === null || ! $estudiante->checkPermissionTo('calificaciones.revision.solicitar')) {
            return ['visible' => false];
        }
        $actividad = $this->actividad($seccionId, $actividadUlid);
        $solicitud = DB::table('grade_review_requests')->where('enrollment_id', $matricula)->where('assessment_id', $actividad->id)->orderByDesc('id')->first();
        $nota = $this->nota($actividad, $matricula);

        return [
            'visible' => true,
            'solicitud' => $solicitud,
            'items' => $solicitud ? $this->items((int) $solicitud->id) : collect(),
            'noPuede' => $this->motivoNoPuede($actividad, $matricula),
            'candidatos' => $nota ? $this->candidatos($actividad, $matricula) : collect(),
            'vence' => $nota ? $this->vence($actividad, $matricula, $nota) : null,
        ];
    }

    /**
     * @param  array<string, string|null>  $items  clave del candidato («pregunta-ID», «criterio-ID» o «general») => por qué
     *
     * @throws ValidationException
     */
    public function solicitar(User $estudiante, int $seccionId, string $actividadUlid, string $motivo, array $items): string
    {
        $this->alcance->exigir($estudiante, 'calificaciones.revision.solicitar');
        $matricula = $this->actividades->matricula($estudiante, $seccionId) ?? abort(403);
        $actividad = $this->actividad($seccionId, $actividadUlid);

        if ($noPuede = $this->motivoNoPuede($actividad, $matricula)) {
            throw ValidationException::withMessages(['solicitud' => $noPuede]);
        }
        $motivo = trim($motivo);
        $items = array_filter(array_map(fn ($comentario) => trim((string) $comentario), $items), fn (string $comentario) => $comentario !== '');
        $candidatos = $this->candidatos($actividad, $matricula)->keyBy('clave');

        $errores = [];
        if (mb_strlen($motivo) < 10) {
            $errores['motivo'] = __('Explique en al menos 10 caracteres por qué pide la revisión.');
        }
        if ($items === []) {
            $errores['items'] = __('Marque al menos una pregunta, criterio o la nota general, y explique por qué.');
        }
        foreach ($items as $clave => $comentario) {
            if (! $candidatos->has($clave)) {
                abort(422);
            }
            if (mb_strlen($comentario) < 5) {
                $errores["items.{$clave}"] = __('Explique qué quiere que se revise (al menos 5 caracteres).');
            }
        }
        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        $nota = $this->nota($actividad, $matricula);
        $ulid = (string) Str::ulid();
        try {
            DbContext::run(function () use ($actividad, $matricula, $seccionId, $motivo, $items, $candidatos, $nota, $ulid) {
                $solicitudId = DB::table('grade_review_requests')->insertGetId([
                    'ulid' => $ulid, 'section_id' => $seccionId, 'enrollment_id' => $matricula, 'assessment_id' => $actividad->id,
                    'quiz_attempt_id' => $candidatos->first()->intento ?? null, 'status' => 'pending', 'reason' => $motivo,
                    'score_before' => $nota->score, 'requested_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ($items as $clave => $comentario) {
                    $candidato = $candidatos[$clave];
                    DB::table('grade_review_items')->insert([
                        'request_id' => $solicitudId, 'section_id' => $seccionId, 'attempt_answer_id' => $candidato->respuesta, 'rubric_criterion_id' => $candidato->criterio,
                        'student_comment' => $comentario, 'points_before' => $candidato->puntos, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }, 'grade_review.requested');
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['solicitud' => __('Ya solicitó revisión de esta actividad.')]);
        }

        RevisionSolicitada::dispatch($ulid);

        return $ulid;
    }

    /**
     * Solicitudes de la sección para el docente (S) o el Coordinador (C, solo lectura). `$estado` vacío = todas.
     *
     * @return Collection<int, object>
     */
    public function deSeccion(User $actor, int $seccionId, string $estado = 'pending'): Collection
    {
        abort_unless($this->alcance->puedeEnSeccion($actor, 'calificaciones.revision.ver', $seccionId), 403);

        return DB::table('grade_review_requests as r')->join('assessments as a', 'a.id', '=', 'r.assessment_id')
            ->join('enrollments as e', 'e.id', '=', 'r.enrollment_id')->join('users as u', 'u.id', '=', 'e.student_id')
            ->where('r.section_id', $seccionId)->when($estado !== '', fn ($q) => $q->where('r.status', $estado))
            ->orderByRaw("case when r.status = 'pending' then 0 else 1 end")->orderBy('r.requested_at')
            ->get(['r.id', 'r.ulid', 'r.status', 'r.reason', 'r.score_before', 'r.score_after', 'r.requested_at', 'r.resolved_at',
                'a.title', 'a.assessment_type', 'u.first_name', 'u.last_name',
                DB::raw('(select count(*) from grade_review_items i where i.request_id = r.id) as total_items')]);
    }

    /**
     * Detalle para resolver: cada ítem con su contexto (pregunta y respuesta, criterio y niveles, o nota general).
     *
     * @return array{solicitud: object, items: Collection<int, object>, puedeResolver: bool}
     */
    public function detalle(User $actor, int $seccionId, string $ulid): array
    {
        abort_unless($this->alcance->puedeEnSeccion($actor, 'calificaciones.revision.ver', $seccionId), 403);
        $solicitud = DB::table('grade_review_requests as r')->join('assessments as a', 'a.id', '=', 'r.assessment_id')
            ->join('enrollments as e', 'e.id', '=', 'r.enrollment_id')->join('users as u', 'u.id', '=', 'e.student_id')
            ->where('r.section_id', $seccionId)->where('r.ulid', $ulid)
            ->first(['r.*', 'a.title', 'a.assessment_type', 'a.rubric_id', 'u.first_name', 'u.last_name']) ?? abort(404);

        return [
            'solicitud' => $solicitud,
            'items' => $this->items((int) $solicitud->id, conContexto: true),
            'puedeResolver' => $solicitud->status === 'pending' && ! $this->actaPublicada($seccionId)
                && $this->alcance->puedeEnSeccion($actor, 'calificaciones.revision.resolver', $seccionId),
        ];
    }

    /**
     * Resuelve la solicitud ítem por ítem y recalcula la nota en la misma transacción.
     *
     * @param  array<int|string, array{cambiar?: bool|string|null, valor?: float|int|string|null, respuesta?: string|null}>  $decisiones  ítem => decisión
     *
     * @throws ValidationException
     */
    public function resolver(User $docente, int $seccionId, string $ulid, array $decisiones, ?string $respuesta): void
    {
        abort_unless($this->alcance->puedeEnSeccion($docente, 'calificaciones.revision.resolver', $seccionId), 403);
        $detalle = $this->detalle($docente, $seccionId, $ulid);
        $solicitud = $detalle['solicitud'];
        if ($solicitud->status !== 'pending') {
            throw ValidationException::withMessages(['solicitud' => __('Esta solicitud ya fue resuelta.')]);
        }
        if ($this->actaPublicada($seccionId)) {
            throw ValidationException::withMessages(['solicitud' => __('El acta de la sección ya se publicó: la nota solo la corrige el Coordinador.')]);
        }

        $cambios = $this->validarDecisiones($detalle['items'], $decisiones);

        $huboCambios = DbContext::run(function () use ($solicitud, $detalle, $decisiones, $cambios, $respuesta, $docente) {
            // La solicitud se bloquea: si otro docente la resolvió mientras tanto, no se aplica dos veces.
            if (DB::table('grade_review_requests')->where('id', $solicitud->id)->lockForUpdate()->value('status') !== 'pending') {
                throw ValidationException::withMessages(['solicitud' => __('Esta solicitud ya fue resuelta.')]);
            }
            $entrega = $solicitud->assessment_type === 'assignment'
                ? EquipoService::deMatricula(DB::table('submissions')->where('assessment_id', $solicitud->assessment_id), (int) $solicitud->enrollment_id)
                    ->where('status', 'submitted')->orderByDesc('attempt_number')->first()
                : null;
            $general = null;
            $huboCambios = false;

            foreach ($detalle['items'] as $item) {
                $valor = $cambios[$item->id] ?? null;
                $puntosDespues = $item->points_before;
                if ($valor !== null) {
                    if ($item->tipo === 'pregunta') {
                        DB::table('attempt_answers')->where('id', $item->attempt_answer_id)->update(['score' => $valor]);
                        $puntosDespues = $valor;
                    } elseif ($item->tipo === 'criterio') {
                        if ($entrega === null) {
                            throw ValidationException::withMessages(["decisiones.{$item->id}.valor" => __('La tarea no tiene una entrega a la que aplicar la rúbrica.')]);
                        }
                        $nivel = DB::table('rubric_levels')->where('id', (int) $valor)->first(['id', 'points']);
                        DB::table('submission_rubric_scores')->updateOrInsert(['submission_id' => $entrega->id, 'criterion_id' => $item->rubric_criterion_id],
                            ['level_id' => $nivel->id, 'points' => $nivel->points]);
                        $puntosDespues = (float) $nivel->points;
                    } else {
                        $general = (float) $valor;
                        $puntosDespues = $general;
                    }
                }
                $cambio = $valor !== null && ($item->points_before === null || abs((float) $puntosDespues - (float) $item->points_before) > 0.0001);
                $huboCambios = $huboCambios || $cambio;
                DB::table('grade_review_items')->where('id', $item->id)->update([
                    'decision' => $cambio ? 'changed' : 'kept', 'teacher_response' => trim((string) $decisiones[$item->id]['respuesta']),
                    'points_after' => $puntosDespues, 'updated_at' => now(),
                ]);
            }

            $this->recalcularNota($solicitud, $entrega, $huboCambios, $general);
            $notaFinal = (float) DB::table('grades')->join('assessments', 'assessments.grade_item_id', '=', 'grades.grade_item_id')
                ->where('assessments.id', $solicitud->assessment_id)->where('grades.enrollment_id', $solicitud->enrollment_id)->value('grades.score');

            DB::table('grade_review_requests')->where('id', $solicitud->id)->update([
                'status' => $huboCambios && abs($notaFinal - (float) $solicitud->score_before) > 0.0001 ? 'resolved_changed' : 'resolved_unchanged',
                'response' => $respuesta !== null && trim($respuesta) !== '' ? trim($respuesta) : null,
                'score_after' => $notaFinal, 'resolved_by' => $docente->id, 'resolved_at' => now(), 'updated_at' => now(),
            ]);

            return $huboCambios;
        }, 'grade_review.resolved');

        RevisionResuelta::dispatch($ulid);
        // La nota revisada ya estaba publicada: si cambió, se reevalúa el riesgo académico de la sección (RF17).
        if ($huboCambios) {
            EvaluarRiesgoAcademico::dispatch($seccionId);
        }
    }

    /**
     * Solicitudes pendientes por sección del docente, para su bandeja (RF24).
     *
     * @return Collection<int, object>
     */
    public function pendientes(User $docente): Collection
    {
        if ($this->alcance->de($docente, 'calificaciones.revision.resolver') === null) {
            return collect();
        }
        $consulta = DB::table('grade_review_requests as r')->join('sections', 'sections.id', '=', 'r.section_id')->join('subjects', 'subjects.id', '=', 'sections.subject_id')
            ->where('r.status', 'pending')->groupBy('sections.ulid', 'sections.code', 'subjects.code')
            ->select('sections.ulid as seccion', 'sections.code as grupo', 'subjects.code', DB::raw('count(*) as pendientes'), DB::raw('min(r.requested_at) as desde'));

        return $this->alcance->secciones($consulta, $docente, 'calificaciones.revision.resolver', 'r.section_id')->get();
    }

    /**
     * Motivo por el que el estudiante no puede pedir revisión ahora, o null si puede.
     */
    private function motivoNoPuede(object $actividad, int $matricula): ?string
    {
        $nota = $this->nota($actividad, $matricula);
        if ($nota === null) {
            return __('Podrá solicitar revisión cuando se publique la nota.');
        }
        if (DB::table('grade_review_requests')->where('enrollment_id', $matricula)->where('assessment_id', $actividad->id)->exists()) {
            return __('Ya solicitó revisión de esta actividad.');
        }
        if (DB::table('academic_periods')->join('sections', 'sections.period_id', '=', 'academic_periods.id')->where('sections.id', $actividad->section_id)->value('academic_periods.status') === 'closed') {
            return __('El período está cerrado.');
        }
        if ($this->actaPublicada((int) $actividad->section_id)) {
            return __('El acta de la sección ya se publicó.');
        }
        // Un cuestionario se revisa cuando su corrección ya es visible: el cierre más la demora (ADR-040).
        if ($actividad->assessment_type === 'quiz' && ! CuestionarioService::revisionVisible($actividad)) {
            return __('Podrá solicitar revisión desde el :fecha.', ['fecha' => CuestionarioService::revisionDesde($actividad)->timezone('America/El_Salvador')->format('d/m/Y H:i')]);
        }
        $vence = $this->vence($actividad, $matricula, $nota);
        if (now()->gt($vence)) {
            return __('El plazo para solicitar revisión venció el :fecha.', ['fecha' => $vence->timezone('America/El_Salvador')->format('d/m/Y H:i')]);
        }

        return null;
    }

    /**
     * Qué puede impugnar: las preguntas del intento que cuenta (el de mejor nota), los criterios de la rúbrica de su
     * entrega, y siempre la nota general.
     *
     * @return Collection<int, object>
     */
    private function candidatos(object $actividad, int $matricula): Collection
    {
        $candidatos = collect();
        if ($actividad->assessment_type === 'quiz') {
            $intento = DB::table('quiz_attempts')->where('assessment_id', $actividad->id)->where('enrollment_id', $matricula)->where('status', '!=', 'in_progress')
                ->orderByDesc('score')->orderByDesc('attempt_number')->first(['id']);
            // Los puntos por pregunta delatan la corrección: solo se muestran cuando ya es visible (RF16, ADR-040).
            $revisionPublicada = CuestionarioService::revisionVisible($actividad);
            if ($intento) {
                $candidatos = DB::table('attempt_answers as r')->join('questions as p', 'p.id', '=', 'r.question_id')->where('r.attempt_id', $intento->id)->orderBy('r.position')
                    ->get(['r.id', 'r.position', 'r.score', 'p.statement'])
                    ->map(fn ($r) => (object) ['clave' => 'pregunta-'.$r->id, 'etiqueta' => __('Pregunta :n', ['n' => $r->position + 1]).' · '.Str::limit($r->statement, 120),
                        'puntos' => $r->score, 'mostrarPuntos' => $revisionPublicada, 'respuesta' => (int) $r->id, 'criterio' => null, 'intento' => (int) $intento->id]);
            }
        } elseif ($actividad->rubric_id) {
            $entrega = EquipoService::deMatricula(DB::table('submissions')->where('assessment_id', $actividad->id), (int) $matricula)->where('status', 'submitted')->orderByDesc('attempt_number')->value('id');
            if ($entrega) {
                $puntos = DB::table('submission_rubric_scores')->where('submission_id', $entrega)->pluck('points', 'criterion_id');
                $candidatos = DB::table('rubric_criteria')->where('rubric_id', $actividad->rubric_id)->orderBy('position')->get(['id', 'name'])
                    ->map(fn ($c) => (object) ['clave' => 'criterio-'.$c->id, 'etiqueta' => $c->name, 'puntos' => $puntos[$c->id] ?? null, 'mostrarPuntos' => true,
                        'respuesta' => null, 'criterio' => (int) $c->id, 'intento' => null]);
            }
        }

        return $candidatos->push((object) ['clave' => 'general', 'etiqueta' => __('Nota general'), 'puntos' => $this->nota($actividad, $matricula)?->score, 'mostrarPuntos' => true,
            'respuesta' => null, 'criterio' => null, 'intento' => $candidatos->first()->intento ?? null])->values();
    }

    /**
     * @return Collection<int, object>
     */
    private function items(int $solicitudId, bool $conContexto = false): Collection
    {
        $items = DB::table('grade_review_items as i')->leftJoin('attempt_answers as r', 'r.id', '=', 'i.attempt_answer_id')
            ->leftJoin('questions as p', 'p.id', '=', 'r.question_id')->leftJoin('rubric_criteria as c', 'c.id', '=', 'i.rubric_criterion_id')
            ->where('i.request_id', $solicitudId)->orderBy('i.id')
            ->get(['i.*', 'r.position', 'r.answer_text', 'r.selected_option_id', 'r.score as puntos_actuales', 'p.statement', 'p.question_type', 'p.accepted_answers', 'c.name as criterio']);

        return $items->each(function (object $i) use ($conContexto): void {
            $i->tipo = $i->attempt_answer_id ? 'pregunta' : ($i->rubric_criterion_id ? 'criterio' : 'general');
            $i->etiqueta = match ($i->tipo) {
                'pregunta' => __('Pregunta :n', ['n' => $i->position + 1]),
                'criterio' => $i->criterio,
                default => __('Nota general'),
            };
            if (! $conContexto) {
                return;
            }
            if ($i->tipo === 'pregunta') {
                $opciones = DB::table('question_options')->where('question_id', DB::table('attempt_answers')->where('id', $i->attempt_answer_id)->value('question_id'))->orderBy('position')->get(['id', 'text', 'is_correct']);
                $i->respuestaEstudiante = $i->selected_option_id ? $opciones->firstWhere('id', $i->selected_option_id)?->text : $i->answer_text;
                $i->correcta = $opciones->isNotEmpty() ? $opciones->where('is_correct', true)->pluck('text')->join(' · ')
                    : collect(json_decode((string) $i->accepted_answers, true) ?: [])->join(' · ');
            }
            if ($i->tipo === 'criterio') {
                $i->niveles = DB::table('rubric_levels')->where('criterion_id', $i->rubric_criterion_id)->orderByDesc('points')->get(['id', 'name', 'points']);
            }
        });
    }

    /**
     * @param  Collection<int, object>  $items
     * @param  array<int|string, array{cambiar?: bool|string|null, valor?: float|int|string|null, respuesta?: string|null}>  $decisiones
     * @return array<int, float|int> ítem => valor nuevo (solo los que cambian)
     *
     * @throws ValidationException
     */
    private function validarDecisiones(Collection $items, array $decisiones): array
    {
        $errores = [];
        $cambios = [];
        foreach ($items as $item) {
            $decision = $decisiones[$item->id] ?? [];
            if (mb_strlen(trim((string) ($decision['respuesta'] ?? ''))) < 3) {
                $errores["decisiones.{$item->id}.respuesta"] = __('Responda al estudiante sobre este ítem.');
            }
            if (! filter_var($decision['cambiar'] ?? false, FILTER_VALIDATE_BOOL)) {
                continue;
            }
            $valor = $decision['valor'] ?? null;
            $valido = match ($item->tipo) {
                'pregunta' => is_numeric($valor) && (float) $valor >= 0 && (float) $valor <= 1,
                'criterio' => is_numeric($valor) && DB::table('rubric_levels')->where('id', (int) $valor)->where('criterion_id', $item->rubric_criterion_id)->exists(),
                default => is_numeric($valor) && (float) $valor >= 0 && (float) $valor <= 10,
            };
            if (! $valido) {
                $errores["decisiones.{$item->id}.valor"] = match ($item->tipo) {
                    'pregunta' => __('Los puntos de la pregunta van de 0 a 1.'),
                    'criterio' => __('Elija un nivel de este criterio.'),
                    default => __('La nota va de 0 a 10.'),
                };

                continue;
            }
            $cambios[(int) $item->id] = $item->tipo === 'criterio' ? (int) $valor : round((float) $valor, 2);
        }
        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        return $cambios;
    }

    /**
     * Recalcula la nota tras los cambios: el intento (cuestionario) o la suma de la rúbrica con la penalización (tarea).
     * Si el docente fijó la nota general, esa es la nota final de la actividad, sin descuentos (ADR-040).
     */
    private function recalcularNota(object $solicitud, ?object $entrega, bool $huboCambios, ?float $general): void
    {
        if (! $huboCambios) {
            return;
        }
        $nota = DB::table('grades')->join('assessments', 'assessments.grade_item_id', '=', 'grades.grade_item_id')
            ->where('assessments.id', $solicitud->assessment_id)->where('grades.enrollment_id', $solicitud->enrollment_id)->first(['grades.id']);

        if ($solicitud->assessment_type === 'quiz' && $solicitud->quiz_attempt_id) {
            $this->cuestionarios->recalcularIntento((int) $solicitud->quiz_attempt_id);
        }
        $penalizacion = (float) ($entrega->late_penalty_applied ?? 0);
        if ($solicitud->assessment_type === 'assignment' && $entrega && $solicitud->rubric_id && $general === null) {
            $base = (float) DB::table('submission_rubric_scores')->where('submission_id', $entrega->id)->sum('points');
            DB::table('grades')->where('id', $nota->id)->update(['score' => round($base * (1 - $penalizacion / 100), 2), 'score_before_penalty' => $base, 'updated_at' => now()]);
        }
        if ($general !== null) {
            DB::table('grades')->where('id', $nota->id)->update(['score' => round($general, 2), 'score_before_penalty' => $general, 'updated_at' => now()]);
        }
    }

    private function actividad(int $seccionId, string $ulid): object
    {
        return DB::table('assessments as a')->join('grade_items as g', 'g.id', '=', 'a.grade_item_id')->where('a.section_id', $seccionId)->where('a.ulid', $ulid)
            ->whereIn('a.assessment_type', ['assignment', 'quiz'])
            ->first(['a.id', 'a.ulid', 'a.title', 'a.assessment_type', 'a.rubric_id', 'a.section_id', 'a.closes_at', 'g.id as item_id', 'g.is_published']) ?? abort(404);
    }

    /**
     * Fin del plazo para pedir revisión: el mayor entre «publicación + grade_review_days» y la prórroga del docente.
     */
    private function vence(object $actividad, int $matricula, object $nota): Carbon
    {
        $plazo = Carbon::parse($nota->published_at ?? now())->addDays(self::plazoDias());
        $prorroga = $this->prorrogas->hasta($matricula, (int) $actividad->id, 'review');

        return $prorroga && $prorroga->gt($plazo) ? $prorroga : $plazo;
    }

    private function nota(object $actividad, int $matricula): ?object
    {
        return DB::table('grades')->where('grade_item_id', $actividad->item_id)->where('enrollment_id', $matricula)->where('status', 'published')->first(['id', 'score', 'published_at']);
    }

    private function actaPublicada(int $seccionId): bool
    {
        return $this->calificaciones->actaBloqueada($seccionId);
    }
}
