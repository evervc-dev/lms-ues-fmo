<?php

namespace App\Modules\Calificaciones\Jobs;

use App\Modules\Calificaciones\Services\RiesgoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Recalcula el riesgo académico (`grade_risk`) de una sección (RF17). Se encola al publicar las notas de una
 * actividad, al calificar o revisar una nota ya publicada y al corregir una nota. Único por sección hasta que empieza
 * a correr: varias publicaciones seguidas se juntan en una evaluación. Como el candado único se suelta al empezar,
 * WithoutOverlapping impide que dos evaluaciones de la misma sección corran a la vez: la segunda vuelve a la cola a
 * los 10 s. Idempotente.
 */
class EvaluarRiesgoAcademico implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** Segundos tras los que se suelta el candado único si el job no llegó a correr. */
    public int $uniqueFor = 600;

    /**
     * Intentos: Horizon da 1 por omisión y cada vuelta a la cola por WithoutOverlapping cuenta como intento. Con
     * `maxExceptions` = 1, un error real no se reintenta.
     */
    public int $tries = 5;

    public int $maxExceptions = 1;

    public function __construct(public int $seccionId)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return (string) $this->seccionId;
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->seccionId))->releaseAfter(10)];
    }

    public function handle(RiesgoService $riesgo): void
    {
        $riesgo->evaluarSeccion($this->seccionId);
    }
}
