<?php

namespace App\Modules\Evaluaciones\Jobs;

use App\Modules\Evaluaciones\Services\CuestionarioService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Cierra y corrige los intentos de cuestionario cuyo tiempo venció sin envío (RF16, ADR-042). Idempotente:
 * solo toma intentos en curso y cada uno se corrige bajo bloqueo una sola vez.
 */
class CerrarIntentosVencidos implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(CuestionarioService $cuestionarios): void
    {
        $cuestionarios->cerrarVencidos();
    }
}
