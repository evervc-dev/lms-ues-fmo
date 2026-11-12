<?php

namespace App\Modules\Calificaciones\Services;

use App\Modules\Calificaciones\Events\RiesgoAcademicoDetectado;
use App\Support\DbContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Riesgo académico (RF17, Flujo Cálculo de nota final · «en vivo»): el estudiante queda «en riesgo» cuando su
 * nota proyectada con las notas publicadas baja de la mínima de aprobación. La alerta (`academic_alerts`,
 * `grade_risk`, sin cómputo) la lee el panel del docente; al abrirse avisa al estudiante.
 */
class RiesgoService
{
    public function __construct(private CalificacionService $calificaciones) {}

    /**
     * Abre y cierra las alertas `grade_risk` de la sección. La llama el job EvaluarRiesgoAcademico; es idempotente:
     * con las mismas notas no cambia nada.
     *
     * - Matrícula activa: sin ninguna actividad publicada no se evalúa (no tiene nota proyectada). Si la proyectada
     *   baja de la mínima y no tiene una alerta abierta, se abre una y avisa al estudiante (`notified_at`); si sube a
     *   la mínima o más, la abierta se cierra.
     * - Matrícula no activa (retirada o trasladada): su alerta abierta se cierra.
     * - Período cerrado: se cierran todas las alertas abiertas de la sección y no se abre ninguna; el resultado ya
     *   está congelado en `enrollment_results`.
     *
     * Todo se lee y se escribe en la misma transacción, y la inserción tolera que otra evaluación haya abierto la
     * alerta a la vez (índice único parcial `academic_alerts_single_open_unique`): solo avisa si de verdad insertó.
     *
     * @return int Alertas abiertas en esta evaluación.
     */
    public function evaluarSeccion(int $seccionId): int
    {
        if ($this->calificaciones->periodoCerrado($seccionId)) {
            DbContext::system(fn () => $this->abiertas($seccionId)->update(['closed_at' => now()]), 'academic_alert.evaluated');

            return 0;
        }

        $computos = $this->calificaciones->estructura($seccionId);
        $minima = CalificacionService::notaMinima();

        $nuevas = DbContext::system(function () use ($seccionId, $computos, $minima) {
            $activas = DB::table('enrollments')->where('section_id', $seccionId)->where('status', 'active')->orderBy('id')->pluck('id');
            $this->abiertas($seccionId)->whereNotIn('enrollment_id', $activas)->update(['closed_at' => now()]);

            $notas = DB::table('grades')->where('section_id', $seccionId)->where('status', 'published')
                ->get(['enrollment_id', 'grade_item_id', 'score'])->groupBy('enrollment_id');
            $abiertas = $this->abiertas($seccionId)->get(['academic_alerts.id', 'academic_alerts.enrollment_id'])->keyBy('enrollment_id');
            // Sin los cómputos heredados de un cambio de grupo (ADR-062), quien se trasladó se marcaría «en
            // riesgo» por una nota que sí tiene, solo que en el grupo anterior.
            $cadenas = $this->calificaciones->cadenasDeSeccion($activas);
            $nuevas = [];

            foreach ($activas as $matricula) {
                $proyectada = $this->notaProyectada($computos, ($notas[$matricula] ?? collect())->keyBy('grade_item_id'),
                    $cadenas->get((int) $matricula));
                if ($proyectada === null) {
                    continue;
                }
                $abierta = $abiertas[$matricula] ?? null;

                if ($proyectada < $minima && $abierta === null) {
                    $insertadas = DB::table('academic_alerts')->insertOrIgnore(['enrollment_id' => $matricula, 'alert_type' => 'grade_risk',
                        'grading_component_id' => null, 'opened_at' => now(), 'notified_at' => now()]);
                    if ($insertadas === 1) {
                        $id = DB::table('academic_alerts')->where('enrollment_id', $matricula)->where('alert_type', 'grade_risk')
                            ->whereNull('grading_component_id')->whereNull('closed_at')->value('id');
                        $nuevas[] = [(int) $id, $proyectada];
                    }
                }
                if ($proyectada >= $minima && $abierta !== null) {
                    DB::table('academic_alerts')->where('id', $abierta->id)->update(['closed_at' => now()]);
                }
            }

            return $nuevas;
        }, 'academic_alert.evaluated');

        foreach ($nuevas as [$id, $proyectada]) {
            RiesgoAcademicoDetectado::dispatch($id, $proyectada, $minima);
        }

        return count($nuevas);
    }

    /**
     * Nota proyectada con las actividades publicadas, reescalada al peso evaluado: dentro de cada cómputo,
     * Σ(nota × peso) / Σ(peso de las actividades publicadas); entre cómputos, Σ(nota del cómputo × peso) / Σ(peso de
     * los cómputos con al menos una actividad publicada). Una actividad publicada sin nota cuenta 0. Redondeo
     * institucional. Null si no hay ninguna actividad publicada.
     *
     * Un cómputo heredado de un cambio de grupo ([[ADR-062]]) entra con su nota y su peso completo, igual que
     * en la nota final: de otro modo el traslado dispararía una alerta falsa.
     *
     * @param  Collection<int, object>  $computos  de CalificacionService::estructura()
     * @param  Collection<int|string, object>  $notas  publicadas, por grade_item_id
     * @param  Collection<int, object>|null  $cadena  heredadas por number, de CalificacionService::notasDeCadena()
     */
    public function notaProyectada(Collection $computos, Collection $notas, ?Collection $cadena = null): ?float
    {
        $suma = 0.0;
        $pesoEvaluado = 0.0;

        foreach ($computos as $computo) {
            $heredada = $cadena?->get((int) $computo->number);
            if ($heredada !== null && ! CalificacionService::tieneNotasDelComputo($computo, $notas)) {
                $suma += (float) $heredada->nota * (float) $computo->weight_percent;
                $pesoEvaluado += (float) $computo->weight_percent;

                continue;
            }

            $publicadas = collect($computo->items)->filter(fn (object $i) => (bool) $i->is_published);
            $pesoActividades = $publicadas->sum(fn (object $i) => (float) $i->weight_percent);
            if ($publicadas->isEmpty() || $pesoActividades <= 0) {
                continue;
            }
            $notaComputo = $publicadas->sum(fn (object $i) => (float) $i->weight_percent * (float) ($notas[$i->id]->score ?? 0)) / $pesoActividades;
            $suma += $notaComputo * (float) $computo->weight_percent;
            $pesoEvaluado += (float) $computo->weight_percent;
        }

        return $pesoEvaluado > 0 ? CalificacionService::redondear($suma / $pesoEvaluado) : null;
    }

    /**
     * Alertas `grade_risk` abiertas de las matrículas de la sección, en cualquier estado.
     */
    private function abiertas(int $seccionId): Builder
    {
        return DB::table('academic_alerts')->whereIn('enrollment_id', DB::table('enrollments')->where('section_id', $seccionId)->select('id'))
            ->where('alert_type', 'grade_risk')->whereNull('grading_component_id')->whereNull('closed_at');
    }
}
