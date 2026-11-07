<?php

namespace App\Modules\Evaluaciones\Services;

use App\Models\User;
use App\Modules\Calificaciones\Jobs\EvaluarRiesgoAcademico;
use App\Modules\Calificaciones\Services\CalificacionService;
use App\Modules\Evaluaciones\Events\EntregaRealizada;
use App\Support\Alcance;
use App\Support\DbContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Actividades y tareas (RF15, ADR-006, ADR-008; Flujo Entrega de tarea).
 * Política de entrega por tarea: estricta, gracia sin penalización o gracia con penalización de X %.
 * Calificación con rúbrica o nota directa; anónima si la evaluación lo indica, hasta publicar las notas.
 * En una tarea grupal la entrega es del equipo y su nota se copia a cada integrante (EquipoService).
 */
class ActividadService
{
    public const POLITICAS = ['strict' => 'Cierre estricto', 'grace' => 'Gracia sin penalización', 'grace_penalty' => 'Gracia con penalización'];

    public function __construct(private Alcance $alcance, private ProrrogaService $prorrogas, private CalificacionService $calificaciones, private EquipoService $equipos, private RubricaService $rubricas) {}

    /**
     * Actividades de la sección por cómputo. Para el estudiante, el estado de su entrega o intento.
     *
     * @return Collection<int, object>
     */
    public function porComputo(User $usuario, int $seccionId): Collection
    {
        abort_unless($this->alcance->puedeEnSeccion($usuario, 'contenidos.ver', $seccionId), 403);
        $matricula = $this->matricula($usuario, $seccionId);

        $actividades = DB::table('assessments as a')->join('grade_items as g', 'g.id', '=', 'a.grade_item_id')->where('a.section_id', $seccionId)
            ->orderBy('a.opens_at')
            ->get(['a.id', 'a.ulid', 'a.assessment_type', 'a.title', 'a.opens_at', 'a.closes_at', 'a.is_anonymous', 'g.component_id', 'g.weight_percent', 'g.is_published',
                DB::raw("(select count(*) from submissions s where s.assessment_id = a.id and s.status = 'submitted') as entregas"),
                DB::raw("(select count(*) from quiz_attempts q where q.assessment_id = a.id and q.status <> 'in_progress') as intentos")]);

        if ($matricula) {
            $entregadas = EquipoService::deMatricula(DB::table('submissions'), $matricula)->where('status', 'submitted')->pluck('assessment_id')->flip();
            $intentadas = DB::table('quiz_attempts')->where('enrollment_id', $matricula)->where('status', '!=', 'in_progress')->pluck('assessment_id')->flip();
            $notas = DB::table('grades')->where('enrollment_id', $matricula)->where('status', 'published')->pluck('score', 'grade_item_id');
            $items = DB::table('assessments')->where('section_id', $seccionId)->pluck('grade_item_id', 'id');
            $actividades->each(function ($a) use ($entregadas, $intentadas, $notas, $items) {
                $a->hecha = $entregadas->has($a->id) || $intentadas->has($a->id);
                $a->nota = $notas[$items[$a->id]] ?? null;
            });
        }

        return DB::table('grading_components')->where('section_id', $seccionId)->orderBy('number')->get()
            ->each(fn ($c) => $c->actividades = $actividades->where('component_id', $c->id)->values());
    }

    /**
     * @return array<string, mixed>
     */
    public function tarea(User $usuario, int $seccionId, string $ulid): array
    {
        abort_unless($this->alcance->puedeEnSeccion($usuario, 'contenidos.ver', $seccionId), 403);
        $tarea = DB::table('assessments as a')->join('assignment_settings as s', 's.assessment_id', '=', 'a.id')->join('grade_items as g', 'g.id', '=', 'a.grade_item_id')
            ->where('a.section_id', $seccionId)->where('a.ulid', $ulid)->where('a.assessment_type', 'assignment')
            ->first(['a.*', 's.*', 'g.weight_percent', 'g.is_published', 'g.id as item_id']) ?? abort(404);
        $tarea->extensiones = json_decode((string) $tarea->allowed_extensions, true) ?: [];
        $matricula = $this->matricula($usuario, $seccionId);
        // RF15: en una tarea grupal la entrega es del equipo; sin equipo todavía, no hay entrega que mostrar.
        $equipo = $matricula && $tarea->is_group ? $this->equipos->equipoDe((int) $tarea->id, $matricula) : null;
        $deSuEntrega = fn (Builder $q) => $tarea->is_group ? $q->where('submissions.team_id', $equipo->id ?? 0) : $q->where('submissions.enrollment_id', $matricula);
        $entrega = $matricula ? DB::table('submissions')->where('assessment_id', $tarea->id)->where($deSuEntrega)->where('status', 'submitted')->orderByDesc('attempt_number')->first() : null;
        // La revisión (nota, retroalimentación y rúbrica) se ve solo con las notas publicadas (ADR-006).
        $publicada = $matricula !== null && (bool) $tarea->is_published;
        $estado = $this->estado($tarea);
        // Con prórroga de entrega vigente, para este estudiante la tarea sigue abierta y sin penalización (ADR-040).
        $prorroga = $matricula ? $this->prorrogas->vigente($matricula, (int) $tarea->id, 'submission') : null;
        if ($prorroga && in_array($estado, ['gracia', 'cerrada'], true)) {
            $estado = 'prorroga';
        }

        return [
            'tarea' => $tarea,
            'estado' => $estado,
            'prorroga' => $prorroga,
            'entrega' => $entrega,
            'archivos' => $matricula ? DB::table('submission_files')->join('submissions', 'submissions.id', '=', 'submission_files.submission_id')
                ->where('submissions.assessment_id', $tarea->id)->where($deSuEntrega)->where('submissions.status', 'submitted')
                ->get(['submission_files.original_name', 'submission_files.bytes']) : collect(),
            'retroalimentacion' => $publicada ? DB::table('submission_feedback')->join('submissions', 'submissions.id', '=', 'submission_feedback.submission_id')
                ->where('submissions.assessment_id', $tarea->id)->where($deSuEntrega)->orderBy('submission_feedback.id')->pluck('submission_feedback.comment') : collect(),
            'nota' => $publicada ? DB::table('grades')->where('grade_item_id', $tarea->item_id)->where('enrollment_id', $matricula)->where('status', 'published')->first(['score', 'score_before_penalty', 'comment']) : null,
            'desglose' => $publicada && $entrega ? $this->desgloseRubrica((int) $entrega->id) : collect(),
            'esEstudiante' => $matricula !== null,
            'equipo' => $equipo,
            'gestiona' => $this->alcance->puedeEnSeccion($usuario, 'tareas.gestionar', $seccionId),
        ];
    }

    /**
     * Entrega del estudiante (RF15): valida la ventana, la extensión, el peso y los intentos.
     */
    public function entregar(User $estudiante, int $seccionId, string $ulid, UploadedFile $archivo): void
    {
        $this->alcance->exigir($estudiante, 'tareas.entregar');
        $datos = $this->tarea($estudiante, $seccionId, $ulid);
        $tarea = $datos['tarea'];
        $matricula = $this->matricula($estudiante, $seccionId) ?? abort(403);
        $estado = $datos['estado'];

        if (! in_array($estado, ['abierta', 'gracia', 'prorroga'], true)) {
            throw ValidationException::withMessages(['archivo' => $estado === 'proxima' ? __('La tarea aún no abre.') : __('La tarea ya cerró.')]);
        }
        $extension = strtolower($archivo->getClientOriginalExtension());
        if ($tarea->extensiones && ! in_array($extension, $tarea->extensiones, true)) {
            throw ValidationException::withMessages(['archivo' => __('Extensión no permitida. Se aceptan: :e.', ['e' => implode(', ', $tarea->extensiones)])]);
        }
        if ($archivo->getSize() > (int) $tarea->max_file_mb * 1024 * 1024) {
            throw ValidationException::withMessages(['archivo' => __('El archivo supera :n MB.', ['n' => $tarea->max_file_mb])]);
        }
        // RF15: la entrega grupal es del equipo, la haga quien la haga, y los intentos son del equipo.
        if ($tarea->is_group && $datos['equipo'] === null) {
            throw ValidationException::withMessages(['archivo' => __('Aún no tiene equipo en esta tarea: su docente lo asigna.')]);
        }
        $duenio = $tarea->is_group ? ['team_id' => (int) $datos['equipo']->id] : ['enrollment_id' => $matricula];
        $intentos = DB::table('submissions')->where('assessment_id', $tarea->id)->where($duenio)->where('status', 'submitted')->count();
        if ($intentos >= (int) $tarea->max_attempts) {
            throw ValidationException::withMessages(['archivo' => __('Ya usó los :n intentos de entrega.', ['n' => $tarea->max_attempts])]);
        }

        $tardia = $estado === 'gracia';
        $ruta = $archivo->store("entregas/{$tarea->id}");
        $entregaId = DbContext::run(function () use ($tarea, $seccionId, $duenio, $archivo, $ruta, $tardia, $estudiante): int {
            $intento = (int) DB::table('submissions')->where('assessment_id', $tarea->id)->where($duenio)->max('attempt_number') + 1;
            $id = DB::table('submissions')->insertGetId(['ulid' => (string) Str::ulid(), 'assessment_id' => $tarea->id, 'section_id' => $seccionId,
                'enrollment_id' => $duenio['enrollment_id'] ?? null, 'team_id' => $duenio['team_id'] ?? null,
                'attempt_number' => $intento, 'status' => 'submitted', 'is_late' => $tardia,
                'late_penalty_applied' => $tardia && $tarea->late_policy === 'grace_penalty' ? $tarea->late_penalty_percent : 0,
                'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('submission_files')->insert(['submission_id' => $id, 'path' => $ruta, 'original_name' => $archivo->getClientOriginalName(), 'bytes' => $archivo->getSize(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('submission_events')->insert(['submission_id' => $id, 'event_type' => 'submitted', 'actor_id' => $estudiante->id, 'occurred_at' => now()]);

            return $id;
        }, 'submission.created');

        // Se avisa fuera de la transacción (puede reintentarse) para los recursos que se completan al entregar (RF18).
        EntregaRealizada::dispatch($entregaId);
    }

    public function cancelarEntrega(User $estudiante, int $seccionId, string $ulid): void
    {
        $datos = $this->tarea($estudiante, $seccionId, $ulid);
        if ($datos['estado'] !== 'abierta' || ! $datos['entrega']) {
            throw ValidationException::withMessages(['archivo' => __('Solo puede cancelar mientras la tarea está abierta.')]);
        }
        DbContext::run(function () use ($datos, $estudiante) {
            DB::table('submissions')->where('id', $datos['entrega']->id)->update(['status' => 'cancelled', 'updated_at' => now()]);
            DB::table('submission_events')->insert(['submission_id' => $datos['entrega']->id, 'event_type' => 'cancelled', 'actor_id' => $estudiante->id, 'occurred_at' => now()]);
        }, 'submission.cancelled');
    }

    /**
     * @param  array{componente: int, titulo: string, instrucciones?: string|null, abre: string, cierra: string, peso: float, politica: string, gracia?: string|null, penalizacion?: int, extensiones: array<int, string>, mb: int, intentos: int, anonima: bool, grupal: bool, criterios?: array<int, array{nombre: string, maximo: float}>}  $datos
     */
    public function crearTarea(User $docente, int $seccionId, array $datos): string
    {
        abort_unless($this->alcance->puedeEnSeccion($docente, 'tareas.gestionar', $seccionId), 403);
        $this->validarPeso((int) $datos['componente'], $seccionId, (float) $datos['peso']);
        if (Carbon::parse($datos['cierra'])->lte(Carbon::parse($datos['abre']))) {
            throw ValidationException::withMessages(['cierra' => __('El cierre debe ser posterior a la apertura.')]);
        }
        // La rúbrica se arma con la tarea (ADR-065); sin criterios, la tarea se califica con nota directa.
        $criterios = $datos['criterios'] ?? [];
        if ($criterios !== []) {
            $this->rubricas->validar($criterios);
        }

        return DbContext::run(function () use ($docente, $seccionId, $datos, $criterios) {
            $item = DB::table('grade_items')->insertGetId(['component_id' => $datos['componente'], 'section_id' => $seccionId, 'item_type' => 'assignment', 'title' => $datos['titulo'],
                'weight_percent' => $datos['peso'], 'is_published' => false, 'created_at' => now(), 'updated_at' => now()]);
            $rubrica = $criterios === [] ? null : $this->rubricas->insertar($docente->id, $datos['titulo'], $criterios);
            $ulid = (string) Str::ulid();
            $id = DB::table('assessments')->insertGetId(['ulid' => $ulid, 'section_id' => $seccionId, 'grade_item_id' => $item, 'assessment_type' => 'assignment',
                'title' => $datos['titulo'], 'instructions' => $datos['instrucciones'] ?? null, 'opens_at' => $datos['abre'], 'closes_at' => $datos['cierra'],
                'max_attempts' => max(1, $datos['intentos']), 'rubric_id' => $rubrica, 'is_anonymous' => $datos['anonima'], 'created_at' => now(), 'updated_at' => now()]);
            DB::table('assignment_settings')->insert(['assessment_id' => $id, 'is_group' => $datos['grupal'], 'late_policy' => $datos['politica'],
                'grace_until' => $datos['politica'] === 'strict' ? null : ($datos['gracia'] ?? null), 'late_penalty_percent' => $datos['politica'] === 'grace_penalty' ? (int) ($datos['penalizacion'] ?? 0) : 0,
                'allowed_extensions' => json_encode(array_values($datos['extensiones'])), 'max_file_mb' => $datos['mb']]);

            return $ulid;
        }, 'assessment.created');
    }

    /**
     * Evaluaciones copiadas de un período anterior que aún no tienen fechas ni cómputo (RF14, ADR-066). Solo las ve el
     * docente de la sección: para el estudiante no existen hasta que se programan.
     *
     * @return Collection<int, \stdClass>
     */
    public function sinProgramar(User $docente, int $seccionId): Collection
    {
        if (! $this->alcance->puedeEnSeccion($docente, 'tareas.gestionar', $seccionId) && ! $this->alcance->puedeEnSeccion($docente, 'cuestionarios.gestionar', $seccionId)) {
            return collect();
        }

        return DB::table('assessments as a')->leftJoin('assignment_settings as s', 's.assessment_id', '=', 'a.id')
            ->where('a.section_id', $seccionId)->whereNull('a.grade_item_id')->orderBy('a.id')
            ->get(['a.id', 'a.ulid', 'a.assessment_type', 'a.title', 'a.rubric_id', 's.late_policy']);
    }

    /**
     * Programa una evaluación copiada (RF14): fechas, cómputo y peso, con las mismas reglas que al crearla. Una tarea con
     * período de gracia pide también la fecha de gracia.
     *
     * @param  array{abre: string, cierra: string, componente: int, peso: float, gracia?: string|null}  $datos
     *
     * @throws ValidationException
     */
    public function programar(User $docente, int $seccionId, string $ulid, array $datos): void
    {
        $evaluacion = DB::table('assessments as a')->leftJoin('assignment_settings as s', 's.assessment_id', '=', 'a.id')
            ->where('a.section_id', $seccionId)->where('a.ulid', $ulid)->whereNull('a.grade_item_id')
            ->first(['a.id', 'a.assessment_type', 'a.title', 's.late_policy']) ?? abort(404);
        abort_unless($this->alcance->puedeEnSeccion($docente, $evaluacion->assessment_type === 'quiz' ? 'cuestionarios.gestionar' : 'tareas.gestionar', $seccionId), 403);
        $this->validarPeso((int) $datos['componente'], $seccionId, (float) $datos['peso']);
        if (Carbon::parse($datos['cierra'])->lte(Carbon::parse($datos['abre']))) {
            throw ValidationException::withMessages(['cierra' => __('El cierre debe ser posterior a la apertura.')]);
        }
        $conGracia = $evaluacion->assessment_type === 'assignment' && $evaluacion->late_policy !== 'strict';
        if ($conGracia && blank($datos['gracia'] ?? null)) {
            throw ValidationException::withMessages(['gracia' => __('La tarea tiene período de gracia: indique hasta cuándo.')]);
        }

        DbContext::run(function () use ($seccionId, $evaluacion, $datos, $conGracia) {
            $item = DB::table('grade_items')->insertGetId(['component_id' => $datos['componente'], 'section_id' => $seccionId, 'item_type' => $evaluacion->assessment_type,
                'title' => $evaluacion->title, 'weight_percent' => $datos['peso'], 'is_published' => false, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('assessments')->where('id', $evaluacion->id)->update(['grade_item_id' => $item, 'opens_at' => $datos['abre'], 'closes_at' => $datos['cierra'], 'updated_at' => now()]);
            if ($conGracia) {
                DB::table('assignment_settings')->where('assessment_id', $evaluacion->id)->update(['grace_until' => $datos['gracia']]);
            }
        }, 'assessment.scheduled');
    }

    /**
     * Entregas para calificar. En evaluaciones anónimas, sin nombres hasta publicar las notas (ADR-006).
     *
     * @return array{tarea: object, entregas: Collection<int, \stdClass>, anonima: bool, rubrica: Collection<int, \stdClass>, rubricaEditable: bool}
     */
    public function entregas(User $docente, int $seccionId, string $ulid): array
    {
        abort_unless($this->alcance->puedeEnSeccion($docente, 'calificaciones.gestionar', $seccionId), 403);
        /** @var \stdClass $tarea */
        $tarea = $this->tarea($docente, $seccionId, $ulid)['tarea'];
        $anonima = (bool) $tarea->is_anonymous && ! $tarea->is_published;

        $entregas = DB::table('submissions as s')->leftJoin('enrollments as e', 'e.id', '=', 's.enrollment_id')->leftJoin('users as u', 'u.id', '=', 'e.student_id')
            ->leftJoin('teams as t', 't.id', '=', 's.team_id')
            ->leftJoin('grades as g', fn ($j) => $j->on('g.enrollment_id', '=', 's.enrollment_id')->where('g.grade_item_id', $tarea->item_id))
            ->where('s.assessment_id', $tarea->id)->where('s.status', 'submitted')->orderBy('s.submitted_at')
            ->get(['s.id', 's.enrollment_id', 's.team_id', 's.submitted_at', 's.is_late', 's.late_penalty_applied', 'u.first_name', 'u.last_name', 't.name as equipo',
                'g.score', 'g.status as estado_nota', DB::raw('(select f.original_name from submission_files f where f.submission_id = s.id limit 1) as archivo')]);

        // RF15: la entrega de un equipo muestra a sus integrantes con la nota de cada uno (puede ajustarse a uno solo).
        $integrantes = DB::table('team_members as tm')->join('enrollments as e', 'e.id', '=', 'tm.enrollment_id')->join('users as u', 'u.id', '=', 'e.student_id')
            ->leftJoin('grades as g', fn ($j) => $j->on('g.enrollment_id', '=', 'tm.enrollment_id')->where('g.grade_item_id', $tarea->item_id))
            ->whereIn('tm.team_id', $entregas->pluck('team_id')->filter()->all())->where('e.status', 'active')->orderBy('u.last_name')
            ->get(['tm.team_id', 'e.id as matricula', 'u.first_name', 'u.last_name', 'g.score'])->groupBy(fn (object $i) => (int) $i->team_id);
        $entregas = $entregas->values()->each(function ($s, $i) use ($anonima, $integrantes) {
            $s->integrantes = $s->team_id ? $integrantes->get((int) $s->team_id, collect()) : collect();
            if ($s->team_id) {
                $s->score = $s->integrantes->whereNotNull('score')->first()?->score;
                $s->nombre = $anonima ? __('Equipo :n', ['n' => $i + 1]) : __('Equipo :e', ['e' => $s->equipo]);
            } else {
                $s->nombre = $anonima ? __('Estudiante :n', ['n' => $i + 1]) : $s->first_name.' '.$s->last_name;
            }
        });

        $rubricaId = $tarea->rubric_id !== null ? (int) $tarea->rubric_id : null;

        return ['tarea' => $tarea, 'entregas' => $entregas, 'anonima' => $anonima, 'rubrica' => $this->rubricas->criterios($rubricaId),
            'rubricaEditable' => $this->rubricas->editable((int) $tarea->item_id, $rubricaId)];
    }

    /**
     * Entregas que el estudiante hizo en los grupos anteriores de un cambio de grupo (RF09, [[ADR-062]]).
     * Son histórico de **solo lectura**: el docente del grupo nuevo las ve con su actividad y el grupo de
     * origen, pero no las califica ni las modifica aquí. Resolver la cadena no abre la sección anterior:
     * se exige el permiso sobre `$seccionId` y que la matrícula sea de esa sección, y se devuelven
     * únicamente las entregas de las matrículas anteriores de ese estudiante.
     *
     * @return Collection<int, \stdClass>
     */
    public function entregasDeCadena(User $usuario, int $seccionId, int $matriculaId): Collection
    {
        abort_unless($this->alcance->puedeEnSeccion($usuario, 'calificaciones.gestionar', $seccionId), 403);
        DB::table('enrollments')->where('id', $matriculaId)->where('section_id', $seccionId)->exists() || abort(404);

        $grupos = $this->calificaciones->cadena($matriculaId)->pluck('grupo', 'id');
        if ($grupos->isEmpty()) {
            return collect();
        }

        return DB::table('submissions as s')->join('assessments as a', 'a.id', '=', 's.assessment_id')
            ->leftJoin('grades as g', fn ($j) => $j->on('g.enrollment_id', '=', 's.enrollment_id')->on('g.grade_item_id', '=', 'a.grade_item_id'))
            ->whereIn('s.enrollment_id', $grupos->keys())->where('s.status', 'submitted')->orderByDesc('s.submitted_at')
            ->get(['s.id', 's.enrollment_id', 's.section_id', 's.submitted_at', 's.is_late', 's.late_penalty_applied',
                'a.ulid as actividad_ulid', 'a.title as actividad', 'a.assessment_type as tipo', 'g.score', 'g.status as estado_nota',
                DB::raw('(select f.original_name from submission_files f where f.submission_id = s.id limit 1) as archivo')])
            ->values()->each(function ($s) use ($grupos) {
                $s->grupo = $grupos->get((int) $s->enrollment_id);
            });
    }

    /**
     * Califica una entrega: con rúbrica (suma de los niveles elegidos) o nota directa; aplica la penalización de tardía.
     *
     * @param  array<int, int>  $niveles  criterio => nivel
     */
    public function calificarEntrega(User $docente, int $seccionId, int $entregaId, ?float $nota, array $niveles, ?string $comentario): float
    {
        abort_unless($this->alcance->puedeEnSeccion($docente, 'calificaciones.gestionar', $seccionId), 403);
        $this->calificaciones->exigirActaAbierta($seccionId);
        $entrega = DB::table('submissions')->where('id', $entregaId)->where('section_id', $seccionId)->first() ?? abort(404);
        $tarea = DB::table('assessments')->join('grade_items', 'grade_items.id', '=', 'assessments.grade_item_id')->where('assessments.id', $entrega->assessment_id)
            ->first(['assessments.rubric_id', 'grade_items.id as item_id', 'grade_items.is_published']);

        $puntos = [];
        if ($tarea->rubric_id && $niveles !== []) {
            $nivelesValidos = DB::table('rubric_levels')->join('rubric_criteria', 'rubric_criteria.id', '=', 'rubric_levels.criterion_id')
                ->where('rubric_criteria.rubric_id', $tarea->rubric_id)->whereIn('rubric_levels.id', array_values($niveles))->get(['rubric_levels.id', 'rubric_levels.points', 'rubric_levels.criterion_id']);
            $puntos = $nivelesValidos->all();
            $nota = (float) $nivelesValidos->sum('points');
        }
        if ($nota === null || $nota < 0 || $nota > 10) {
            throw ValidationException::withMessages(['nota' => __('Escriba una nota entre 0 y 10 o complete la rúbrica.')]);
        }
        $final = round($nota * (1 - (float) $entrega->late_penalty_applied / 100), 2);
        // RF15: la nota de una entrega grupal se copia a cada integrante activo del equipo.
        $matriculas = $entrega->team_id ? $this->equipos->matriculasDe((int) $entrega->team_id)->all() : [(int) $entrega->enrollment_id];
        if ($matriculas === []) {
            throw ValidationException::withMessages(['nota' => __('El equipo no tiene integrantes matriculados.')]);
        }

        DbContext::run(function () use ($docente, $entrega, $tarea, $nota, $final, $puntos, $comentario, $matriculas) {
            DB::table('submission_rubric_scores')->where('submission_id', $entrega->id)->delete();
            foreach ($puntos as $p) {
                DB::table('submission_rubric_scores')->insert(['submission_id' => $entrega->id, 'criterion_id' => $p->criterion_id, 'level_id' => $p->id, 'points' => $p->points]);
            }
            // Una retroalimentación por docente y entrega: al recalificar se reemplaza, no se duplica.
            if ($comentario) {
                DB::table('submission_feedback')->updateOrInsert(['submission_id' => $entrega->id, 'author_id' => $docente->id], ['comment' => $comentario, 'updated_at' => now(), 'created_at' => now()]);
            }
            foreach ($matriculas as $matricula) {
                DB::table('grades')->updateOrInsert(['grade_item_id' => $tarea->item_id, 'enrollment_id' => $matricula], [
                    'section_id' => $entrega->section_id, 'score' => $final, 'score_before_penalty' => $nota, 'comment' => $comentario, 'graded_by' => $docente->id,
                    'status' => $tarea->is_published ? 'published' : 'draft', 'published_at' => $tarea->is_published ? now() : null, 'updated_at' => now(), 'created_at' => now()]);
            }
        }, 'submission.graded');

        // Con la tarea ya publicada la nota queda visible: se reevalúa el riesgo académico de la sección (RF17).
        if ($tarea->is_published) {
            EvaluarRiesgoAcademico::dispatch((int) $entrega->section_id);
        }

        return $final;
    }

    /**
     * Ajusta la nota de un solo integrante de una entrega grupal (RF15), con motivo: p. ej. si no trabajó. Se aplica
     * la misma penalización de tardía que al equipo. Volver a calificar la entrega copia otra vez la nota a todos.
     */
    public function ajustarIntegrante(User $docente, int $seccionId, int $entregaId, int $matriculaId, ?float $nota, string $motivo): float
    {
        abort_unless($this->alcance->puedeEnSeccion($docente, 'calificaciones.gestionar', $seccionId), 403);
        $this->calificaciones->exigirActaAbierta($seccionId);
        $entrega = DB::table('submissions')->where('id', $entregaId)->where('section_id', $seccionId)->whereNotNull('team_id')->first() ?? abort(404);
        $this->equipos->matriculasDe((int) $entrega->team_id)->contains($matriculaId) || abort(404);
        if ($nota === null || $nota < 0 || $nota > 10) {
            throw ValidationException::withMessages(['notaIntegrante' => __('Escriba una nota entre 0 y 10.')]);
        }
        if (trim($motivo) === '') {
            throw ValidationException::withMessages(['motivoAjuste' => __('Ajustar la nota de un integrante exige un motivo.')]);
        }
        $tarea = DB::table('assessments')->join('grade_items', 'grade_items.id', '=', 'assessments.grade_item_id')->where('assessments.id', $entrega->assessment_id)
            ->first(['grade_items.id as item_id', 'grade_items.is_published']);
        $final = round($nota * (1 - (float) $entrega->late_penalty_applied / 100), 2);

        DbContext::run(fn () => DB::table('grades')->updateOrInsert(['grade_item_id' => $tarea->item_id, 'enrollment_id' => $matriculaId], [
            'section_id' => $entrega->section_id, 'score' => $final, 'score_before_penalty' => $nota, 'comment' => trim($motivo), 'graded_by' => $docente->id,
            'status' => $tarea->is_published ? 'published' : 'draft', 'published_at' => $tarea->is_published ? now() : null, 'updated_at' => now(), 'created_at' => now()]),
            'grade.member_adjusted', trim($motivo));

        if ($tarea->is_published) {
            EvaluarRiesgoAcademico::dispatch((int) $entrega->section_id);
        }

        return $final;
    }

    /**
     * Bandeja del docente: entregas sin nota y ensayos por calificar en sus secciones (RF24).
     *
     * @return Collection<int, object>
     */
    public function pendientes(User $docente): Collection
    {
        $this->alcance->exigir($docente, 'calificaciones.gestionar');
        $entregas = DB::table('submissions as s')->join('assessments as a', 'a.id', '=', 's.assessment_id')->join('sections', 'sections.id', '=', 's.section_id')
            ->join('subjects', 'subjects.id', '=', 'sections.subject_id')
            // Sin nota: ni de quien entregó ni, en una entrega grupal, de ningún integrante del equipo (RF15).
            ->whereNotExists(fn ($q) => $q->from('grades as g')->whereColumn('g.grade_item_id', 'a.grade_item_id')
                ->where(fn ($w) => $w->whereColumn('g.enrollment_id', 's.enrollment_id')
                    ->orWhereIn('g.enrollment_id', fn ($t) => $t->select('tm.enrollment_id')->from('team_members as tm')->whereColumn('tm.team_id', 's.team_id'))))
            ->where('s.status', 'submitted')
            ->groupBy('a.id', 'a.ulid', 'a.title', 'a.closes_at', 'a.is_anonymous', 'sections.ulid', 'sections.code', 'subjects.code')
            ->select('a.id', 'a.ulid', 'a.title', 'a.closes_at', 'a.is_anonymous', 'sections.ulid as seccion', 'sections.code as grupo', 'subjects.code', DB::raw("'tarea' as tipo"), DB::raw('count(*) as pendientes'));
        $ensayos = DB::table('quiz_attempts as q')->join('assessments as a', 'a.id', '=', 'q.assessment_id')->join('sections', 'sections.id', '=', 'a.section_id')
            ->join('subjects', 'subjects.id', '=', 'sections.subject_id')->where('q.status', 'pending_grading')
            ->groupBy('a.id', 'a.ulid', 'a.title', 'a.closes_at', 'a.is_anonymous', 'sections.ulid', 'sections.code', 'subjects.code')
            ->select('a.id', 'a.ulid', 'a.title', 'a.closes_at', 'a.is_anonymous', 'sections.ulid as seccion', 'sections.code as grupo', 'subjects.code', DB::raw("'cuestionario' as tipo"), DB::raw('count(*) as pendientes'));

        return $this->alcance->secciones($entregas, $docente, 'calificaciones.gestionar')->get()
            ->concat($this->alcance->secciones($ensayos, $docente, 'calificaciones.gestionar', 'a.section_id')->get())
            ->sortBy('closes_at')->values();
    }

    /**
     * Puntos de la rúbrica por criterio de una entrega: lo que el estudiante ve en su revisión (CP-RF15-06).
     *
     * @return Collection<int, object>
     */
    public function desgloseRubrica(int $entregaId): Collection
    {
        return DB::table('submission_rubric_scores as s')->join('rubric_criteria as c', 'c.id', '=', 's.criterion_id')->join('rubric_levels as l', 'l.id', '=', 's.level_id')
            ->where('s.submission_id', $entregaId)->orderBy('c.position')
            ->get(['c.id', 'c.name as criterio', 'l.name as nivel', 's.points', DB::raw('(select max(x.points) from rubric_levels x where x.criterion_id = c.id) as maximo')]);
    }

    public function estado(object $actividad): string
    {
        $ahora = now();
        if ($ahora->lt(Carbon::parse($actividad->opens_at))) {
            return 'proxima';
        }
        if ($ahora->lte(Carbon::parse($actividad->closes_at))) {
            return 'abierta';
        }
        if (($actividad->late_policy ?? 'strict') !== 'strict' && ! empty($actividad->grace_until) && $ahora->lte(Carbon::parse($actividad->grace_until))) {
            return 'gracia';
        }

        return 'cerrada';
    }

    public function validarPeso(int $componenteId, int $seccionId, float $peso): void
    {
        DB::table('grading_components')->where('id', $componenteId)->where('section_id', $seccionId)->exists() || abort(404);
        $usado = (float) DB::table('grade_items')->where('component_id', $componenteId)->sum('weight_percent');
        if ($peso <= 0 || $usado + $peso > 100) {
            throw ValidationException::withMessages(['peso' => __('El peso supera el 100 % del cómputo (ya usado: :u %).', ['u' => rtrim(rtrim(number_format($usado, 2, '.', ''), '0'), '.')])]);
        }
    }

    public function matricula(User $usuario, int $seccionId): ?int
    {
        $id = DB::table('enrollments')->where('student_id', $usuario->id)->where('section_id', $seccionId)->where('status', 'active')->value('id');

        return $id === null ? null : (int) $id;
    }
}
