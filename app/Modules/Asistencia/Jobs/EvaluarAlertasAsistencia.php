<?php

namespace App\Modules\Asistencia\Jobs;

use App\Modules\Asistencia\Services\AsistenciaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Recalcula las alertas de inasistencia de una sección (RF22). Se encola al cerrar una sesión, al corregir un
 * registro, al aceptar una justificación y al aprobar una cancelación. Único por sección hasta que empieza a
 * correr: varios cambios seguidos se juntan en una evaluación. Idempotente.
 */
class EvaluarAlertasAsistencia implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(public int $seccionId)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return (string) $this->seccionId;
    }

    public function handle(AsistenciaService $asistencia): void
    {
        $asistencia->evaluarAlertas($this->seccionId);
    }
}
