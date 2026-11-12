<?php

namespace App\Modules\Reportes\Services;

use App\Models\User;
use App\Modules\Academica\Services\PeriodoService;
use App\Modules\Asistencia\Services\AsistenciaService;
use App\Support\Alcance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Panel de inicio por rol (RF24, ADR-034): un bloque por cada rol del usuario, con datos reales.
 * Debe cargar en menos de 3 s (RNF03): consultas agregadas y acotadas.
 */
class PanelService
{
    /** Filas que muestra una lista del panel antes de mandar a la pantalla completa. */
    private const FILAS_PANEL = 6;

    public function __construct(private Alcance $alcance) {}

    /**
     * @return array<string, mixed>
     */
    public function estudiante(User $u): array
    {
        $periodo = PeriodoService::activoId();
        $matriculas = DB::table('enrollments')->where('student_id', $u->id)->where('period_id', $periodo)->where('status', 'active');
        $secciones = (clone $matriculas)->pluck('section_id');
        $asistencia = app(AsistenciaService::class)->miAsistencia($u);

        return [
            'pendientes' => DB::table('assessments as a')->join('sections', 'sections.id', '=', 'a.section_id')->join('subjects', 'subjects.id', '=', 'sections.subject_id')
                ->whereIn('a.section_id', $secciones)->where('a.closes_at', '>=', now())->where('a.opens_at', '<=', now()->addDays(14))
                // Propia o de su equipo: una entrega grupal cuenta para todos (RF15).
                ->whereNotExists(fn ($q) => $q->from('submissions as s')->whereColumn('s.assessment_id', 'a.id')->where('s.status', 'submitted')
                    ->where(fn ($w) => $w->whereIn('s.enrollment_id', DB::table('enrollments')->where('student_id', $u->id)->select('id'))
                        ->orWhereIn('s.team_id', DB::table('team_members')->whereIn('enrollment_id', DB::table('enrollments')->where('student_id', $u->id)->select('id'))->select('team_id'))))
                ->whereNotExists(fn ($q) => $q->from('quiz_attempts as q')->join('enrollments as e', 'e.id', '=', 'q.enrollment_id')->whereColumn('q.assessment_id', 'a.id')
                    ->where('e.student_id', $u->id)->where('q.status', '!=', 'in_progress'))
                ->orderBy('a.closes_at')->limit(6)->get(['a.ulid', 'a.title', 'a.assessment_type', 'a.closes_at', 'sections.ulid as seccion', 'subjects.code']),
            'asistencia' => $asistencia,
            'materias' => $this->materiasDelEstudiante($asistencia),
            'agenda' => $this->agendaDelEstudiante($secciones, (clone $matriculas)->pluck('id')),
            'sesionAbierta' => $this->sesionAbierta($u),
            'notas' => DB::table('grades as g')->join('grade_items as i', 'i.id', '=', 'g.grade_item_id')->join('sections', 'sections.id', '=', 'g.section_id')
                ->join('subjects', 'subjects.id', '=', 'sections.subject_id')->whereIn('g.enrollment_id', (clone $matriculas)->pluck('id'))
                ->where('g.status', 'published')->orderByDesc('g.published_at')->limit(5)->get(['i.title', 'g.score', 'subjects.code', 'g.published_at']),
            'clasesVirtuales' => DB::table('video_sessions as v')->join('sections', 'sections.id', '=', 'v.section_id')->join('subjects', 'subjects.id', '=', 'sections.subject_id')
                ->whereIn('v.section_id', $secciones)->where('v.starts_at', '>=', now())->orderBy('v.starts_at')->limit(3)
                ->get(['v.topic', 'v.starts_at', 'v.meet_url', 'subjects.code']),
        ];
    }

    /**
     * Recuadros de «Mis materias activas» (RF24, maqueta 02): a lo que ya trae miAsistencia se le suman el
     * docente responsable, el horario y el avance del curso.
     *
     * El avance es el **porcentaje de recursos completados** (RF13, tabla `resource_progress`): es el único
     * dato de avance que existe hoy. Todo se resuelve en 3 consultas agrupadas, no una por materia (RNF03).
     *
     * @param  Collection<int, object>  $asistencia
     * @return Collection<int, object>
     */
    private function materiasDelEstudiante(Collection $asistencia): Collection
    {
        $secciones = $asistencia->pluck('seccion_id');
        if ($secciones->isEmpty()) {
            return collect();
        }

        $docentes = DB::table('teaching_assignments as t')->join('users as u', 'u.id', '=', 't.teacher_id')
            ->whereIn('t.section_id', $secciones)->whereNull('t.ended_at')->where('t.is_responsible', true)
            ->get(['t.section_id', 'u.first_name', 'u.last_name'])
            ->mapWithKeys(fn (object $d) => [$d->section_id => trim($d->first_name.' '.$d->last_name)]);

        $horarios = DB::table('section_schedules as h')->leftJoin('classrooms as a', 'a.id', '=', 'h.classroom_id')
            ->whereIn('h.section_id', $secciones)->orderBy('h.weekday')->orderBy('h.start_time')
            ->get(['h.section_id', 'h.weekday', 'h.start_time', 'h.end_time', 'a.code as aula'])
            ->groupBy('section_id');

        // Recursos publicados y completados por matrícula, en una sola pasada.
        $publicados = DB::table('resources as r')->join('units as un', 'un.id', '=', 'r.unit_id')
            ->whereIn('un.section_id', $secciones)->whereNull('r.deleted_at')->whereNull('un.deleted_at')
            ->selectRaw('un.section_id, count(*) as total')->groupBy('un.section_id')->pluck('total', 'un.section_id');

        $completados = DB::table('resource_progress as p')->join('resources as r', 'r.id', '=', 'p.resource_id')
            ->join('units as un', 'un.id', '=', 'r.unit_id')->whereIn('p.enrollment_id', $asistencia->pluck('id'))
            ->whereNull('r.deleted_at')->selectRaw('p.enrollment_id, count(*) as total')
            ->groupBy('p.enrollment_id')->pluck('total', 'p.enrollment_id');

        return $asistencia->map(function (object $m) use ($docentes, $horarios, $publicados, $completados): object {
            $total = (int) ($publicados[$m->seccion_id] ?? 0);
            $hechos = (int) ($completados[$m->id] ?? 0);

            $m->docente = $docentes[$m->seccion_id] ?? null;
            $m->horario = $horarios[$m->seccion_id] ?? collect();
            $m->avance = $total > 0 ? (int) round(100 * $hechos / $total) : null;
            $m->recursos = ['hechos' => $hechos, 'total' => $total];

            return $m;
        })->values();
    }

    /**
     * Agenda de los próximos 7 días (RF24, CA1 «la lista de pendientes ordenada por fecha y el calendario»):
     * una sola línea de tiempo con las clases del horario, las entregas que vencen y las videoconferencias.
     *
     * Las clases de un día con cancelación aprobada no se muestran (RF32). Las videoconferencias solo
     * aparecen cuando existan: hoy únicamente las siembra la demo, porque RF21 sigue pendiente.
     *
     * @param  Collection<int, int>  $secciones
     * @param  Collection<int, int>  $matriculas
     * @return Collection<string, Collection<int, object>> día (Y-m-d) => entradas ordenadas por hora
     */
    private function agendaDelEstudiante(Collection $secciones, Collection $matriculas): Collection
    {
        $desde = now()->startOfDay();
        $hasta = (clone $desde)->addDays(7)->endOfDay();
        $entradas = collect();

        if ($secciones->isEmpty()) {
            return collect();
        }

        $canceladas = DB::table('class_cancellations')->where('status', 'approved')->where('kind', 'cancellation')
            ->whereBetween('date', [$desde->toDateString(), $hasta->toDateString()])
            ->where(fn ($q) => $q->whereIn('section_id', $secciones)->orWhereIn('scope', ['career', 'faculty']))
            ->get(['section_id', 'date', 'scope'])
            ->map(fn (object $c) => ($c->scope === 'section' ? $c->section_id : 'todas').'|'.Carbon::parse($c->date)->toDateString())->flip();

        // Clases del horario, expandidas día a día sobre la semana que viene.
        $horario = DB::table('section_schedules as h')->join('sections as s', 's.id', '=', 'h.section_id')
            ->join('subjects as m', 'm.id', '=', 's.subject_id')->leftJoin('classrooms as a', 'a.id', '=', 'h.classroom_id')
            ->whereIn('h.section_id', $secciones)
            ->get(['h.section_id', 'h.weekday', 'h.start_time', 'h.end_time', 'a.code as aula', 'm.code', 'm.name', 's.ulid as seccion']);

        foreach (range(0, 7) as $dia) {
            $fecha = (clone $desde)->addDays($dia);
            foreach ($horario as $clase) {
                // weekday 1 = lunes, como en el ER; Carbon usa 0 = domingo.
                if ((int) $clase->weekday !== ($fecha->dayOfWeek === 0 ? 7 : $fecha->dayOfWeek)) {
                    continue;
                }
                if ($canceladas->has($clase->section_id.'|'.$fecha->toDateString()) || $canceladas->has('todas|'.$fecha->toDateString())) {
                    continue;
                }
                $entradas->push((object) [
                    'tipo' => 'clase', 'cuando' => $fecha->copy()->setTimeFromTimeString($clase->start_time),
                    'titulo' => $clase->name, 'code' => $clase->code, 'seccion' => $clase->seccion,
                    'detalle' => $clase->aula ? __('Aula :a', ['a' => $clase->aula]) : null,
                    'hasta' => $clase->end_time, 'url' => null,
                ]);
            }
        }

        foreach (DB::table('assessments as a')->join('sections as s', 's.id', '=', 'a.section_id')
            ->join('subjects as m', 'm.id', '=', 's.subject_id')->whereIn('a.section_id', $secciones)
            ->whereBetween('a.closes_at', [$desde, $hasta])
            ->get(['a.ulid', 'a.title', 'a.assessment_type', 'a.closes_at', 'm.code', 's.ulid as seccion']) as $e) {
            $entradas->push((object) [
                'tipo' => 'entrega', 'cuando' => Carbon::parse($e->closes_at),
                'titulo' => $e->title, 'code' => $e->code, 'seccion' => $e->seccion,
                'detalle' => __('Cierra'), 'hasta' => null,
                'url' => route($e->assessment_type === 'quiz' ? 'secciones.cuestionarios.show' : 'secciones.tareas.show',
                    ['seccion' => $e->seccion, $e->assessment_type === 'quiz' ? 'cuestionario' : 'tarea' => $e->ulid]),
            ]);
        }

        foreach (DB::table('video_sessions as v')->join('sections as s', 's.id', '=', 'v.section_id')
            ->join('subjects as m', 'm.id', '=', 's.subject_id')->whereIn('v.section_id', $secciones)
            ->whereBetween('v.starts_at', [$desde, $hasta])
            ->get(['v.topic', 'v.starts_at', 'v.meet_url', 'm.code', 's.ulid as seccion']) as $v) {
            $entradas->push((object) [
                'tipo' => 'videoconferencia', 'cuando' => Carbon::parse($v->starts_at),
                'titulo' => $v->topic, 'code' => $v->code, 'seccion' => $v->seccion,
                'detalle' => __('En línea'), 'hasta' => null, 'url' => $v->meet_url,
            ]);
        }

        return $entradas->sortBy('cuando')->groupBy(fn (object $e) => $e->cuando->toDateString());
    }

    /**
     * Sesión de asistencia abierta de una de sus materias (RF24: tarjeta «Marcar asistencia» al inicio).
     */
    private function sesionAbierta(User $u): ?object
    {
        return DB::table('attendance_sessions as a')->join('enrollments as e', 'e.section_id', '=', 'a.section_id')
            ->join('sections as s', 's.id', '=', 'a.section_id')->join('subjects as m', 'm.id', '=', 's.subject_id')
            ->where('e.student_id', $u->id)->where('e.status', 'active')->where('a.status', 'open')
            ->orderByDesc('a.opened_at')
            ->first(['m.code', 'm.name', 's.ulid as seccion']);
    }

    /**
     * @return array<string, mixed>
     */
    public function docente(User $u): array
    {
        $secciones = DB::table('teaching_assignments')->join('sections', 'sections.id', '=', 'teaching_assignments.section_id')
            ->where('teacher_id', $u->id)->whereNull('ended_at')->where('sections.period_id', PeriodoService::activoId())->pluck('sections.id');
        $dia = (int) now()->dayOfWeekIso;

        return [
            'clasesHoy' => DB::table('section_schedules as h')->join('sections', 'sections.id', '=', 'h.section_id')->join('subjects', 'subjects.id', '=', 'sections.subject_id')
                ->leftJoin('classrooms', 'classrooms.id', '=', 'h.classroom_id')->whereIn('h.section_id', $secciones)->where('h.weekday', $dia)->orderBy('h.start_time')
                ->get(['sections.ulid', 'sections.code as grupo', 'subjects.code', 'subjects.name', 'h.start_time', 'h.end_time', 'classrooms.code as aula',
                    DB::raw("(select s.ulid from attendance_sessions s where s.section_id = sections.id and s.status = 'open' limit 1) as sesion_abierta")]),
            'porCalificar' => DB::table('submissions as s')->join('assessments as a', 'a.id', '=', 's.assessment_id')
                ->leftJoin('grades as g', fn ($j) => $j->on('g.enrollment_id', '=', 's.enrollment_id')->on('g.grade_item_id', '=', 'a.grade_item_id'))
                ->whereIn('s.section_id', $secciones)->where('s.status', 'submitted')->whereNull('g.id')->count()
                + DB::table('quiz_attempts as q')->join('assessments as a', 'a.id', '=', 'q.assessment_id')->whereIn('a.section_id', $secciones)->where('q.status', 'pending_grading')->count(),
            'matricula' => DB::table('enrollments')->whereIn('section_id', $secciones)->where('status', 'active')->count(),
            'secciones' => $secciones->count(),
            'alertas' => DB::table('academic_alerts as a')->join('enrollments as e', 'e.id', '=', 'a.enrollment_id')->join('users as u', 'u.id', '=', 'e.student_id')
                ->join('sections', 'sections.id', '=', 'e.section_id')->join('subjects', 'subjects.id', '=', 'sections.subject_id')
                // El preventivo (85 %) solo avisa al estudiante; el docente ve desde el crítico (RF22, Flujo Alerta de inasistencias).
                ->whereIn('e.section_id', $secciones)->whereNull('a.closed_at')->where('a.alert_type', '!=', 'attendance_85')->orderByDesc('a.opened_at')->limit(5)
                ->get(['a.alert_type', 'u.first_name', 'u.last_name', 'subjects.code']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function coordinador(User $u): array
    {
        $periodo = PeriodoService::activoId();
        $secciones = $this->alcance->secciones(DB::table('sections')->where('period_id', $periodo), $u, 'secciones.gestionar')->pluck('sections.id');

        return $this->gestion($secciones) + [
            'pendientes' => [
                'cambios' => $this->alcance->secciones(DB::table('section_change_requests as r')->join('enrollments as e', 'e.id', '=', 'r.enrollment_id')
                    ->where('r.status', 'pending'), $u, 'matricula.gestionar', 'e.section_id')->count(),
                'actas' => DB::table('grade_sheets')->whereIn('section_id', $secciones)->where('status', 'published')->count(),
                'cancelaciones' => DB::table('class_cancellations')->whereIn('section_id', $secciones)->where('status', 'requested')->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jefe(User $u): array
    {
        $periodo = PeriodoService::activoId();
        $deps = $this->alcance->departamentos($u);
        $secciones = DB::table('sections')->join('subjects', 'subjects.id', '=', 'sections.subject_id')->where('sections.period_id', $periodo)->whereIn('subjects.department_id', $deps)->pluck('sections.id');

        return $this->gestion($secciones) + [
            'actas' => ['validadas' => DB::table('grade_sheets')->whereIn('section_id', $secciones)->where('status', 'validated')->count(), 'total' => $secciones->count()],
            'auditoria' => DB::table('audit_logs')->whereIn('department_id', $deps)->orderByDesc('occurred_at')->limit(4)->get(['event_type', 'entity_type', 'occurred_at']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function administrador(User $u): array
    {
        $periodo = DB::table('academic_periods')->where('status', 'active')->first(['id', 'name', 'end_date']);
        $secciones = DB::table('sections')->where('period_id', $periodo?->id)->pluck('id');

        return $this->gestion($secciones) + [
            'periodo' => $periodo,
            'cierres' => ['autorizados' => DB::table('department_closures')->where('period_id', $periodo?->id)->count(), 'departamentos' => DB::table('departments')->count()],
            'usuarios' => ['activos' => DB::table('users')->where('is_active', true)->count(), 'accesosHoy' => DB::table('login_history')->where('occurred_at', '>=', now()->startOfDay())->where('result', 'success')->count()],
            'colas' => DB::table('failed_jobs')->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function soporte(User $u): array
    {
        return [
            'solicitudes' => DB::table('device_change_requests')->where('status', 'pending')->count(),
            'rechazosHoy' => DB::table('attendance_scan_attempts')->where('occurred_at', '>=', now()->startOfDay())->where('result', '!=', 'ok')
                ->groupBy('result')->selectRaw('result, count(*) as total')->pluck('total', 'result')->map(fn ($n) => (int) $n)->all(),
            'sinTelefono' => DB::table('student_careers')->where('status', 'active')
                ->whereNotIn('student_id', DB::table('student_devices')->where('status', 'active')->select('student_id'))->count(),
            'pendientes' => DB::table('device_change_requests as r')->join('users as u', 'u.id', '=', 'r.student_id')
                ->leftJoin('student_profiles as p', 'p.user_id', '=', 'u.id')
                ->where('r.status', 'pending')->orderBy('r.created_at')
                ->limit(self::FILAS_PANEL)->get(['r.id', 'r.created_at', 'r.reason', 'u.first_name', 'u.last_name', 'p.student_code']),
            'ultimosRechazos' => DB::table('attendance_scan_attempts as a')->join('users as u', 'u.id', '=', 'a.student_id')
                ->leftJoin('student_profiles as p', 'p.user_id', '=', 'u.id')
                ->leftJoin('attendance_sessions as s', 's.id', '=', 'a.session_id')
                ->leftJoin('sections as se', 'se.id', '=', 's.section_id')
                ->leftJoin('subjects as su', 'su.id', '=', 'se.subject_id')
                ->where('a.result', '!=', 'ok')->orderByDesc('a.occurred_at')
                ->limit(self::FILAS_PANEL)->get(['a.id', 'a.occurred_at', 'a.result', 'u.first_name', 'u.last_name', 'p.student_code', 'su.code as asignatura']),
        ];
    }

    /**
     * Indicadores comunes de gestión sobre un conjunto de secciones.
     *
     * @param  Collection<int, mixed>  $secciones
     * @return array<string, mixed>
     */
    private function gestion(Collection $secciones): array
    {
        $asistencia = DB::table('attendance_records as r')->whereIn('r.section_id', $secciones)->selectRaw(AsistenciaService::porcentajeSql().' as p')->value('p');

        return [
            'secciones' => $secciones->count(),
            'matricula' => DB::table('enrollments')->whereIn('section_id', $secciones)->where('status', 'active')->count(),
            'sinDocente' => DB::table('sections')->whereIn('id', $secciones)
                ->whereNotExists(fn ($q) => $q->from('teaching_assignments as t')->whereColumn('t.section_id', 'sections.id')->whereNull('t.ended_at'))->count(),
            // «En riesgo»: bajo el mínimo de asistencia del cómputo (RF22) o de reprobar por notas (RF17).
            'riesgo' => DB::table('academic_alerts as a')->join('enrollments as e', 'e.id', '=', 'a.enrollment_id')->whereIn('e.section_id', $secciones)
                ->whereNull('a.closed_at')->whereIn('a.alert_type', ['attendance_75', 'grade_risk'])->distinct()->count('a.enrollment_id'),
            'asistencia' => $asistencia === null ? null : (float) $asistencia,
        ];
    }
}
