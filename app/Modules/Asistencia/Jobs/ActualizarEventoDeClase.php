<?php

namespace App\Modules\Asistencia\Jobs;

use App\Models\User;
use App\Modules\Asistencia\Services\CalendarGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lleva a Google Calendar el tema, el inicio y la duración de una clase virtual editada (RF21, CP-RF21-10), y Google
 * avisa a los invitados. Como al cancelar, se prueba con los docentes vigentes de la sección, primero el responsable.
 * Va en la cola «google» con reintentos. Idempotente: lee la clase al correr y deja el evento igual a ella.
 */
class ActualizarEventoDeClase implements ShouldQueue
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
        $clase = DB::table('video_sessions')->where('id', $this->claseId)->first(['id', 'section_id', 'topic', 'starts_at', 'duration_minutes', 'google_event_id']);
        if ($clase === null || $clase->google_event_id === null) {
            return;
        }

        $docentes = DB::table('teaching_assignments')->where('section_id', $clase->section_id)->whereNull('ended_at')
            ->orderByDesc('is_responsible')->orderBy('id')->pluck('teacher_id');

        foreach (User::query()->whereIn('id', $docentes)->get()->sortBy(fn (User $u) => $docentes->search($u->id)) as $docente) {
            if ($calendario->actualizarClase($docente, (string) $clase->google_event_id, (string) $clase->topic, Carbon::parse($clase->starts_at), (int) $clase->duration_minutes) === true) {
                return;
            }
        }
    }
}
