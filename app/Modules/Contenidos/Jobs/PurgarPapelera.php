<?php

namespace App\Modules\Contenidos\Jobs;

use App\Modules\Contenidos\Services\ContenidoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Borra definitivamente las unidades y los recursos que llevan más de 30 días en la papelera, con sus
 * archivos del disco (RF11). Idempotente: solo toca lo que ya pasó el plazo, así que repetirlo no cambia nada.
 */
class PurgarPapelera implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(ContenidoService $contenidos): void
    {
        $contenidos->purgarPapelera();
    }
}
