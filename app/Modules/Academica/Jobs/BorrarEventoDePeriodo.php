<?php

namespace App\Modules\Academica\Jobs;

use App\Models\User;
use App\Modules\Asistencia\Services\CalendarGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Spatie\Permission\Models\Permission;

/**
 * Borra de Google Calendar el evento de una fecha eliminada del calendario de evaluaciones (RF06), y Google avisa a
 * los invitados. `google_sync_refs` no guarda quién lo creó: se prueba primero con quien eliminó la fecha y después
 * con los demás que gestionan períodos. Va en la cola «google» con reintentos; si nadie lo encuentra, no hace nada.
 */
class BorrarEventoDePeriodo implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public string $externalId, public int $actorId)
    {
        $this->onQueue('google');
    }

    public function handle(CalendarGateway $calendario): void
    {
        $gestores = User::permission(Permission::findByName('periodos.gestionar'))->whereNotNull('google_calendar_token')
            ->get()->sortBy(fn (User $u) => $u->id === $this->actorId ? 0 : 1);

        foreach ($gestores as $gestor) {
            if ($calendario->borrarEvento($gestor, $this->externalId) === true) {
                return;
            }
        }
    }
}
