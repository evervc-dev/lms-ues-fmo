<?php

namespace App\Modules\Evaluaciones\Jobs;

use App\Modules\Evaluaciones\Events\RevisionCuestionarioPublicada;
use App\Modules\Evaluaciones\Services\CuestionarioService;
use App\Support\DbContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/**
 * Avisa a los estudiantes cuando la corrección de un cuestionario queda visible: su fin efectivo (cierre o último
 * intento con tiempo extra) más la demora (ADR-040, ADR-042). Idempotente: marca quiz_settings.review_published al
 * avisar y solo toma los que aún no lo tienen.
 */
class AvisarRevisionesDisponibles implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $limite = now()->subMinutes(CuestionarioService::demoraRevision());
        $cuestionarios = DB::table('assessments')->join('quiz_settings', 'quiz_settings.assessment_id', '=', 'assessments.id')
            ->where('assessments.assessment_type', 'quiz')->where('quiz_settings.review_published', false)->where('assessments.closes_at', '<=', $limite)
            ->get(['assessments.id', 'assessments.closes_at']);

        foreach ($cuestionarios as $cuestionario) {
            if (! CuestionarioService::revisionVisible($cuestionario)) {
                continue;
            }
            $id = $cuestionario->id;
            $marcado = DbContext::system(fn () => DB::table('quiz_settings')->where('assessment_id', $id)->where('review_published', false)
                ->update(['review_published' => true]), 'quiz_review.available');
            if ($marcado > 0) {
                RevisionCuestionarioPublicada::dispatch((int) $id);
            }
        }
    }
}
