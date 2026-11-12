<?php

namespace App\Modules\Reportes\Services;

use App\Models\User;
use App\Modules\Academica\Services\PeriodoService;
use App\Support\Alcance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Indicadores de desempeño docente (RF33, maqueta 27) de un período, para el Jefe (D). Son informativos: no califican
 * al docente. Cálculo (decidido por el usuario el 01/11/2026):
 * - Retraso: minutos promedio entre el inicio programado de la clase y la apertura de la sesión de asistencia; abrir
 *   antes cuenta 0. Se compara con el bloque del horario de ese día cuyo inicio está más cerca de la apertura; una
 *   sesión abierta un día sin clase en el horario no cuenta. Al lado, el % abiertas hasta 15 min después del inicio.
 * - Recursos: recursos publicados en sus secciones.
 * - Revisión: horas promedio desde la entrega hasta la publicación de su nota, solo con la nota ya publicada; las que
 *   siguen sin publicar se cuentan aparte.
 * - Notas publicadas: % de actividades con notas publicadas entre las que ya cerraron.
 */
class DesempenoService
{
    /** Minutos después del inicio hasta los que una apertura cuenta «a tiempo». */
    public const TOLERANCIA_MINUTOS = 15;

    public function __construct(private Alcance $alcance) {}

    /**
     * Indicadores de los docentes del alcance en un período (RF33): el elegido, o el activo si no se elige.
     *
     * @return Collection<int, object>
     */
    public function indicadores(User $jefe, ?int $periodoId = null): Collection
    {
        $alcance = $this->alcance->exigir($jefe, 'desempeno-docente.ver');
        $periodo = $periodoId ?? PeriodoService::activoId();

        $docentes = DB::table('teacher_profiles as t')->join('users as u', 'u.id', '=', 't.user_id')->join('departments as d', 'd.id', '=', 't.department_id')
            ->when($alcance !== 'G', fn ($q) => $q->whereIn('t.department_id', $this->alcance->departamentos($jefe)))
            ->whereExists(fn ($q) => $q->from('teaching_assignments as a')->join('sections as s', 's.id', '=', 'a.section_id')
                ->whereColumn('a.teacher_id', 'u.id')->whereNull('a.ended_at')->where('s.period_id', $periodo))
            ->orderBy('u.last_name')->get(['u.id', 'u.first_name', 'u.last_name', 'd.code as departamento']);

        return $docentes->map(function ($d) use ($periodo) {
            $secciones = DB::table('teaching_assignments')->join('sections', 'sections.id', '=', 'teaching_assignments.section_id')
                ->where('teacher_id', $d->id)->whereNull('ended_at')->where('sections.period_id', $periodo)->pluck('sections.id');
            $d->secciones = $secciones->count();
            ['minutos' => $d->retraso, 'aTiempo' => $d->aTiempo] = $this->puntualidad($d->id, $secciones);
            $d->recursos = DB::table('resources')->join('units', 'units.id', '=', 'resources.unit_id')->whereIn('units.section_id', $secciones)->whereNull('resources.deleted_at')->count();
            ['horas' => $d->revisionHoras, 'sinPublicar' => $d->sinPublicar] = $this->revision($secciones);
            $cerradas = DB::table('grade_items as i')->join('assessments as a', 'a.grade_item_id', '=', 'i.id')->whereIn('i.section_id', $secciones)->where('a.closes_at', '<', now());
            $total = (clone $cerradas)->count();
            $d->publicadas = $total ? round(100 * (clone $cerradas)->where('i.is_published', true)->count() / $total) : null;

            return $d;
        });
    }

    /**
     * Períodos que se pueden consultar: el activo y los cerrados, del más reciente al más antiguo.
     *
     * @return Collection<int, object>
     */
    public function periodos(): Collection
    {
        return DB::table('academic_periods')->whereIn('status', ['active', 'closed'])->orderByDesc('start_date')->get(['id', 'name', 'status']);
    }

    /**
     * Minutos promedio de retraso y % de sesiones abiertas a tiempo (CP-RF33-02).
     *
     * @param  Collection<int, mixed>  $secciones
     * @return array{minutos: ?float, aTiempo: ?float}
     */
    private function puntualidad(int $docente, Collection $secciones): array
    {
        $horarios = DB::table('section_schedules')->whereIn('section_id', $secciones)->get(['section_id', 'weekday', 'start_time'])->groupBy('section_id');
        $retrasos = DB::table('attendance_sessions')->whereIn('section_id', $secciones)->where('opened_by', $docente)->whereNotNull('opened_at')
            ->get(['section_id', 'opened_at'])
            ->map(function (\stdClass $sesion) use ($horarios): ?float {
                $abierta = Carbon::parse($sesion->opened_at)->timezone('America/El_Salvador');
                $inicios = $horarios->get($sesion->section_id, collect())->where('weekday', $abierta->dayOfWeekIso)
                    ->map(fn (\stdClass $bloque) => $abierta->copy()->setTimeFromTimeString($bloque->start_time));
                if ($inicios->isEmpty()) {
                    return null;
                }
                $inicio = $inicios->sortBy(fn (Carbon $i) => abs($i->diffInSeconds($abierta, false)))->first();

                return max(0.0, $inicio->diffInSeconds($abierta, false) / 60);
            })->filter(fn (?float $minutos) => $minutos !== null);

        return $retrasos->isEmpty() ? ['minutos' => null, 'aTiempo' => null] : [
            'minutos' => round((float) $retrasos->avg(), 1),
            'aTiempo' => round(100 * $retrasos->filter(fn (float $m) => $m <= self::TOLERANCIA_MINUTOS)->count() / $retrasos->count(), 1),
        ];
    }

    /**
     * Horas promedio desde la entrega hasta la publicación de su nota, y entregas aún sin nota publicada (CP-RF33-03).
     * Cuenta el último intento de cada estudiante o equipo; en un equipo, la primera publicación entre sus integrantes.
     * Una entrega posterior a la publicación (con prórroga, por ejemplo) cuenta 0.
     *
     * @param  Collection<int, mixed>  $secciones
     * @return array{horas: ?float, sinPublicar: int}
     */
    private function revision(Collection $secciones): array
    {
        $entregas = DB::table('submissions as s')->join('assessments as a', 'a.id', '=', 's.assessment_id')
            ->whereIn('s.section_id', $secciones)->where('s.status', 'submitted')->whereNotNull('a.grade_item_id')
            ->orderBy('s.attempt_number')
            ->get(['s.assessment_id', 's.enrollment_id', 's.team_id', 's.submitted_at', 'a.grade_item_id'])
            ->keyBy(fn (\stdClass $e) => $e->assessment_id.'-'.($e->team_id ? 't'.$e->team_id : 'm'.$e->enrollment_id));
        $publicadas = DB::table('grades')->whereIn('grade_item_id', $entregas->pluck('grade_item_id')->unique())->whereNotNull('published_at')
            ->get(['grade_item_id', 'enrollment_id', 'published_at'])->keyBy(fn (\stdClass $g) => $g->grade_item_id.'-'.$g->enrollment_id);
        $integrantes = DB::table('team_members')->whereIn('team_id', $entregas->pluck('team_id')->filter()->unique())->get(['team_id', 'enrollment_id'])->groupBy('team_id');

        $horas = [];
        $sinPublicar = 0;
        foreach ($entregas as $e) {
            $matriculas = $e->team_id ? $integrantes->get($e->team_id, collect())->pluck('enrollment_id') : collect([$e->enrollment_id]);
            $publicada = $matriculas->map(fn ($m) => $publicadas->get($e->grade_item_id.'-'.$m)?->published_at)->filter()->sort()->first();
            if ($publicada === null) {
                $sinPublicar++;

                continue;
            }
            $horas[] = max(0.0, Carbon::parse($e->submitted_at)->diffInSeconds(Carbon::parse($publicada), false) / 3600);
        }

        return ['horas' => $horas === [] ? null : round(array_sum($horas) / count($horas), 1), 'sinPublicar' => $sinPublicar];
    }
}
