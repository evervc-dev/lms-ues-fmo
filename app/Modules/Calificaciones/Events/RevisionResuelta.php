<?php

namespace App\Modules\Calificaciones\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * El docente resolvió una solicitud de revisión (RF35).
 */
class RevisionResuelta
{
    use Dispatchable;

    public function __construct(public string $solicitud) {}
}
