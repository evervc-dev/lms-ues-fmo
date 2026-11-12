<?php

namespace App\Modules\Asistencia\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se aprobó la cancelación de un día de clases o un cambio de modalidad (RF32), de una sección, de una carrera
 * o de toda la facultad.
 */
class ClaseCancelada
{
    use Dispatchable;

    public function __construct(public int $cancelacionId) {}
}
