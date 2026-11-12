<?php

namespace App\Modules\Evaluaciones\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * El estudiante entregó una tarea (RF15). Lo escucha Contenidos para completar los recursos cuya regla es
 * «entrega de una tarea» (RF18, ADR-067).
 */
class EntregaRealizada
{
    use Dispatchable;

    public function __construct(public int $entregaId) {}
}
