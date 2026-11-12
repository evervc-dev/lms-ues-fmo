<?php

namespace App\Modules\Calificaciones\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * El docente publicó las notas de una actividad (RF17).
 */
class NotasPublicadas
{
    use Dispatchable;

    public function __construct(public int $itemId) {}
}
