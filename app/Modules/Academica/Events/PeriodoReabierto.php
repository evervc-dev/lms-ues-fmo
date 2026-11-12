<?php

namespace App\Modules\Academica\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * El Administrador reabrió un período cerrado (RF06), con su motivo. `$cambio` identifica esta reapertura para que
 * el aviso no se duplique si el evento se procesa dos veces, pero sí se repita en una reapertura posterior.
 */
class PeriodoReabierto
{
    use Dispatchable;

    public function __construct(public int $periodoId, public string $motivo, public string $cambio) {}
}
