<?php

namespace App\Modules\Academica\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Cambió el horario o el aula de una sección (RF08): se agregó o se quitó un bloque, o un bloque cambió de aula.
 * `$cambio` identifica este cambio para que el aviso no se duplique si el evento se procesa dos veces.
 */
class HorarioCambiado
{
    use Dispatchable;

    public function __construct(public int $seccionId, public string $detalle, public string $cambio) {}
}
