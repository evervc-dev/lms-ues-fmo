<?php

namespace App\Modules\Academica\Jobs;

use App\Models\User;
use App\Modules\Academica\Services\CalendarioPeriodoService;
use App\Modules\Asistencia\Services\CalendarGateway;
use App\Support\DbContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Lleva una fecha del calendario de evaluaciones a Google Calendar (RF06, CP-RF06-04, ADR-068): la crea en el
 * calendario de quien sincronizó —el Administrador las globales, el Jefe las de su departamento y el Coordinador las
 * de su carrera—, o la actualiza si ya tenía evento, e invita a los involucrados **de ese ámbito**. Va en la cola
 * «google» con reintentos. Idempotente: con `external_id` actualiza en vez de crear otro evento.
 */
class SincronizarEventoDePeriodo implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public int $eventoId, public int $organizadorId)
    {
        $this->onQueue('google');
    }

    public function handle(CalendarGateway $calendario): void
    {
        $evento = DB::table('period_calendar_events')->where('id', $this->eventoId)->first();
        $organizador = User::query()->find($this->organizadorId);
        if ($evento === null || $organizador === null) {
            return;
        }
        $referencia = ['entity_type' => CalendarioPeriodoService::ENTIDAD, 'entity_id' => $evento->id, 'provider' => 'google_calendar'];
        $externo = DB::table('google_sync_refs')->where($referencia)->value('external_id');

        try {
            $id = $calendario->sincronizarEvento($organizador, $externo, (string) $evento->title, Carbon::parse($evento->starts_at),
                $evento->ends_at === null ? null : Carbon::parse($evento->ends_at),
                // Solo los del ámbito de la fecha (ADR-068): una fecha del departamento no invita a toda la universidad.
                CalendarioPeriodoService::involucrados((int) $evento->period_id, $evento->department_id === null ? null : (int) $evento->department_id,
                    $evento->career_id === null ? null : (int) $evento->career_id));
        } catch (Throwable $excepcion) {
            $this->guardar($referencia, ['sync_status' => 'failed', 'last_error' => Str::limit($excepcion->getMessage(), 500)]);

            throw $excepcion;
        }

        $this->guardar($referencia, $id === null
            ? ['sync_status' => 'failed', 'last_error' => __('Quien sincronizó ya no tiene Google Calendar conectado.')]
            : ['sync_status' => 'synced', 'external_id' => $id, 'last_error' => null]);
    }

    /**
     * @param  array<string, mixed>  $referencia
     * @param  array<string, mixed>  $valores
     */
    private function guardar(array $referencia, array $valores): void
    {
        DbContext::system(fn () => DB::table('google_sync_refs')->updateOrInsert($referencia, $valores + ['updated_at' => now(), 'created_at' => now()]),
            'period_calendar.synced');
    }
}
