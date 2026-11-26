<?php

namespace App\Modules\Identidad\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Terminó de procesarse un lote de carga masiva de usuarios (RF04): Comunicación avisa a quien lo subió.
 */
class ImportacionTerminada
{
    use Dispatchable;

    public function __construct(public int $loteId) {}
}
