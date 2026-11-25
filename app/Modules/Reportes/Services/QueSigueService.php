<?php

namespace App\Modules\Reportes\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bloque «Qué sigue» del Inicio (RF24, QA 24/11/2026): de 3 a 5 pendientes del rol, ordenados por fecha, cada uno con
 * el botón que lo resuelve. Solo reúne datos que ya existen; cada destino exige el permiso que pide su pantalla.
 *
 * @phpstan-type Pendiente array{titulo: string, detalle: string, fecha: Carbon|null, accion: string, url: string, tono: string, externo: bool}
 */
class QueSigueService
{
    public const MAXIMO = 5;

    /**
     * @param  Collection<int, mixed>  $secciones
     * @return list<Pendiente>
     */
    public function docente(User $u, Collection $secciones): array
    {
        $pendientes = [];
        $dia = (int) now()->dayOfWeekIso;

        // Clases de hoy que todavía no tienen sesión de asistencia.
        $clases = DB::table('section_schedules as h')->join('sections', 'sections.id', '=', 'h.section_id')->join('subjects', 'subjects.id', '=', 'sections.subject_id')
            ->whereIn('h.section_id', $secciones)->where('h.weekday', $dia)
            ->whereNotExists(fn ($q) => $q->from('attendance_sessions as s')->whereColumn('s.section_id', 'sections.id')->whereDate('s.session_date', now()->toDateString()))
            ->orderBy('h.start_time')->get(['sections.ulid', 'sections.code as grupo', 'subjects.code', 'h.start_time']);
        foreach ($clases as $c) {
            $pendientes[] = $this->pendiente(__('Tomar asistencia de :m · :g', ['m' => $c->code, 'g' => $c->grupo]), __('Clase de hoy a las :h', ['h' => substr($c->start_time, 0, 5)]),
                Carbon::parse(now()->toDateString().' '.$c->start_time), __('Abrir asistencia'), route('secciones.asistencia', ['seccion' => $c->ulid]), 'aviso');
        }

        // Entregas por calificar, agrupadas por actividad: la más antigua manda.
        $entregas = DB::table('submissions as s')->join('assessments as a', 'a.id', '=', 's.assessment_id')->join('sections', 'sections.id', '=', 's.section_id')
            ->leftJoin('grades as g', fn ($j) => $j->on('g.enrollment_id', '=', 's.enrollment_id')->on('g.grade_item_id', '=', 'a.grade_item_id'))
            ->whereIn('s.section_id', $secciones)->where('s.status', 'submitted')->whereNull('g.id')
            ->groupBy('a.ulid', 'a.title', 'sections.ulid')->selectRaw('a.ulid, a.title, sections.ulid as seccion, count(*) as total, min(s.submitted_at) as desde')
            ->orderBy('desde')->limit(self::MAXIMO)->get();
        foreach ($entregas as $e) {
            $pendientes[] = $this->pendiente(__('Calificar «:t»', ['t' => $e->title]), trans_choice('{1} 1 entrega sin nota|[2,*] :count entregas sin nota', (int) $e->total),
                $e->desde ? Carbon::parse($e->desde) : null, __('Calificar'), route('secciones.tareas.show', ['seccion' => $e->seccion, 'tarea' => $e->ulid]), 'aviso');
        }

        // Ensayos de cuestionario por calificar: una sola fila que lleva a Pendientes de calificar.
        $ensayos = DB::table('quiz_attempts as q')->join('assessments as a', 'a.id', '=', 'q.assessment_id')->whereIn('a.section_id', $secciones)->where('q.status', 'pending_grading')
            ->selectRaw('count(*) as total, min(q.submitted_at) as desde')->first();
        if ($ensayos && (int) $ensayos->total > 0 && $u->checkPermissionTo('calificaciones.gestionar')) {
            $pendientes[] = $this->pendiente(__('Calificar ensayos de cuestionarios'), trans_choice('{1} 1 intento por calificar|[2,*] :count intentos por calificar', (int) $ensayos->total),
                $ensayos->desde ? Carbon::parse($ensayos->desde) : null, __('Calificar'), route('pendientes-de-calificar'), 'aviso');
        }

        // Solicitudes de revisión de nota sin resolver.
        $revisiones = DB::table('grade_review_requests as r')->join('sections', 'sections.id', '=', 'r.section_id')->join('assessments as a', 'a.id', '=', 'r.assessment_id')
            ->whereIn('r.section_id', $secciones)->where('r.status', 'pending')->orderBy('r.requested_at')->limit(self::MAXIMO)
            ->get(['r.ulid', 'r.requested_at', 'a.title', 'sections.ulid as seccion']);
        foreach ($revisiones as $r) {
            $pendientes[] = $this->pendiente(__('Resolver una revisión de nota'), (string) $r->title, Carbon::parse($r->requested_at), __('Resolver'),
                route('secciones.revisiones.show', ['seccion' => $r->seccion, 'revision' => $r->ulid]), 'error');
        }

        return $this->ordenar($pendientes);
    }

    /**
     * @param  Collection<int, mixed>  $secciones
     * @return list<Pendiente>
     */
    public function coordinador(User $u, Collection $secciones): array
    {
        $pendientes = [];

        if ($u->checkPermissionTo('actas.validar')) {
            $actas = DB::table('grade_sheets as g')->join('sections', 'sections.id', '=', 'g.section_id')->join('subjects', 'subjects.id', '=', 'sections.subject_id')
                ->whereIn('g.section_id', $secciones)->where('g.status', 'published')->orderBy('g.published_at')->limit(self::MAXIMO)
                ->get(['g.published_at', 'subjects.code', 'sections.code as grupo']);
            foreach ($actas as $a) {
                $pendientes[] = $this->pendiente(__('Validar el acta de :m · :g', ['m' => $a->code, 'g' => $a->grupo]), __('Publicada por el docente'),
                    $a->published_at ? Carbon::parse($a->published_at) : null, __('Validar'), route('actas'), 'aviso');
            }
        }

        if ($u->checkPermissionTo('clases.cancelar')) {
            $cancelaciones = DB::table('class_cancellations as c')->join('sections', 'sections.id', '=', 'c.section_id')->join('subjects', 'subjects.id', '=', 'sections.subject_id')
                ->whereIn('c.section_id', $secciones)->where('c.status', 'requested')->orderBy('c.date')->limit(self::MAXIMO)
                ->get(['c.date', 'c.kind', 'subjects.code', 'sections.code as grupo']);
            foreach ($cancelaciones as $c) {
                $pendientes[] = $this->pendiente(
                    $c->kind === 'cancellation' ? __('Decidir la cancelación de :m · :g', ['m' => $c->code, 'g' => $c->grupo]) : __('Decidir el cambio de modalidad de :m · :g', ['m' => $c->code, 'g' => $c->grupo]),
                    __('Clase del :f', ['f' => Carbon::parse($c->date)->format('d/m/Y')]), Carbon::parse($c->date), __('Revisar'), route('clases-canceladas'), 'aviso');
            }
        }

        return $this->ordenar($pendientes);
    }

    /**
     * @param  Collection<int, mixed>  $secciones
     * @return list<Pendiente>
     */
    public function jefe(User $u, Collection $secciones, int $sinDocente): array
    {
        $pendientes = [];

        if ($u->checkPermissionTo('matricula.cambio.resolver')) {
            $cambios = DB::table('section_change_requests as r')->join('enrollments as e', 'e.id', '=', 'r.enrollment_id')->join('users as u', 'u.id', '=', 'e.student_id')
                ->whereIn('e.section_id', $secciones)->where('r.status', 'pending')->orderBy('r.created_at')->limit(self::MAXIMO)
                ->get(['r.created_at', 'u.first_name', 'u.last_name']);
            foreach ($cambios as $c) {
                $pendientes[] = $this->pendiente(__('Resolver un cambio de grupo'), $c->first_name.' '.$c->last_name, Carbon::parse($c->created_at), __('Resolver'), route('matricula.solicitudes'), 'aviso');
            }
        }

        $pendientes = [...$pendientes, ...$this->sinDocente($u, $sinDocente)];

        if ($u->checkPermissionTo('cierre.autorizar') && $secciones->isNotEmpty()) {
            $porValidar = DB::table('sections')->whereIn('id', $secciones)
                ->whereNotExists(fn ($q) => $q->from('grade_sheets as g')->whereColumn('g.section_id', 'sections.id')->where('g.status', 'validated'))->count();
            $fin = DB::table('academic_periods')->where('status', 'active')->value('end_date');
            if ($porValidar === 0) {
                $pendientes[] = $this->pendiente(__('Autorizar el cierre del departamento'), __('Todas las actas están validadas'), $fin ? Carbon::parse($fin) : null, __('Autorizar'), route('cierre'), 'error');
            }
        }

        return $this->ordenar($pendientes);
    }

    /**
     * @return list<Pendiente>
     */
    public function administrador(User $u, ?object $periodo, int $sinDocente, int $autorizados, int $departamentos, int $fallidos): array
    {
        $pendientes = $this->sinDocente($u, $sinDocente);

        if ($fallidos > 0 && $u->checkPermissionTo('horizon.ver')) {
            $pendientes[] = $this->pendiente(__('Revisar los trabajos fallidos'), trans_choice('{1} 1 trabajo falló en las colas|[2,*] :count trabajos fallaron en las colas', $fallidos), null,
                __('Revisar'), route('horizon.index'), 'error', externo: true);
        }

        if ($periodo !== null && $autorizados < $departamentos && $u->checkPermissionTo('periodos.gestionar')) {
            $pendientes[] = $this->pendiente(__('Seguir el cierre del período :p', ['p' => $periodo->name]), __(':a de :d departamentos autorizaron su cierre', ['a' => $autorizados, 'd' => $departamentos]),
                $periodo->end_date ? Carbon::parse($periodo->end_date) : null, __('Ver cierres'), route('periodos.show', ['periodo' => $periodo->id]), 'aviso');
        }

        return $this->ordenar($pendientes);
    }

    /**
     * @return list<Pendiente>
     */
    private function sinDocente(User $u, int $sinDocente): array
    {
        if ($sinDocente === 0 || ! $u->checkPermissionTo('secciones.asignar-docente')) {
            return [];
        }

        return [$this->pendiente(__('Asignar docente a los grupos teóricos'), trans_choice('{1} 1 grupo teórico sin docente|[2,*] :count grupos teóricos sin docente', $sinDocente), null,
            __('Asignar'), route('carga-academica', ['estado' => 'sin_docente']), 'error')];
    }

    /**
     * @return Pendiente
     */
    private function pendiente(string $titulo, string $detalle, ?Carbon $fecha, string $accion, string $url, string $tono, bool $externo = false): array
    {
        return ['titulo' => $titulo, 'detalle' => $detalle, 'fecha' => $fecha, 'accion' => $accion, 'url' => $url, 'tono' => $tono, 'externo' => $externo];
    }

    /**
     * Lo que tiene fecha va primero, de la más próxima o antigua a la más lejana; lo que no tiene fecha, al final.
     *
     * @param  list<Pendiente>  $pendientes
     * @return list<Pendiente>
     */
    private function ordenar(array $pendientes): array
    {
        usort($pendientes, fn (array $a, array $b) => [$a['fecha'] === null, $a['fecha']?->getTimestamp() ?? 0] <=> [$b['fecha'] === null, $b['fecha']?->getTimestamp() ?? 0]);

        return array_slice($pendientes, 0, self::MAXIMO);
    }
}
