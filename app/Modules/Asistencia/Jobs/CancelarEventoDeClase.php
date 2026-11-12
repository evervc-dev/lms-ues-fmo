<?php

namespace App\Modules\Asistencia\Jobs;

use App\Models\User;
use App\Modules\Asistencia\Services\CalendarGateway;
use App\Support\DbContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/**
 * Borra de Google Calendar el evento de una clase virtual cancelada (RF32 + RF21, ADR-018), y Google avisa a los
 * invitados. El evento está en el calendario del docente que programó la clase, que no se guarda: se prueba con los
 * docentes vigentes de la sección, primero el responsable. Va en la cola «google» con reintentos. Idempotente: sin
 * evento no hace nada, y al borrarlo se quita `google_event_id`.
 */
class CancelarEventoDeClase implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public int $claseId)
    {
        $this->onQueue('google');
    }

    public function handle(CalendarGateway $calendario): void
    {
        $clase = DB::table('video_sessions')->where('id', $this->claseId)->first(['id', 'section_id', 'google_event_id']);
        if ($clase === null || $clase->google_event_id === null) {
            return;
        }

        $docentes = DB::table('teaching_assignments')->where('section_id', $clase->section_id)->whereNull('ended_at')
            ->orderByDesc('is_responsible')->orderBy('id')->pluck('teacher_id');

        foreach (User::query()->whereIn('id', $docentes)->get()->sortBy(fn (User $u) => $docentes->search($u->id)) as $docente) {
            if ($calendario->borrarEvento($docente, (string) $clase->google_event_id) === true) {
                DbContext::system(fn () => DB::table('video_sessions')->where('id', $clase->id)->update(['google_event_id' => null, 'updated_at' => now()]),
                    'video_session.calendar_cancelled');

                return;
            }
        }
    }
}
