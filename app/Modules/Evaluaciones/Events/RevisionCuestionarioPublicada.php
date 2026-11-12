<?php

namespace App\Modules\Evaluaciones\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * El docente publicó la revisión de un cuestionario (RF16).
 */
class RevisionCuestionarioPublicada
{
    use Dispatchable;

    public function __construct(public int $cuestionarioId) {}
}
