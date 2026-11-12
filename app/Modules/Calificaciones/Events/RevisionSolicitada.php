<?php

namespace App\Modules\Calificaciones\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un estudiante pidió revisión de una nota (RF35).
 */
class RevisionSolicitada
{
    use Dispatchable;

    public function __construct(public string $solicitud) {}
}
