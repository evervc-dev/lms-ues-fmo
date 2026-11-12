<?php

namespace App\Modules\Contenidos\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Registra en resource_access_logs lo que el estudiante hace con un recurso (RF13), fuera de la petición para no
 * afectar el tiempo de respuesta. La tabla no se audita (volumen alto, particionada por mes en PostgreSQL).
 *
 * - `open`: una apertura, salvo que ya haya otra del mismo estudiante y recurso en los últimos 5 minutos (volver a
 *   la página o recargarla es la misma visita).
 * - `download`: cada descarga.
 * - `time`: suma segundos con la pestaña visible a la última apertura, en un solo UPDATE; un reintento después de
 *   un fallo no los duplica porque el UPDATE no llegó a confirmarse.
 */
class RegistrarAccesoRecurso implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** Minutos en que volver al mismo recurso cuenta como la misma apertura (decidido por el usuario el 01/11/2026). */
    public const VENTANA_MINUTOS = 5;

    public function __construct(
        public int $recursoId,
        public int $matriculaId,
        public string $evento,
        public string $cuando,
        public int $segundos = 0,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $cuando = Carbon::parse($this->cuando);
        $ultimaApertura = fn () => DB::table('resource_access_logs')->where('resource_id', $this->recursoId)->where('enrollment_id', $this->matriculaId)
            ->where('event_type', 'open')->orderByDesc('occurred_at')->orderByDesc('id');

        match ($this->evento) {
            'open' => $ultimaApertura()->where('occurred_at', '>=', $cuando->copy()->subMinutes(self::VENTANA_MINUTOS))->exists()
                || $this->insertar('open', $cuando),
            'download' => $this->insertar('download', $cuando),
            'time' => $this->sumarTiempo($ultimaApertura()->first(['id', 'occurred_at']), $cuando),
            default => null,
        };
    }

    private function insertar(string $evento, Carbon $cuando, int $segundos = 0): bool
    {
        return DB::table('resource_access_logs')->insert(['resource_id' => $this->recursoId, 'enrollment_id' => $this->matriculaId,
            'event_type' => $evento, 'duration_seconds' => $segundos, 'occurred_at' => $cuando]);
    }

    /**
     * Sin apertura previa (se purgó o el job llegó antes), el tiempo abre una apertura propia para no perderse.
     */
    private function sumarTiempo(?\stdClass $apertura, Carbon $cuando): void
    {
        if ($apertura === null) {
            $this->insertar('open', $cuando, $this->segundos);

            return;
        }
        // occurred_at forma parte de la clave en PostgreSQL (tabla particionada): se filtra por los dos.
        DB::table('resource_access_logs')->where('id', $apertura->id)->where('occurred_at', $apertura->occurred_at)->increment('duration_seconds', $this->segundos);
    }
}
