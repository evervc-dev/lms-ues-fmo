<?php

namespace App\Modules\Calificaciones\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * El Coordinador corrigió una nota publicada con el período cerrado (RF17): lleva el valor anterior, el nuevo y
 * el motivo para avisar al estudiante.
 */
class NotaCorregida
{
    use Dispatchable;

    public function __construct(public int $notaId, public float $anterior, public float $nueva, public string $motivo) {}
}
