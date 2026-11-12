<?php

namespace App\Modules\Asistencia\Listeners;

use App\Modules\Asistencia\Events\ClaseCancelada;
use App\Modules\Asistencia\Jobs\EvaluarAlertasAsistencia;
use App\Modules\Asistencia\Services\AsistenciaService;
use Illuminate\Support\Facades\DB;

/**
 * Un día cancelado deja de contar en el porcentaje (RF32), así que las alertas de las secciones afectadas se
 * recalculan (RF22): una ausencia de ese día puede cerrar una alerta.
 */
class ReevaluarAlertasPorCancelacion
{
    public function handle(ClaseCancelada $evento): void
    {
        $cancelacion = DB::table('class_cancellations')->where('id', $evento->cancelacionId)->first(['scope', 'section_id', 'career_id', 'kind']);
        if ($cancelacion === null || $cancelacion->kind !== 'cancellation') {
            return;
        }

        foreach (AsistenciaService::seccionesDeCancelacion($cancelacion) as $seccion) {
            EvaluarAlertasAsistencia::dispatch($seccion);
        }
    }
}
