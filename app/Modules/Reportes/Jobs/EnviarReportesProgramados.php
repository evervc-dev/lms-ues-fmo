<?php

namespace App\Modules\Reportes\Jobs;

use App\Modules\Reportes\Services\ReporteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Genera y envía por correo los reportes programados que ya tocan (RF25). Idempotente: cada reporte avanza su
 * próxima fecha al tomarse, así que repetir el job no lo envía dos veces.
 */
class EnviarReportesProgramados implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct()
    {
        $this->onQueue('reportes');
    }

    public function handle(ReporteService $reportes): void
    {
        $reportes->enviarProgramados();
    }
}
