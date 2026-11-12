<?php

namespace App\Modules\Contenidos\Listeners;

use App\Modules\Calificaciones\Events\NotasPublicadas;
use App\Modules\Contenidos\Services\ProgresoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

/**
 * Al publicar las notas de una actividad, completa los recursos cuya regla es la nota mínima de ese cuestionario
 * (RF18, ADR-067). Es idempotente: `resource_progress` no se duplica.
 */
class CompletarPorNotasPublicadas implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private ProgresoService $progreso) {}

    public function handle(NotasPublicadas $evento): void
    {
        DB::table('assessments')->where('grade_item_id', $evento->itemId)->pluck('id')
            ->each(fn ($evaluacion) => $this->progreso->evaluarPorEvaluacion((int) $evaluacion));
    }
}
