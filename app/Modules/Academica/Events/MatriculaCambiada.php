<?php

namespace App\Modules\Academica\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Cambió la matrícula de un estudiante (RF09, RF29): se inscribió, se retiró, cambió de grupo o se le rechazó el
 * cambio. `$cambio` es inscrita, retirada, trasladada o cambio_rechazado; `$marca` identifica este cambio para que
 * el aviso no se duplique si el evento se procesa dos veces.
 */
class MatriculaCambiada
{
    use Dispatchable;

    public function __construct(public int $matriculaId, public string $cambio, public string $marca, public ?string $motivo = null) {}
}
