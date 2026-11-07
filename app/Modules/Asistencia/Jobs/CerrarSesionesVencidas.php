<?php

namespace App\Modules\Asistencia\Jobs;

use App\Modules\Asistencia\Services\AsistenciaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Cierra las sesiones de asistencia cuya vigencia ya pasó (RF19, ADR-028): quienes no marcaron quedan ausentes,
 * igual que si el docente la cerrara. Idempotente: solo toma sesiones todavía abiertas.
 */
class CerrarSesionesVencidas implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(AsistenciaService $asistencia): void
    {
        $asistencia->cerrarVencidas();
    }
}
