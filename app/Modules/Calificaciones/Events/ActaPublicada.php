<?php

namespace App\Modules\Calificaciones\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * El docente publicó el acta de una sección: queda por validar por el Coordinador (RF34).
 */
class ActaPublicada
{
    use Dispatchable;

    public function __construct(public int $seccionId) {}
}
