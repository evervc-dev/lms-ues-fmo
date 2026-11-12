<?php

namespace App\Modules\Asistencia\Jobs;

use App\Models\User;
use App\Modules\Asistencia\Services\CalendarGateway;
use App\Support\DbContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Crea en Google Calendar el evento de una clase virtual recién programada (RF21, CP-RF21-01), con su enlace de Meet,
 * e invita a los matriculados. Va en la cola «google» con reintentos y retroceso. Idempotente: si la clase ya tiene
 * evento, no hace nada. Si el docente escribió su propio enlace de Meet, se conserva.
 */
class CrearEventoDeClase implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public int $claseId, public int $docenteId)
    {
        $this->onQueue('google');
    }

    public function handle(CalendarGateway $calendario): void
    {
        $clase = DB::table('video_sessions')->where('id', $this->claseId)->first();
        $docente = User::query()->find($this->docenteId);
        if ($clase === null || $clase->google_event_id !== null || $docente === null) {
            return;
        }

        $invitados = DB::table('enrollments as e')->join('users as u', 'u.id', '=', 'e.student_id')
            ->where('e.section_id', $clase->section_id)->where('e.status', 'active')
            ->orderBy('u.institutional_email')->pluck('u.institutional_email')->map(fn ($c) => (string) $c)->values()->all();
        $evento = $calendario->crearClase($docente, (string) $clase->topic, Carbon::parse($clase->starts_at), (int) $clase->duration_minutes, $invitados);
        if ($evento === null) {
            return;
        }

        DbContext::system(fn () => DB::table('video_sessions')->where('id', $clase->id)->whereNull('google_event_id')->update([
            'google_event_id' => $evento['evento'], 'meet_url' => $clase->meet_url ?? $evento['meet'], 'updated_at' => now(),
        ]), 'video_session.calendar_created');
    }
}
