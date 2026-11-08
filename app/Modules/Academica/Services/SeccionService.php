<?php

namespace App\Modules\Academica\Services;

use App\Models\User;
use App\Modules\Identidad\Services\PerfilContextualService;
use App\Support\Alcance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Secciones: cabecera de la Vista de sección, listado por ámbito y estudiantes de una sección.
 */
class SeccionService
{
    /** Ámbito del listado => permiso que define el alcance (docs/13 Layout/Mapa de vistas.md). */
    public const AMBITOS = ['propias' => 'contenidos.gestionar', 'carrera' => 'contenidos.ver', 'departamento' => 'matricula.gestionar'];

    public function __construct(private Alcance $alcance, private PerfilContextualService $perfiles) {}

    /**
     * Ámbito por defecto según los roles: docente → propias; coordinador → carrera; jefe → departamento.
     */
    public function ambitoPorDefecto(User $usuario): ?string
    {
        foreach (['propias' => 'docente', 'carrera' => 'coordinador', 'departamento' => 'jefe_departamento'] as $ambito => $rol) {
            if ($usuario->hasRole($rol)) {
                return $ambito;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function ambitos(User $usuario): array
    {
        return array_keys(array_filter(['propias' => 'docente', 'carrera' => 'coordinador', 'departamento' => 'jefe_departamento'], fn ($rol) => $usuario->hasRole($rol)));
    }

    /**
     * Secciones del período activo en el ámbito pedido.
     *
     * @return Collection<int, object>
     */
    public function listar(User $usuario, string $ambito, string $buscar = ''): Collection
    {
        abort_unless(in_array($ambito, $this->ambitos($usuario), true), 403);
        $buscar = mb_strtolower(trim($buscar));

        $consulta = DB::table('sections')->join('subjects', 'subjects.id', '=', 'sections.subject_id')
            ->join('academic_periods', 'academic_periods.id', '=', 'sections.period_id')->where('academic_periods.status', 'active')
            ->when($buscar !== '', fn ($q) => $q->where(fn ($q) => $q->whereRaw('lower(subjects.code) like ?', ["%{$buscar}%"])->orWhereRaw('lower(subjects.name) like ?', ["%{$buscar}%"])))
            ->select('sections.id', 'sections.ulid', 'sections.code as grupo', 'sections.max_capacity', 'subjects.code', 'subjects.name',
                DB::raw("(select count(*) from enrollments e where e.section_id = sections.id and e.status = 'active') as inscritos"),
                DB::raw('(select count(distinct a.enrollment_id) from academic_alerts a join enrollments e on e.id = a.enrollment_id where e.section_id = sections.id and a.closed_at is null) as alertas'))
            ->orderBy('subjects.code')->orderBy('sections.code');

        $secciones = $this->alcance->secciones($consulta, $usuario, self::AMBITOS[$ambito])->get();
        $docentes = DB::table('teaching_assignments')->join('users', 'users.id', '=', 'teaching_assignments.teacher_id')
            ->whereIn('section_id', $secciones->pluck('id'))->whereNull('ended_at')->where('is_responsible', true)
            ->get(['section_id', DB::raw("users.first_name || ' ' || users.last_name as nombre")])->pluck('nombre', 'section_id');
        $dias = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
        $horarios = DB::table('section_schedules')->leftJoin('classrooms', 'classrooms.id', '=', 'section_schedules.classroom_id')
            ->whereIn('section_id', $secciones->pluck('id'))->orderBy('weekday')->get()->groupBy('section_id');

        return $secciones->each(function ($s) use ($docentes, $horarios, $dias) {
            $s->docente = $docentes[$s->id] ?? null;
            $s->horario = ($horarios[$s->id] ?? collect())->map(fn ($h) => $dias[$h->weekday].' '.substr($h->start_time, 0, 5))->implode(' · ');
        });
    }

    /**
     * Estudiantes de una sección con su asistencia, promedio y alertas (Vista de sección › Estudiantes).
     *
     * @return Collection<int, object>
     */
    public function estudiantes(User $usuario, int $seccionId): Collection
    {
        abort_unless($this->alcance->puedeEnSeccion($usuario, 'matricula.gestionar', $seccionId), 403);

        return DB::table('enrollments as e')->join('users as u', 'u.id', '=', 'e.student_id')->leftJoin('student_profiles as p', 'p.user_id', '=', 'u.id')
            ->where('e.section_id', $seccionId)->whereIn('e.status', ['active', 'withdrawn', 'transferred'])
            ->select('e.id', 'e.status', 'e.enrolled_via', 'u.first_name', 'u.last_name', 'u.institutional_email', 'p.student_code',
                DB::raw("(select round(100.0 * sum(case when r.status in ('present', 'justified') then 1 when r.status = 'late' then 0.5 else 0 end) / count(*), 1) from attendance_records r where r.enrollment_id = e.id) as asistencia"),
                DB::raw("(select round(avg(g.score), 1) from grades g where g.enrollment_id = e.id and g.status = 'published') as promedio"),
                DB::raw('(select count(*) from academic_alerts a where a.enrollment_id = e.id and a.closed_at is null) as alertas'))
            ->orderByRaw("case when e.status = 'active' then 0 else 1 end")->orderBy('u.last_name')->get();
    }

    /**
     * Participantes de la sección (RF36, ADR-056): sus docentes vigentes, sus estudiantes activos y los coordinadores de
     * las carreras cuyo plan incluye la asignatura. Solo nombre, rol y enlace al perfil, que va únicamente donde quien
     * mira tiene vínculo (por ejemplo, un Coordinador no abre a un estudiante de otra carrera inscrito en la sección).
     *
     * @return array{docentes: Collection<int, \stdClass>, estudiantes: Collection<int, \stdClass>, coordinadores: Collection<int, \stdClass>}
     */
    public function participantes(User $usuario, int $seccionId): array
    {
        abort_unless($this->alcance->puedeEnSeccion($usuario, 'perfiles.ver', $seccionId), 403);

        $docentes = DB::table('teaching_assignments as t')->join('users as u', 'u.id', '=', 't.teacher_id')
            ->where('t.section_id', $seccionId)->whereNull('t.ended_at')
            ->orderByDesc('t.is_responsible')->orderBy('u.last_name')->get(['u.id', 'u.first_name', 'u.last_name', 'u.avatar_url', 't.is_responsible']);
        $estudiantes = DB::table('enrollments as e')->join('users as u', 'u.id', '=', 'e.student_id')
            ->where('e.section_id', $seccionId)->where('e.status', 'active')
            ->orderBy('u.last_name')->orderBy('u.first_name')->get(['u.id', 'u.first_name', 'u.last_name', 'u.avatar_url']);
        $coordinadores = DB::table('career_coordinators as cc')->join('users as u', 'u.id', '=', 'cc.user_id')->join('careers as c', 'c.id', '=', 'cc.career_id')
            ->whereNull('cc.valid_to')->whereIn('cc.career_id', DB::table('career_subjects')->join('study_plans', 'study_plans.id', '=', 'career_subjects.study_plan_id')
            ->where('career_subjects.subject_id', DB::table('sections')->where('id', $seccionId)->select('subject_id'))->select('study_plans.career_id'))
            ->orderBy('c.code')->get(['u.id', 'u.first_name', 'u.last_name', 'u.avatar_url', 'c.code as carrera']);

        $visibles = $this->perfiles->visibles($usuario, [...$docentes->pluck('id'), ...$estudiantes->pluck('id'), ...$coordinadores->pluck('id')]);
        foreach ([$docentes, $estudiantes, $coordinadores] as $lista) {
            // Sin flecha: each() se detiene si la función devuelve false, y la asignación devolvería el booleano.
            foreach ($lista as $p) {
                $p->visible = in_array((int) $p->id, $visibles, true);
            }
        }

        return ['docentes' => $docentes, 'estudiantes' => $estudiantes, 'coordinadores' => $coordinadores];
    }

    public function idPorUlid(string $ulid): int
    {
        return (int) (DB::table('sections')->where('ulid', $ulid)->value('id') ?? abort(404));
    }

    /**
     * @return array{ulid: string, titulo: string, codigo: string, asignatura: string, grupo: string, periodo: string, periodoCerrado: bool, docentes: array<int, string>, horario: array<int, string>}|null
     */
    public function resumen(string $ulid): ?array
    {
        $seccion = DB::table('sections')
            ->join('subjects', 'subjects.id', '=', 'sections.subject_id')
            ->join('academic_periods', 'academic_periods.id', '=', 'sections.period_id')
            ->where('sections.ulid', $ulid)
            ->first(['sections.id', 'sections.ulid', 'sections.code as grupo', 'subjects.code', 'subjects.name', 'academic_periods.name as periodo', 'academic_periods.status']);

        if ($seccion === null) {
            return null;
        }

        $docentes = DB::table('teaching_assignments')->join('users', 'users.id', '=', 'teaching_assignments.teacher_id')
            ->where('section_id', $seccion->id)->whereNull('ended_at')->orderByDesc('is_responsible')
            ->get(['users.first_name', 'users.last_name'])->map(fn ($u) => $u->first_name.' '.$u->last_name)->all();

        $dias = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
        $horario = DB::table('section_schedules')->leftJoin('classrooms', 'classrooms.id', '=', 'section_schedules.classroom_id')
            ->where('section_id', $seccion->id)->orderBy('weekday')
            ->get(['weekday', 'start_time', 'end_time', 'classrooms.code as aula'])
            ->map(fn ($h) => $dias[$h->weekday].' '.substr($h->start_time, 0, 5).'–'.substr($h->end_time, 0, 5).($h->aula ? ' · '.$h->aula : ''))
            ->all();

        return [
            'ulid' => $seccion->ulid,
            'titulo' => $seccion->code.' · '.$seccion->grupo,
            'codigo' => $seccion->code,
            'asignatura' => $seccion->name,
            'grupo' => $seccion->grupo,
            'periodo' => $seccion->periodo,
            'periodoCerrado' => $seccion->status === 'closed',
            'docentes' => $docentes,
            'horario' => $horario,
        ];
    }
}
