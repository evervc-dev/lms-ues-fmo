<?php

namespace App\Modules\Calificaciones\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * El Coordinador devolvió el acta de una sección con observaciones (RF34).
 */
class ActaDevuelta
{
    use Dispatchable;

    public function __construct(public int $seccionId, public string $motivo) {}
}
