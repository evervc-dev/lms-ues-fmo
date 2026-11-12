<?php

namespace App\Modules\Evaluaciones\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * El docente dio o amplió una prórroga a un estudiante (RF15, RF35).
 */
class ProrrogaConcedida
{
    use Dispatchable;

    public function __construct(public int $prorrogaId) {}
}
