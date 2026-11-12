<?php

namespace App\Modules\Comunicacion\Listeners;

use App\Modules\Academica\Events\HorarioCambiado;
use App\Modules\Academica\Events\MatriculaCambiada;
use App\Modules\Academica\Events\PeriodoReabierto;
use App\Modules\Asistencia\Events\AlertaAsistenciaAbierta;
use App\Modules\Asistencia\Events\ClaseCancelada;
use App\Modules\Asistencia\Services\AsistenciaService;
use App\Modules\Calificaciones\Events\ActaDevuelta;
use App\Modules\Calificaciones\Events\ActaPublicada;
use App\Modules\Calificaciones\Events\NotaCorregida;
use App\Modules\Calificaciones\Events\NotasPublicadas;
use App\Modules\Calificaciones\Events\RevisionResuelta;
use App\Modules\Calificaciones\Events\RevisionSolicitada;
use App\Modules\Calificaciones\Events\RiesgoAcademicoDetectado;
use App\Modules\Comunicacion\Events\RespuestaPublicada;
use App\Modules\Comunicacion\Notifications\Aviso;
use App\Modules\Comunicacion\Services\NotificacionService;
use App\Modules\Evaluaciones\Events\ProrrogaConcedida;
use App\Modules\Evaluaciones\Events\RevisionCuestionarioPublicada;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Convierte los eventos de Académica, Asistencia, Calificaciones, Evaluaciones y del foro en avisos (RF23). Cada aviso lleva su dedup_key, así
 * que un evento repetido no duplica el aviso; NotificacionService decide además si se agrupa o si va por correo.
 */
class EnviarAvisos
{
    public function __construct(private NotificacionService $notificaciones) {}

    public function notasPublicadas(NotasPublicadas $evento): void
    {
        $item = DB::table('grade_items')->where('id', $evento->itemId)->first(['id', 'title']);
        $estudiantes = DB::table('grades')->join('enrollments', 'enrollments.id', '=', 'grades.enrollment_id')
            ->where('grades.grade_item_id', $evento->itemId)->where('grades.status', 'published')->pluck('enrollments.student_id');

        $this->enviar($estudiantes->all(), new Aviso('grade.published', __('Nota publicada: :t', ['t' => $item->title]),
            __('Ya puede ver su nota y la retroalimentación; si no está de acuerdo, puede solicitar revisión.'), route('mis-notas'), 'grade.published:item:'.$item->id));
    }

    /**
     * Avisa a los coordinadores vigentes de las carreras de la asignatura que el acta espera su validación (RF34).
     * La clave lleva la fecha de publicación: si el acta vuelve a publicarse tras una devolución, el aviso se repite.
     */
    public function actaPublicada(ActaPublicada $evento): void
    {
        $s = $this->seccionConActa($evento->seccionId);
        $coordinadores = DB::table('career_coordinators')->whereNull('valid_to')
            ->whereIn('career_id', DB::table('career_subjects')->join('study_plans', 'study_plans.id', '=', 'career_subjects.study_plan_id')
                ->where('career_subjects.subject_id', $s->subject_id)->select('study_plans.career_id'))
            ->pluck('user_id');

        $this->enviar($coordinadores->unique()->all(), new Aviso('grade_sheet.published', __('Acta por validar: :c', ['c' => $s->code.'-'.$s->grupo]),
            __('El docente publicó el acta de :a. Revísela y valídela o devuélvala con observaciones.', ['a' => $s->name]),
            route('actas'), 'grade_sheet.published:section:'.$s->id.':'.Carbon::parse($s->published_at)->timestamp));
    }

    /**
     * Avisa a los docentes vigentes de la sección que el Coordinador devolvió el acta, con el motivo (RF34).
     */
    public function actaDevuelta(ActaDevuelta $evento): void
    {
        $s = $this->seccionConActa($evento->seccionId);
        $docentes = DB::table('teaching_assignments')->where('section_id', $s->id)->whereNull('ended_at')->pluck('teacher_id');

        $this->enviar($docentes->unique()->all(), new Aviso('grade_sheet.returned', __('Acta devuelta: :c', ['c' => $s->code.'-'.$s->grupo]),
            __('El Coordinador devolvió el acta de :a. Observaciones: :m', ['a' => $s->name, 'm' => $evento->motivo]),
            route('secciones.acta', ['seccion' => $s->ulid]), 'grade_sheet.returned:section:'.$s->id.':'.Carbon::parse($s->updated_at)->timestamp));
    }

    public function revisionCuestionarioPublicada(RevisionCuestionarioPublicada $evento): void
    {
        $c = DB::table('assessments')->join('sections', 'sections.id', '=', 'assessments.section_id')->where('assessments.id', $evento->cuestionarioId)
            ->first(['assessments.id', 'assessments.ulid', 'assessments.title', 'sections.ulid as seccion']);
        $estudiantes = DB::table('quiz_attempts')->join('enrollments', 'enrollments.id', '=', 'quiz_attempts.enrollment_id')
            ->where('quiz_attempts.assessment_id', $c->id)->where('quiz_attempts.status', '!=', 'in_progress')->distinct()->pluck('enrollments.student_id');

        $this->enviar($estudiantes->all(), new Aviso('feedback.available', __('Revisión disponible: :t', ['t' => $c->title]),
            __('Ya puede ver la corrección de su intento.'), route('secciones.cuestionarios.show', ['seccion' => $c->seccion, 'cuestionario' => $c->ulid]), 'feedback.available:quiz:'.$c->id));
    }

    public function revisionSolicitada(RevisionSolicitada $evento): void
    {
        $s = $this->solicitud($evento->solicitud);
        $docentes = DB::table('teaching_assignments')->where('section_id', $s->section_id)->whereNull('ended_at')->pluck('teacher_id');

        $this->enviar($docentes->all(), new Aviso('grade_review.requested', __('Solicitud de revisión: :t', ['t' => $s->title]),
            trans_choice('{1} :n pide revisar 1 ítem.|[2,*] :n pide revisar :count ítems.', $s->items, ['n' => trim($s->first_name.' '.$s->last_name)]),
            route('secciones.revisiones', ['seccion' => $s->seccion]), 'grade_review.requested:'.$evento->solicitud));
    }

    public function revisionResuelta(RevisionResuelta $evento): void
    {
        $s = $this->solicitud($evento->solicitud);
        $cuerpo = $s->status === 'resolved_changed'
            ? __('Su nota cambió de :a a :b.', ['a' => number_format((float) $s->score_before, 1), 'b' => number_format((float) $s->score_after, 1)])
            : __('Su nota se mantiene en :a. Vea la respuesta del docente.', ['a' => number_format((float) $s->score_after, 1)]);
        $quiz = $s->assessment_type === 'quiz';

        $this->enviar([$s->student_id], new Aviso('grade_review.resolved', __('Revisión resuelta: :t', ['t' => $s->title]), $cuerpo,
            route($quiz ? 'secciones.cuestionarios.show' : 'secciones.tareas.show', ['seccion' => $s->seccion, $quiz ? 'cuestionario' : 'tarea' => $s->actividad]),
            'grade_review.resolved:'.$evento->solicitud));
    }

    public function prorrogaConcedida(ProrrogaConcedida $evento): void
    {
        $p = DB::table('assessment_extensions as x')->join('assessments as a', 'a.id', '=', 'x.assessment_id')->join('enrollments as e', 'e.id', '=', 'x.enrollment_id')
            ->join('sections', 'sections.id', '=', 'x.section_id')->where('x.id', $evento->prorrogaId)
            ->first(['x.kind', 'x.until', 'x.updated_at', 'a.title', 'a.ulid', 'a.assessment_type', 'e.student_id', 'sections.ulid as seccion']);
        $hasta = Carbon::parse($p->until)->timezone('America/El_Salvador')->format('d/m/Y H:i');
        $quiz = $p->assessment_type === 'quiz';

        $this->enviar([$p->student_id], new Aviso('extension.granted', __('Prórroga: :t', ['t' => $p->title]),
            $p->kind === 'submission' ? __('Puede entregar hasta el :f sin penalización.', ['f' => $hasta]) : __('Puede pedir revisión de su nota hasta el :f.', ['f' => $hasta]),
            route($quiz ? 'secciones.cuestionarios.show' : 'secciones.tareas.show', ['seccion' => $p->seccion, $quiz ? 'cuestionario' : 'tarea' => $p->ulid]),
            'extension.granted:'.$evento->prorrogaId.':'.Carbon::parse($p->updated_at)->timestamp));
    }

    /**
     * Avisa a los estudiantes matriculados y a los docentes vigentes que cambió el horario o el aula de la sección (RF08).
     */
    public function horarioCambiado(HorarioCambiado $evento): void
    {
        $s = DB::table('sections')->join('subjects', 'subjects.id', '=', 'sections.subject_id')->where('sections.id', $evento->seccionId)
            ->first(['sections.id', 'sections.ulid', 'sections.code as grupo', 'subjects.code', 'subjects.name']);
        $estudiantes = DB::table('enrollments')->where('section_id', $s->id)->where('status', 'active')->pluck('student_id');
        $docentes = DB::table('teaching_assignments')->where('section_id', $s->id)->whereNull('ended_at')->pluck('teacher_id');

        $this->enviar($estudiantes->concat($docentes)->unique()->all(), new Aviso('schedule.changed', __('Cambio de horario: :c', ['c' => $s->code.'-'.$s->grupo]),
            __(':a. :d', ['a' => $s->name, 'd' => $evento->detalle]),
            route('secciones.contenidos', ['seccion' => $s->ulid]), 'schedule.changed:section:'.$s->id.':'.$evento->cambio));
    }

    /**
     * Avisa a los coordinadores vigentes que el Administrador reabrió un período cerrado, con el motivo (RF06).
     */
    public function periodoReabierto(PeriodoReabierto $evento): void
    {
        $periodo = DB::table('academic_periods')->where('id', $evento->periodoId)->value('name');
        $coordinadores = DB::table('career_coordinators')->whereNull('valid_to')->pluck('user_id');

        $this->enviar($coordinadores->unique()->all(), new Aviso('period.reopened', __('Período reabierto: :p', ['p' => $periodo]),
            __('El Administrador reabrió el período :p. Motivo: :m', ['p' => $periodo, 'm' => $evento->motivo]),
            route('actas'), 'period.reopened:period:'.$evento->periodoId.':'.$evento->cambio));
    }

    /**
     * Avisa al estudiante de su matrícula: inscripción, retiro, cambio de grupo aprobado o rechazado (RF09, RF29).
     */
    public function matriculaCambiada(MatriculaCambiada $evento): void
    {
        $m = DB::table('enrollments as e')->join('sections', 'sections.id', '=', 'e.section_id')->join('subjects', 'subjects.id', '=', 'sections.subject_id')
            ->where('e.id', $evento->matriculaId)->first(['e.student_id', 'sections.ulid', 'sections.code as grupo', 'subjects.code', 'subjects.name']);
        $datos = ['c' => $m->code.'-'.$m->grupo, 'a' => $m->name, 'g' => $m->grupo, 'm' => $evento->motivo];

        [$titulo, $cuerpo, $url] = match ($evento->cambio) {
            'inscrita' => [__('Matrícula: :c', $datos), __('Su matrícula en :a, grupo :g, quedó registrada.', $datos), route('secciones.contenidos', ['seccion' => $m->ulid])],
            'retirada' => [__('Retiro de matrícula: :c', $datos), __('Se retiró su matrícula en :a, grupo :g. Motivo: :m', $datos), route('materias')],
            'trasladada' => [__('Cambio de grupo: :c', $datos), __('Se aprobó su cambio: ahora está en el grupo :g de :a.', $datos), route('secciones.contenidos', ['seccion' => $m->ulid])],
            'cambio_rechazado' => [__('Cambio de grupo rechazado: :c', $datos), __('No se aprobó su cambio de grupo en :a. Motivo: :m', $datos), route('materias')],
        };

        $this->enviar([$m->student_id], new Aviso('enrollment.changed', $titulo, $cuerpo, $url, 'enrollment.changed:'.$evento->matriculaId.':'.$evento->marca));
    }

    /**
     * Avisa al autor del mensaje respondido, o al autor del hilo si la respuesta va al hilo (RF30). Nadie recibe
     * aviso de su propia respuesta, ni de una respuesta que el docente ya ocultó.
     */
    public function respuestaPublicada(RespuestaPublicada $evento): void
    {
        $r = DB::table('forum_posts as p')->join('forum_threads as t', 't.id', '=', 'p.thread_id')->join('sections', 'sections.id', '=', 't.section_id')
            ->join('users as u', 'u.id', '=', 'p.author_id')->leftJoin('forum_posts as padre', 'padre.id', '=', 'p.parent_post_id')
            ->where('p.id', $evento->mensajeId)
            ->first(['p.author_id', 'p.is_hidden', 't.id as hilo', 't.title', 't.author_id as autor_hilo', 'padre.author_id as autor_padre', 'sections.ulid', 'u.first_name', 'u.last_name']);
        $destinatario = (int) ($r->autor_padre ?? $r->autor_hilo);
        if ($r->is_hidden || $destinatario === (int) $r->author_id) {
            return;
        }

        $this->enviar([$destinatario], new Aviso('forum.reply', __('Respuesta en el foro: :t', ['t' => $r->title]),
            __(':n respondió en «:t».', ['n' => trim($r->first_name.' '.$r->last_name), 't' => $r->title]),
            route('secciones.foro.hilo', ['seccion' => $r->ulid, 'hilo' => $r->hilo]), 'forum.reply:'.$evento->mensajeId));
    }

    /**
     * Avisa a los estudiantes matriculados y a los docentes de las secciones afectadas por una cancelación o un
     * cambio de modalidad aprobados (RF32): de una sección, de las carreras de una asignatura o de toda la facultad.
     */
    public function claseCancelada(ClaseCancelada $evento): void
    {
        $c = DB::table('class_cancellations as c')->leftJoin('careers', 'careers.id', '=', 'c.career_id')
            ->leftJoin('sections', 'sections.id', '=', 'c.section_id')->where('c.id', $evento->cancelacionId)
            ->first(['c.scope', 'c.section_id', 'c.career_id', 'c.date', 'c.kind', 'c.new_modality', 'c.reason', 'careers.name as carrera', 'sections.ulid as seccion']);
        $secciones = AsistenciaService::seccionesDeCancelacion($c);
        $usuarios = DB::table('enrollments')->whereIn('section_id', $secciones)->where('status', 'active')->pluck('student_id')
            ->concat(DB::table('teaching_assignments')->whereIn('section_id', $secciones)->whereNull('ended_at')->pluck('teacher_id'))->unique()->values();

        $fecha = Carbon::parse($c->date)->format('d/m/Y');
        $donde = match ($c->scope) {
            'section' => (string) DB::table('sections')->join('subjects', 'subjects.id', '=', 'sections.subject_id')->where('sections.id', $c->section_id)
                ->value(DB::raw("subjects.code || '-' || sections.code")),
            'career' => __('la carrera :c', ['c' => $c->carrera]),
            default => __('toda la facultad'),
        };
        [$titulo, $cuerpo] = $c->kind === 'modality_change'
            ? [__('Cambio de modalidad: :d', ['d' => $donde]), __('El :f, la clase de :d será :m. Motivo: :r', ['f' => $fecha, 'd' => $donde, 'm' => $c->new_modality === 'virtual' ? __('virtual') : __('presencial'), 'r' => $c->reason])]
            : [__('Clase cancelada: :d', ['d' => $donde]), __('El :f no hay clases en :d. Motivo: :r', ['f' => $fecha, 'd' => $donde, 'r' => $c->reason])];

        // La cancelación de una sección lleva a esa sección; la de una carrera o de toda la facultad no tiene una
        // sola pantalla a la que ir, y el texto del aviso ya dice la fecha, el alcance y el motivo.
        $url = $c->scope === 'section' && $c->seccion !== null ? route('secciones.contenidos', ['seccion' => $c->seccion]) : null;

        $this->enviar($usuarios->all(), new Aviso('class.cancelled', $titulo, $cuerpo, $url, 'class.cancelled:'.$evento->cancelacionId));
    }

    /**
     * Avisa al estudiante, en la plataforma y por correo, que su asistencia del cómputo bajó del umbral preventivo
     * o del crítico (RF22). Sale una sola vez por umbral y cómputo: lo decide AsistenciaService::evaluarAlertas().
     */
    public function alertaAsistencia(AlertaAsistenciaAbierta $evento): void
    {
        $a = DB::table('academic_alerts as a')->join('enrollments as e', 'e.id', '=', 'a.enrollment_id')->join('sections', 'sections.id', '=', 'e.section_id')
            ->join('subjects', 'subjects.id', '=', 'sections.subject_id')->leftJoin('grading_components as c', 'c.id', '=', 'a.grading_component_id')
            ->where('a.id', $evento->alertaId)->first(['a.alert_type', 'e.student_id', 'sections.ulid', 'subjects.code', 'subjects.name', 'c.name as computo']);
        $umbrales = AsistenciaService::umbralesDeAlerta();
        $datos = [
            'a' => $a->name, 'c' => $a->computo ?? __('la materia'), 'p' => rtrim(rtrim(number_format($evento->porcentaje, 1, '.', ''), '0'), '.'),
            'u' => rtrim(rtrim(number_format($umbrales[$a->alert_type], 1, '.', ''), '0'), '.'),
            'm' => rtrim(rtrim(number_format($umbrales['attendance_75'], 1, '.', ''), '0'), '.'),
        ];

        [$titulo, $cuerpo] = $a->alert_type === 'attendance_78'
            ? [__('Asistencia crítica: :c', ['c' => $a->code]), __('Su asistencia en :a (:c) bajó de :u %: lleva :p %. Con menos de :m % puede reprobar por inasistencia.', $datos)]
            : [__('Aviso de asistencia: :c', ['c' => $a->code]), __('Su asistencia en :a (:c) bajó de :u %: lleva :p %. Cuide no bajar de :m %.', $datos)];

        $this->enviar([$a->student_id], new Aviso('academic_alert.opened', $titulo, $cuerpo, route('secciones.asistencia', ['seccion' => $a->ulid]),
            'academic_alert.opened:'.$evento->alertaId));
    }

    /**
     * Avisa al estudiante, en la plataforma y por correo, que su nota proyectada con las notas publicadas quedó por
     * debajo de la mínima de aprobación (RF17). Sale una vez por alerta: lo decide RiesgoService::evaluarSeccion().
     */
    public function riesgoAcademico(RiesgoAcademicoDetectado $evento): void
    {
        $a = DB::table('academic_alerts as a')->join('enrollments as e', 'e.id', '=', 'a.enrollment_id')->join('sections', 'sections.id', '=', 'e.section_id')
            ->join('subjects', 'subjects.id', '=', 'sections.subject_id')->where('a.id', $evento->alertaId)
            ->first(['e.student_id', 'sections.ulid', 'subjects.code', 'subjects.name']);
        $datos = ['a' => $a->name, 'n' => number_format($evento->notaProyectada, 1), 'm' => number_format($evento->notaMinima, 1)];

        $this->enviar([$a->student_id], new Aviso('academic_alert.opened', __('Riesgo de reprobar: :c', ['c' => $a->code]),
            __('Con las notas publicadas, su nota proyectada en :a es :n y se aprueba con :m. Hable con su docente.', $datos),
            route('secciones.notas', ['seccion' => $a->ulid]), 'academic_alert.opened:'.$evento->alertaId));
    }

    /**
     * Avisa al estudiante que el Coordinador corrigió una nota publicada con el período cerrado (RF17), con el valor
     * anterior, el nuevo y el motivo. Usa el evento de nota publicada (`grade.published`): la nota que ve cambió.
     */
    public function notaCorregida(NotaCorregida $evento): void
    {
        $n = DB::table('grades')->join('grade_items', 'grade_items.id', '=', 'grades.grade_item_id')->join('enrollments as e', 'e.id', '=', 'grades.enrollment_id')
            ->join('sections', 'sections.id', '=', 'grades.section_id')->where('grades.id', $evento->notaId)
            ->first(['grade_items.title', 'e.student_id', 'sections.ulid', 'grades.updated_at']);

        $this->enviar([$n->student_id], new Aviso('grade.published', __('Nota corregida: :t', ['t' => $n->title]),
            __('Su nota cambió de :a a :b. Motivo: :m', ['a' => number_format($evento->anterior, 1), 'b' => number_format($evento->nueva, 1), 'm' => $evento->motivo]),
            route('secciones.notas', ['seccion' => $n->ulid]), 'grade.published:grade:'.$evento->notaId.':'.Carbon::parse($n->updated_at)->timestamp));
    }

    private function seccionConActa(int $seccionId): object
    {
        return DB::table('sections')->join('subjects', 'subjects.id', '=', 'sections.subject_id')->join('grade_sheets as g', 'g.section_id', '=', 'sections.id')
            ->where('sections.id', $seccionId)
            ->first(['sections.id', 'sections.ulid', 'sections.code as grupo', 'sections.subject_id', 'subjects.code', 'subjects.name', 'g.published_at', 'g.updated_at']);
    }

    private function solicitud(string $ulid): object
    {
        return DB::table('grade_review_requests as r')->join('assessments as a', 'a.id', '=', 'r.assessment_id')->join('enrollments as e', 'e.id', '=', 'r.enrollment_id')
            ->join('users as u', 'u.id', '=', 'e.student_id')->join('sections', 'sections.id', '=', 'r.section_id')->where('r.ulid', $ulid)
            ->first(['r.section_id', 'r.status', 'r.score_before', 'r.score_after', 'a.title', 'a.ulid as actividad', 'a.assessment_type', 'e.student_id',
                'u.first_name', 'u.last_name', 'sections.ulid as seccion', DB::raw('(select count(*) from grade_review_items i where i.request_id = r.id) as items')]);
    }

    /**
     * @param  array<int, int|string>  $usuarios
     */
    private function enviar(array $usuarios, Aviso $aviso): void
    {
        $this->notificaciones->enviar($usuarios, $aviso);
    }
}
