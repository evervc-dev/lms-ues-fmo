<?php

namespace App\Modules\Asistencia\Listeners;

use App\Modules\Asistencia\Events\ClaseCancelada;
use App\Modules\Asistencia\Jobs\CancelarEventoDeClase;
use App\Modules\Asistencia\Services\AsistenciaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Un día de clases cancelado (RF32) borra de Google Calendar los eventos de las clases virtuales de ese día en las
 * secciones afectadas (RF21, ADR-018), para que los estudiantes no se queden con un enlace a una clase que no habrá.
 * Un cambio de modalidad no borra nada: la clase sigue en pie.
 */
class CancelarEventosPorCancelacion
{
    public function handle(ClaseCancelada $evento): void
    {
        $cancelacion = DB::table('class_cancellations')->where('id', $evento->cancelacionId)->first(['scope', 'section_id', 'career_id', 'kind', 'date']);
        if ($cancelacion === null || $cancelacion->kind !== 'cancellation') {
            return;
        }

        $dia = Carbon::parse($cancelacion->date);
        DB::table('video_sessions')->whereIn('section_id', AsistenciaService::seccionesDeCancelacion($cancelacion))
            ->whereNotNull('google_event_id')->whereBetween('starts_at', [$dia->copy()->startOfDay(), $dia->copy()->endOfDay()])
            ->pluck('id')->each(fn ($id) => CancelarEventoDeClase::dispatch((int) $id)->afterCommit());
    }
}
