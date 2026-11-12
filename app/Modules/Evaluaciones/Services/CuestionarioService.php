<?php

namespace App\Modules\Evaluaciones\Services;

use App\Models\User;
use App\Modules\Calificaciones\Jobs\EvaluarRiesgoAcademico;
use App\Modules\Calificaciones\Services\CalificacionService;
use App\Modules\Evaluaciones\Events\IntentoCalificado;
use App\Support\Alcance;
use App\Support\DbContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Cuestionarios (RF16, ADR-009; Flujo Resolución de cuestionario) y bancos de preguntas.
 * Cuatro tipos: opción múltiple, verdadero/falso, respuesta corta y ensayo. El tiempo lo controla el
 * servidor (deadline_at); las respuestas se guardan solas; los ensayos los califica el docente.
 */
class CuestionarioService
{
    public const TIPOS = ['multiple_choice' => 'Opción múltiple', 'true_false' => 'Verdadero / falso', 'short_answer' => 'Respuesta corta', 'essay' => 'Ensayo'];

    public function __construct(private Alcance $alcance, private ActividadService $actividades, private CalificacionService $calificaciones) {}

    /**
     * Bancos propios y compartidos conmigo.
     *
     * @return Collection<int, object>
     */
    public function bancos(User $docente): Collection
    {
        $this->alcance->exigir($docente, 'banco-preguntas.gestionar');

        return DB::table('question_banks as b')->leftJoin('subjects', 'subjects.id', '=', 'b.subject_id')->join('users as u', 'u.id', '=', 'b.owner_id')
            ->where(fn ($q) => $q->where('b.owner_id', $docente->id)->orWhereIn('b.id', DB::table('question_bank_shares')->where('user_id', $docente->id)->select('bank_id')))
            ->select('b.*', 'subjects.code', DB::raw("u.first_name || ' ' || u.last_name as dueno"), DB::raw('(select count(*) from questions q where q.bank_id = b.id) as preguntas'))
            ->orderBy('b.name')->get();
    }

    public function crearBanco(User $docente, string $nombre, ?int $asignaturaId): int
    {
        $this->alcance->exigir($docente, 'banco-preguntas.gestionar');

        return DbContext::run(fn () => DB::table('question_banks')->insertGetId(['owner_id' => $docente->id, 'subject_id' => $asignaturaId, 'name' => $nombre, 'created_at' => now(), 'updated_at' => now()]), 'question_bank.created');
    }

    /**
     * @return array{banco: object, preguntas: Collection<int, object>, esDueno: bool, compartidoCon: Collection<int, object>}
     */
    public function banco(User $docente, int $bancoId): array
    {
        $banco = $this->bancos($docente)->firstWhere('id', $bancoId) ?? abort(404);
        $preguntas = DB::table('questions')->where('bank_id', $bancoId)->orderBy('id')->get()
            ->each(fn ($p) => $p->opciones = DB::table('question_options')->where('question_id', $p->id)->orderBy('position')->get(['text', 'is_correct']));

        return [
            'banco' => $banco,
            'preguntas' => $preguntas,
            'esDueno' => (int) $banco->owner_id === $docente->id,
            'compartidoCon' => DB::table('question_bank_shares')->join('users', 'users.id', '=', 'question_bank_shares.user_id')->where('bank_id', $bancoId)
                ->get(['users.id', 'users.first_name', 'users.last_name']),
        ];
    }

    /**
     * @param  array{tipo: string, enunciado: string, tema?: string|null, dificultad?: string|null, opciones?: array<int, string>, correcta?: int|string|null, aceptadas?: string|null, justificacion?: string|null}  $datos
     */
    public function agregarPregunta(User $docente, int $bancoId, array $datos): void
    {
        $this->banco($docente, $bancoId);
        $opciones = match ($datos['tipo']) {
            'true_false' => [__('Verdadero'), __('Falso')],
            'multiple_choice' => array_values(array_filter(array_map('trim', $datos['opciones'] ?? []))),
            default => [],
        };
        if ($datos['tipo'] === 'multiple_choice' && count($opciones) < 2) {
            throw ValidationException::withMessages(['opciones' => __('Escriba al menos dos opciones.')]);
        }
        $correcta = (int) ($datos['correcta'] ?? 0);
        if ($opciones !== [] && ! isset($opciones[$correcta])) {
            throw ValidationException::withMessages(['correcta' => __('Marque la opción correcta.')]);
        }

        DbContext::run(function () use ($bancoId, $datos, $opciones, $correcta) {
            $aceptadas = array_values(array_filter(array_map('trim', explode(',', (string) ($datos['aceptadas'] ?? '')))));
            $id = DB::table('questions')->insertGetId(['bank_id' => $bancoId, 'question_type' => $datos['tipo'], 'statement' => $datos['enunciado'], 'topic' => $datos['tema'] ?? null,
                'difficulty' => $datos['dificultad'] ?? null, 'accepted_answers' => $datos['tipo'] === 'short_answer' ? json_encode($aceptadas) : null,
                'justification' => $datos['justificacion'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($opciones as $i => $texto) {
                DB::table('question_options')->insert(['question_id' => $id, 'text' => $texto, 'is_correct' => $i === $correcta, 'position' => $i, 'created_at' => now(), 'updated_at' => now()]);
            }
        }, 'question.created');
    }

    public function compartir(User $docente, int $bancoId, int $colegaId): void
    {
        $datos = $this->banco($docente, $bancoId);
        abort_unless($datos['esDueno'], 403);
        DB::table('teacher_profiles')->where('user_id', $colegaId)->exists() || throw ValidationException::withMessages(['colega' => __('La persona no es docente.')]);
        DbContext::run(fn () => DB::table('question_bank_shares')->insertOrIgnore(['bank_id' => $bancoId, 'user_id' => $colegaId]), 'question_bank.shared');
    }

    /**
     * @param  array{componente: int, titulo: string, instrucciones?: string|null, abre: string, cierra: string, peso: float, minutos: int, intentos: int, navegacion: string, banco: int, cantidad: int, tema?: string|null}  $datos
     */
    public function crearCuestionario(User $docente, int $seccionId, array $datos): string
    {
        abort_unless($this->alcance->puedeEnSeccion($docente, 'cuestionarios.gestionar', $seccionId), 403);
        $this->bancos($docente)->contains('id', $datos['banco']) || abort(403);
        $this->actividades->validarPeso((int) $datos['componente'], $seccionId, (float) $datos['peso']);
        $disponibles = DB::table('questions')->where('bank_id', $datos['banco'])->when($datos['tema'] ?? null, fn ($q, $t) => $q->where('topic', $t))->count();
        if ($disponibles < $datos['cantidad']) {
            throw ValidationException::withMessages(['cantidad' => __('El banco solo tiene :n preguntas con ese filtro.', ['n' => $disponibles])]);
        }
        if (Carbon::parse($datos['cierra'])->lte(Carbon::parse($datos['abre']))) {
            throw ValidationException::withMessages(['cierra' => __('El cierre debe ser posterior a la apertura.')]);
        }

        return DbContext::run(function () use ($seccionId, $datos) {
            $item = DB::table('grade_items')->insertGetId(['component_id' => $datos['componente'], 'section_id' => $seccionId, 'item_type' => 'quiz', 'title' => $datos['titulo'],
                'weight_percent' => $datos['peso'], 'is_published' => false, 'created_at' => now(), 'updated_at' => now()]);
            $ulid = (string) Str::ulid();
            $id = DB::table('assessments')->insertGetId(['ulid' => $ulid, 'section_id' => $seccionId, 'grade_item_id' => $item, 'assessment_type' => 'quiz', 'title' => $datos['titulo'],
                'instructions' => $datos['instrucciones'] ?? null, 'opens_at' => $datos['abre'], 'closes_at' => $datos['cierra'], 'max_attempts' => max(1, $datos['intentos']),
                'is_anonymous' => false, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('quiz_settings')->insert(['assessment_id' => $id, 'time_limit_minutes' => $datos['minutos'], 'autosave_seconds' => 30, 'navigation' => $datos['navegacion'], 'review_published' => false]);
            DB::table('quiz_question_rules')->insert(['assessment_id' => $id, 'bank_id' => $datos['banco'], 'topic' => $datos['tema'] ?? null, 'quantity' => $datos['cantidad'], 'created_at' => now(), 'updated_at' => now()]);

            return $ulid;
        }, 'assessment.created');
    }

    /**
     * @return array<string, mixed>
     */
    public function cuestionario(User $usuario, int $seccionId, string $ulid): array
    {
        abort_unless($this->alcance->puedeEnSeccion($usuario, 'contenidos.ver', $seccionId), 403);
        $c = DB::table('assessments as a')->join('quiz_settings as s', 's.assessment_id', '=', 'a.id')->join('grade_items as g', 'g.id', '=', 'a.grade_item_id')
            ->where('a.section_id', $seccionId)->where('a.ulid', $ulid)->where('a.assessment_type', 'quiz')
            ->first(['a.*', 's.time_limit_minutes', 's.navigation', 's.review_published', 'g.weight_percent', 'g.is_published', 'g.id as item_id']) ?? abort(404);
        $matricula = $this->actividades->matricula($usuario, $seccionId);
        $gestiona = $this->alcance->puedeEnSeccion($usuario, 'cuestionarios.gestionar', $seccionId);
        $estado = $this->actividades->estado($c);

        return [
            'cuestionario' => $c,
            'estado' => $estado,
            'aciertos' => $gestiona && $estado === 'cerrada' ? $this->aciertos((int) $c->id) : collect(),
            'preguntas' => (int) DB::table('quiz_question_rules')->where('assessment_id', $c->id)->sum('quantity'),
            'misIntentos' => $matricula ? DB::table('quiz_attempts')->where('assessment_id', $c->id)->where('enrollment_id', $matricula)->orderBy('attempt_number')->get() : collect(),
            'esEstudiante' => $matricula !== null,
            'gestiona' => $gestiona,
            'revisionVisible' => self::revisionVisible($c),
            'revisionDesde' => self::revisionDesde($c),
        ];
    }

    /**
     * Porcentaje de aciertos por pregunta en los intentos enviados (CP-RF16-05), de la más difícil a la más fácil.
     * Acierto = la respuesta obtuvo el valor completo de la pregunta; los ensayos cuentan cuando ya tienen nota.
     *
     * @return Collection<int, object>
     */
    public function aciertos(int $cuestionarioId): Collection
    {
        return DB::table('attempt_answers as r')->join('quiz_attempts as q', 'q.id', '=', 'r.attempt_id')->join('questions as p', 'p.id', '=', 'r.question_id')
            ->where('q.assessment_id', $cuestionarioId)->whereNotNull('q.submitted_at')->whereNotNull('r.score')
            ->groupBy('p.id', 'p.statement', 'p.question_type')
            ->selectRaw('p.id, p.statement, p.question_type, count(*) as respuestas, sum(case when r.score >= 1 then 1 else 0 end) as aciertos')
            ->get()
            ->each(function (object $p): void {
                $p->porcentaje = (int) round(100 * (int) $p->aciertos / max(1, (int) $p->respuestas));
            })
            ->sortBy([['porcentaje', 'asc'], ['id', 'asc']])->values();
    }

    /**
     * Inicia (o retoma) el intento: sortea las preguntas según las reglas y fija el límite en el servidor.
     */
    public function iniciar(User $estudiante, int $seccionId, string $ulid): string
    {
        $this->alcance->exigir($estudiante, 'cuestionarios.resolver');
        $datos = $this->cuestionario($estudiante, $seccionId, $ulid);
        $c = $datos['cuestionario'];
        $matricula = $this->actividades->matricula($estudiante, $seccionId) ?? abort(403);

        if ($abierto = $datos['misIntentos']->firstWhere('status', 'in_progress')) {
            return $abierto->ulid;
        }
        if ($datos['estado'] !== 'abierta') {
            throw ValidationException::withMessages(['intento' => __('El cuestionario no está abierto.')]);
        }
        if ($datos['misIntentos']->count() >= (int) $c->max_attempts) {
            throw ValidationException::withMessages(['intento' => __('Ya usó sus :n intentos.', ['n' => $c->max_attempts])]);
        }

        return DbContext::run(function () use ($c, $matricula, $datos) {
            $inicio = now();
            $limite = $inicio->copy()->addMinutes((int) $c->time_limit_minutes);
            $ulid = (string) Str::ulid();
            $intento = DB::table('quiz_attempts')->insertGetId(['ulid' => $ulid, 'assessment_id' => $c->id, 'enrollment_id' => $matricula, 'attempt_number' => $datos['misIntentos']->count() + 1,
                'started_at' => $inicio, 'deadline_at' => $limite->min(Carbon::parse($c->closes_at)), 'status' => 'in_progress', 'created_at' => now(), 'updated_at' => now()]);
            $posicion = 0;
            foreach (DB::table('quiz_question_rules')->where('assessment_id', $c->id)->get() as $regla) {
                $ids = DB::table('questions')->where('bank_id', $regla->bank_id)->when($regla->topic, fn ($q, $t) => $q->where('topic', $t))
                    ->when($regla->difficulty, fn ($q, $d) => $q->where('difficulty', $d))->pluck('id')->shuffle()->take((int) $regla->quantity);
                foreach ($ids as $id) {
                    DB::table('attempt_answers')->insert(['attempt_id' => $intento, 'question_id' => $id, 'position' => $posicion++]);
                }
            }

            return $ulid;
        }, 'quiz_attempt.started');
    }

    /**
     * @return array{intento: object, cuestionario: object, preguntas: Collection<int, object>, segundos: int}
     */
    public function intento(User $estudiante, string $ulid): array
    {
        $intento = DB::table('quiz_attempts as q')->join('enrollments as e', 'e.id', '=', 'q.enrollment_id')->where('q.ulid', $ulid)
            ->first(['q.*', 'e.student_id', 'e.section_id']) ?? abort(404);
        abort_unless((int) $intento->student_id === $estudiante->id, 403);
        $this->vencerSiCorresponde($intento);

        $cuestionario = DB::table('assessments as a')->join('quiz_settings as s', 's.assessment_id', '=', 'a.id')->join('sections', 'sections.id', '=', 'a.section_id')
            ->join('subjects', 'subjects.id', '=', 'sections.subject_id')->where('a.id', $intento->assessment_id)
            ->first(['a.id', 'a.ulid', 'a.title', 'a.closes_at', 's.navigation', 's.review_published', 'subjects.code', 'sections.ulid as seccion']);
        $cuestionario->revision_visible = self::revisionVisible($cuestionario);
        $preguntas = DB::table('attempt_answers as r')->join('questions as p', 'p.id', '=', 'r.question_id')->where('r.attempt_id', $intento->id)->orderBy('r.position')
            ->get(['r.id as respuesta_id', 'r.position', 'r.selected_option_id', 'r.answer_text', 'r.score', 'p.id', 'p.question_type', 'p.statement', 'p.justification']);
        $opciones = DB::table('question_options')->whereIn('question_id', $preguntas->pluck('id'))->orderBy('position')->get(['id', 'question_id', 'text', 'is_correct'])->groupBy('question_id');
        $preguntas->each(fn ($p) => $p->opciones = ($opciones[$p->id] ?? collect())->values());

        return [
            'intento' => DB::table('quiz_attempts')->where('id', $intento->id)->first(),
            'cuestionario' => $cuestionario,
            'preguntas' => $preguntas,
            'segundos' => max(0, (int) now()->diffInSeconds(Carbon::parse($intento->deadline_at), false)),
        ];
    }

    /**
     * Guarda una respuesta del intento propio. Solo se escribe si el intento sigue en curso y dentro del límite, en
     * la misma sentencia: una corrección simultánea (docente o job) no queda con una respuesta posterior (ADR-042).
     */
    public function guardarRespuesta(User $estudiante, string $ulid, int $respuestaId, ?int $opcion, ?string $texto): void
    {
        $respuesta = DB::table('attempt_answers as r')->join('quiz_attempts as q', 'q.id', '=', 'r.attempt_id')->join('enrollments as e', 'e.id', '=', 'q.enrollment_id')
            ->where('r.id', $respuestaId)->where('q.ulid', $ulid)->first(['r.question_id', 'q.id as intento', 'q.status', 'q.deadline_at', 'e.student_id']) ?? abort(404);
        abort_unless((int) $respuesta->student_id === $estudiante->id, 403);
        if ($opcion !== null && ! DB::table('question_options')->where('id', $opcion)->where('question_id', $respuesta->question_id)->exists()) {
            abort(422);
        }

        $guardada = DB::table('attempt_answers')->where('id', $respuestaId)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('quiz_attempts')->whereColumn('quiz_attempts.id', 'attempt_answers.attempt_id')
                ->where('quiz_attempts.status', 'in_progress')->where('quiz_attempts.deadline_at', '>=', now()))
            ->update(['selected_option_id' => $opcion, 'answer_text' => $texto === null ? null : mb_substr($texto, 0, 10000), 'saved_at' => now()]);
        if ($guardada === 0) {
            if ($respuesta->status === 'in_progress') {
                $this->corregir((int) $respuesta->intento, soloSiVencio: true, evento: 'quiz_attempt.expired');
            }
            throw ValidationException::withMessages(['intento' => __('El intento ya terminó.')]);
        }
    }

    /**
     * Termina el intento: corrige opción múltiple, V/F y respuesta corta; los ensayos quedan pendientes.
     * La nota (0–10) se guarda como borrador hasta que el docente publica.
     */
    public function terminar(User $estudiante, string $ulid): void
    {
        $datos = $this->intento($estudiante, $ulid);
        if ($datos['intento']->status === 'in_progress') {
            $this->corregir((int) $datos['intento']->id);
        }
    }

    /**
     * Minutos tras el cierre en que la corrección se muestra sola y se aceptan solicitudes de revisión (ADR-040).
     */
    public static function demoraRevision(): int
    {
        return (int) (json_decode((string) DB::table('system_settings')->where('key', 'quiz_review_delay_minutes')->value('value'), true) ?? 10);
    }

    /** Minutos máximos que el docente puede dar después del cierre del cuestionario (ADR-042). */
    public const TIEMPO_EXTRA_MAXIMO = 30;

    /**
     * Fin efectivo del cuestionario: su cierre o el límite del último intento aún en curso con tiempo extra, el que
     * sea mayor. Los intentos ya enviados no retrasan la corrección.
     */
    public static function finEfectivo(object $cuestionario): Carbon
    {
        $cierre = Carbon::parse($cuestionario->closes_at);
        $ultimo = DB::table('quiz_attempts')->where('assessment_id', $cuestionario->id)->where('status', 'in_progress')->max('deadline_at');

        return $ultimo !== null && Carbon::parse($ultimo)->gt($cierre) ? Carbon::parse($ultimo) : $cierre;
    }

    /**
     * Momento desde el que se ve la corrección: el fin efectivo más la demora. Así nadie la ve mientras otro
     * estudiante sigue respondiendo con tiempo extra (ADR-040, ADR-042).
     */
    public static function revisionDesde(object $cuestionario): Carbon
    {
        return self::finEfectivo($cuestionario)->addMinutes(self::demoraRevision());
    }

    public static function revisionVisible(object $cuestionario): bool
    {
        return now()->gte(self::revisionDesde($cuestionario));
    }

    /**
     * Recalcula la nota del intento y la de la actividad tras cambiar los puntos de una respuesta (RF35).
     * No abre transacción: se llama dentro del DbContext::run() de quien cambia los puntos.
     */
    public function recalcularIntento(int $intentoId): void
    {
        $this->registrarNota($intentoId);
    }

    /**
     * Cierra y corrige los intentos cuyo tiempo venció sin que el estudiante los enviara (cerró el navegador, perdió
     * la conexión). Lo corre el job CerrarIntentosVencidos cada minuto; idempotente.
     */
    public function cerrarVencidos(): int
    {
        $vencidos = DB::table('quiz_attempts')->where('status', 'in_progress')->where('deadline_at', '<', now())->pluck('id');
        $cerrados = 0;
        foreach ($vencidos as $id) {
            // Un intento que falla (p. ej. período cerrado) no detiene a los demás.
            try {
                DbContext::system(fn () => $this->corregir((int) $id, soloSiVencio: true, evento: 'quiz_attempt.expired'));
                $cerrados++;
            } catch (Throwable $excepcion) {
                report($excepcion);
            }
        }

        return $cerrados;
    }

    /**
     * Monitor del docente (RF16, ADR-042): cada estudiante de la sección con su intento más reciente.
     *
     * @return array{cuestionario: object, filas: Collection<int, object>, resumen: array{sin_empezar: int, en_curso: int, enviados: int}, finEfectivo: Carbon, topeExtra: Carbon, revisionDesde: Carbon, enVivo: bool}
     */
    public function monitor(User $docente, int $seccionId, string $ulid): array
    {
        abort_unless($this->alcance->puedeEnSeccion($docente, 'cuestionarios.gestionar', $seccionId), 403);
        $c = $this->cuestionarioDeSeccion($seccionId, $ulid);

        $intentos = DB::table('quiz_attempts')->where('assessment_id', $c->id)->orderBy('attempt_number')->get()->keyBy('enrollment_id');
        $respondidas = DB::table('attempt_answers')->join('quiz_attempts', 'quiz_attempts.id', '=', 'attempt_answers.attempt_id')
            ->where('quiz_attempts.assessment_id', $c->id)->where(fn ($q) => $q->whereNotNull('attempt_answers.selected_option_id')->orWhere('attempt_answers.answer_text', '!=', ''))
            ->groupBy('attempt_answers.attempt_id')->selectRaw('attempt_answers.attempt_id, count(*) as total')->pluck('total', 'attempt_id');
        $totales = DB::table('attempt_answers')->join('quiz_attempts', 'quiz_attempts.id', '=', 'attempt_answers.attempt_id')
            ->where('quiz_attempts.assessment_id', $c->id)->groupBy('attempt_answers.attempt_id')->selectRaw('attempt_answers.attempt_id, count(*) as total')->pluck('total', 'attempt_id');

        $filas = DB::table('enrollments as e')->join('users as u', 'u.id', '=', 'e.student_id')->where('e.section_id', $seccionId)->where('e.status', 'active')
            ->orderBy('u.last_name')->orderBy('u.first_name')->get(['e.id as matricula', 'u.first_name', 'u.last_name'])
            ->each(function (object $f) use ($intentos, $respondidas, $totales): void {
                $i = $intentos[$f->matricula] ?? null;
                $f->intento = $i;
                $f->estado = $i === null ? 'sin_empezar' : ($i->status === 'in_progress' ? 'en_curso' : 'enviado');
                $f->segundos = $i && $i->status === 'in_progress' ? max(0, (int) now()->diffInSeconds(Carbon::parse($i->deadline_at), false)) : null;
                $f->respondidas = $i ? (int) ($respondidas[$i->id] ?? 0) : 0;
                $f->preguntas = $i ? (int) ($totales[$i->id] ?? 0) : 0;
            });

        $resumen = ['sin_empezar' => $filas->where('estado', 'sin_empezar')->count(), 'en_curso' => $filas->where('estado', 'en_curso')->count(), 'enviados' => $filas->where('estado', 'enviado')->count()];
        $fin = self::finEfectivo($c);

        return [
            'cuestionario' => $c,
            'filas' => $filas,
            'resumen' => $resumen,
            'finEfectivo' => $fin,
            'topeExtra' => Carbon::parse($c->closes_at)->addMinutes(self::TIEMPO_EXTRA_MAXIMO),
            'revisionDesde' => $fin->copy()->addMinutes(self::demoraRevision()),
            // Se actualiza solo mientras el cuestionario está abierto o queda alguien respondiendo.
            'enVivo' => $resumen['en_curso'] > 0 || now()->between(Carbon::parse($c->opens_at), Carbon::parse($c->closes_at)),
        ];
    }

    /**
     * El docente cierra un intento en curso: se corrige y se envía con lo guardado.
     */
    public function cerrarIntento(User $docente, int $seccionId, string $cuestionarioUlid, string $intentoUlid): void
    {
        $intento = $this->intentoDelMonitor($docente, $seccionId, $cuestionarioUlid, $intentoUlid);
        if ($intento->status !== 'in_progress') {
            throw ValidationException::withMessages(['intento' => __('El intento ya terminó.')]);
        }

        $this->corregir((int) $intento->id, evento: 'quiz_attempt.closed_by_teacher');
    }

    /**
     * Da minutos extra a intentos en curso: los indicados o, sin lista, todos los de la actividad. El límite nuevo
     * nunca pasa de TIEMPO_EXTRA_MAXIMO minutos después del cierre (ADR-042). Devuelve cuántos se ampliaron.
     *
     * @param  array<int, string>|null  $intentos  ulids; null = todos los intentos en curso
     */
    public function darTiempo(User $docente, int $seccionId, string $cuestionarioUlid, ?array $intentos, int $minutos): int
    {
        abort_unless($this->alcance->puedeEnSeccion($docente, 'cuestionarios.gestionar', $seccionId), 403);
        $c = $this->cuestionarioDeSeccion($seccionId, $cuestionarioUlid);
        if ($minutos < 1 || $minutos > 60) {
            throw ValidationException::withMessages(['minutos' => __('Dé entre 1 y 60 minutos.')]);
        }
        if ($intentos === []) {
            throw ValidationException::withMessages(['minutos' => __('Elija al menos un estudiante con el intento en curso.')]);
        }
        $tope = Carbon::parse($c->closes_at)->addMinutes(self::TIEMPO_EXTRA_MAXIMO);

        return DbContext::run(function () use ($c, $intentos, $minutos, $tope) {
            $enCurso = DB::table('quiz_attempts')->where('assessment_id', $c->id)->where('status', 'in_progress')
                ->when($intentos !== null, fn ($q) => $q->whereIn('ulid', $intentos))->lockForUpdate()->get(['id', 'deadline_at']);
            $ampliados = 0;
            foreach ($enCurso as $i) {
                // Si el intento ya venció (el job aún no lo cerró), los minutos cuentan desde ahora.
                $nuevo = Carbon::parse($i->deadline_at)->max(now())->addMinutes($minutos)->min($tope);
                if ($nuevo->gt(Carbon::parse($i->deadline_at))) {
                    DB::table('quiz_attempts')->where('id', $i->id)->update(['deadline_at' => $nuevo, 'updated_at' => now()]);
                    $ampliados++;
                }
            }

            return $ampliados;
        }, 'quiz_attempt.extended');
    }

    /**
     * Datos mínimos del cuestionario para el monitor y sus acciones (se consultan cada 15 s).
     */
    private function cuestionarioDeSeccion(int $seccionId, string $ulid): object
    {
        return DB::table('assessments')->where('section_id', $seccionId)->where('ulid', $ulid)->where('assessment_type', 'quiz')
            ->first(['id', 'ulid', 'title', 'opens_at', 'closes_at']) ?? abort(404);
    }

    private function intentoDelMonitor(User $docente, int $seccionId, string $cuestionarioUlid, string $intentoUlid): object
    {
        abort_unless($this->alcance->puedeEnSeccion($docente, 'cuestionarios.gestionar', $seccionId), 403);
        $c = $this->cuestionarioDeSeccion($seccionId, $cuestionarioUlid);

        return DB::table('quiz_attempts')->where('assessment_id', $c->id)->where('ulid', $intentoUlid)->first() ?? abort(404);
    }

    public function calificarEnsayo(User $docente, int $respuestaId, float $puntos): void
    {
        $respuesta = DB::table('attempt_answers as r')->join('quiz_attempts as q', 'q.id', '=', 'r.attempt_id')->join('assessments as a', 'a.id', '=', 'q.assessment_id')
            ->where('r.id', $respuestaId)->first(['r.id', 'q.id as intento', 'a.section_id']) ?? abort(404);
        abort_unless($this->alcance->puedeEnSeccion($docente, 'calificaciones.gestionar', (int) $respuesta->section_id), 403);
        $this->calificaciones->exigirActaAbierta((int) $respuesta->section_id);
        if ($puntos < 0 || $puntos > 1) {
            throw ValidationException::withMessages(['puntos' => __('Califique el ensayo entre 0 y 1 (proporción del valor de la pregunta).')]);
        }
        DbContext::run(function () use ($respuesta, $puntos) {
            DB::table('attempt_answers')->where('id', $respuesta->id)->update(['score' => $puntos]);
            $this->registrarNota((int) $respuesta->intento);
        }, 'quiz_essay.graded');
    }

    private function vencerSiCorresponde(object $intento): void
    {
        if ($intento->status === 'in_progress' && now()->gt(Carbon::parse($intento->deadline_at))) {
            $this->corregir((int) $intento->id, soloSiVencio: true, evento: 'quiz_attempt.expired');
        }
    }

    /**
     * Corrige y envía el intento. Bajo bloqueo: el estudiante, el docente o el job pueden cerrarlo a la vez y se
     * corrige una sola vez. Con `$soloSiVencio`, vuelve a comprobar el límite: si el docente dio minutos extra
     * mientras tanto, no se cierra (ADR-042).
     */
    private function corregir(int $intentoId, bool $soloSiVencio = false, string $evento = 'quiz_attempt.submitted'): void
    {
        DbContext::run(function () use ($intentoId, $soloSiVencio) {
            $actual = DB::table('quiz_attempts')->where('id', $intentoId)->lockForUpdate()->first(['status', 'deadline_at']);
            if ($actual->status !== 'in_progress' || ($soloSiVencio && now()->lte(Carbon::parse($actual->deadline_at)))) {
                return;
            }
            $respuestas = DB::table('attempt_answers as r')->join('questions as p', 'p.id', '=', 'r.question_id')->where('r.attempt_id', $intentoId)
                ->get(['r.id', 'r.selected_option_id', 'r.answer_text', 'p.question_type', 'p.accepted_answers']);
            foreach ($respuestas as $r) {
                $puntos = match ($r->question_type) {
                    'multiple_choice', 'true_false' => (float) (bool) DB::table('question_options')->where('id', $r->selected_option_id)->value('is_correct'),
                    'short_answer' => (float) in_array(mb_strtolower(trim((string) $r->answer_text)), array_map(fn ($a) => mb_strtolower(trim($a)), json_decode((string) $r->accepted_answers, true) ?: []), true),
                    default => null,
                };
                DB::table('attempt_answers')->where('id', $r->id)->update(['score' => $puntos]);
            }
            $hayEnsayos = $respuestas->contains('question_type', 'essay');
            DB::table('quiz_attempts')->where('id', $intentoId)->update(['status' => $hayEnsayos ? 'pending_grading' : 'graded', 'submitted_at' => now(), 'updated_at' => now()]);
            $this->registrarNota($intentoId);
        }, $evento);
    }

    /**
     * Nota del intento sobre 10 y, si es la mejor, nota de la actividad (borrador hasta publicar).
     */
    private function registrarNota(int $intentoId): void
    {
        $intento = DB::table('quiz_attempts')->where('id', $intentoId)->first();
        $respuestas = DB::table('attempt_answers')->where('attempt_id', $intentoId)->get(['score']);
        $nota = $respuestas->count() ? round(10 * $respuestas->sum('score') / $respuestas->count(), 2) : 0;
        $pendientes = $respuestas->whereNull('score')->count();
        DB::table('quiz_attempts')->where('id', $intentoId)->update(['score' => $nota, 'status' => $pendientes ? 'pending_grading' : 'graded', 'updated_at' => now()]);

        $item = DB::table('assessments')->join('grade_items', 'grade_items.id', '=', 'assessments.grade_item_id')->where('assessments.id', $intento->assessment_id)->first(['grade_items.id', 'grade_items.section_id', 'grade_items.is_published']);
        $mejor = (float) DB::table('quiz_attempts')->where('assessment_id', $intento->assessment_id)->where('enrollment_id', $intento->enrollment_id)->max('score');
        DB::table('grades')->updateOrInsert(['grade_item_id' => $item->id, 'enrollment_id' => $intento->enrollment_id], [
            'section_id' => $item->section_id, 'score' => $mejor, 'score_before_penalty' => $mejor, 'status' => $item->is_published ? 'published' : 'draft',
            'published_at' => $item->is_published ? now() : null, 'updated_at' => now(), 'created_at' => now()]);

        // Siempre corre dentro de un DbContext::run(): el job se despacha al confirmar la transacción (y con él se toma
        // el candado único), y solo si la nota quedó publicada, para reevaluar el riesgo académico de la sección (RF17).
        if ($item->is_published) {
            $seccion = (int) $item->section_id;
            $evaluacion = (int) $intento->assessment_id;
            $matricula = (int) $intento->enrollment_id;
            DB::afterCommit(function () use ($seccion, $evaluacion, $matricula): void {
                EvaluarRiesgoAcademico::dispatch($seccion);
                // La nota ya nació publicada: no habrá un «publicar notas» que complete los recursos de la regla
                // de nota mínima (RF18, ADR-067).
                IntentoCalificado::dispatch($evaluacion, $matricula);
            });
        }
    }
}
