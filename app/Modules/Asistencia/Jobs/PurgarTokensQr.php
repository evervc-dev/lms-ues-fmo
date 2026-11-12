<?php

namespace App\Modules\Asistencia\Jobs;

use App\Modules\Asistencia\Services\AsistenciaQrService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Borra los tokens del QR vencidos hace más de `qr_token_retention_days` (RF20, ADR-028). Idempotente: solo
 * borra lo que ya venció, así que repetirlo no cambia nada.
 */
class PurgarTokensQr implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(AsistenciaQrService $qr): void
    {
        $qr->purgarTokens();
    }
}
