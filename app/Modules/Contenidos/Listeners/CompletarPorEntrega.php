<?php

namespace App\Modules\Contenidos\Listeners;

use App\Modules\Contenidos\Services\ProgresoService;
use App\Modules\Evaluaciones\Events\EntregaRealizada;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

/**
 * Completa los recursos cuya regla es entregar esa tarea (RF18, ADR-067). En una tarea grupal la entrega es del
 * equipo (RF15), así que se completa para todos sus integrantes.
 */
class CompletarPorEntrega implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private ProgresoService $progreso) {}

    public function handle(EntregaRealizada $evento): void
    {
        $entrega = DB::table('submissions')->where('id', $evento->entregaId)->first(['assessment_id', 'enrollment_id', 'team_id']);
        if ($entrega === null) {
            return;
        }
        $matriculas = $entrega->team_id === null
            ? [(int) $entrega->enrollment_id]
            : DB::table('team_members')->where('team_id', $entrega->team_id)->pluck('enrollment_id')->map(fn ($id) => (int) $id)->all();

        if ($matriculas !== []) {
            $this->progreso->evaluarPorEvaluacion((int) $entrega->assessment_id, $matriculas);
        }
    }
}
