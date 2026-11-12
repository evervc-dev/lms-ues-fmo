<?php

namespace App\Modules\Calificaciones\Listeners;

use App\Modules\Calificaciones\Events\NotasPublicadas;
use App\Modules\Calificaciones\Jobs\EvaluarRiesgoAcademico;
use Illuminate\Support\Facades\DB;

/**
 * Al publicar las notas de una actividad, encola la reevaluación del riesgo académico de su sección (RF17).
 */
class ReevaluarRiesgoPorNotas
{
    public function handle(NotasPublicadas $evento): void
    {
        $seccion = DB::table('grade_items')->where('id', $evento->itemId)->value('section_id');
        if ($seccion !== null) {
            EvaluarRiesgoAcademico::dispatch((int) $seccion);
        }
    }
}
