<?php

namespace App\Modules\Comunicacion\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Alguien respondió en un hilo del foro (RF30): a un mensaje o al hilo.
 */
class RespuestaPublicada
{
    use Dispatchable;

    public function __construct(public int $mensajeId) {}
}
