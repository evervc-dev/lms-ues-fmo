<?php

namespace App\Modules\Contenidos\Listeners;

use App\Modules\Contenidos\Services\ProgresoService;
use App\Modules\Evaluaciones\Events\IntentoCalificado;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * El intento se calificó con la actividad ya publicada: la nota nació publicada y no habrá un «publicar notas»
 * posterior, así que la regla de nota mínima se evalúa aquí, solo para esa matrícula (RF18, ADR-067).
 */
class CompletarPorIntentoCalificado implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private ProgresoService $progreso) {}

    public function handle(IntentoCalificado $evento): void
    {
        $this->progreso->evaluarPorEvaluacion($evento->evaluacionId, [$evento->matriculaId]);
    }
}
