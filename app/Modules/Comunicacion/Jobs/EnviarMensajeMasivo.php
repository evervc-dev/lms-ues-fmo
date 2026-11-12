<?php

namespace App\Modules\Comunicacion\Jobs;

use App\Models\User;
use App\Modules\Comunicacion\Notifications\MensajeDocente;
use App\Modules\Comunicacion\Services\NotificacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/**
 * Entrega un mensaje del docente a sus destinatarios (RF31), en la cola `notificaciones`: «el envío va en cola».
 *
 * Es idempotente: solo toma a quienes aún no tienen `delivered_at`, y cada aviso lleva una clave de
 * idempotencia por mensaje, así que un reintento no repite ni la bandeja ni el correo. Un solo trabajo por
 * mensaje a la vez.
 */
class EnviarMensajeMasivo implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public int $mensajeId)
    {
        $this->onQueue('notificaciones');
    }

    public function uniqueId(): string
    {
        return 'mensaje-masivo:'.$this->mensajeId;
    }

    public function handle(NotificacionService $notificaciones): void
    {
        $mensaje = DB::table('mass_messages as m')->join('sections as s', 's.id', '=', 'm.section_id')
            ->join('subjects as a', 'a.id', '=', 's.subject_id')->where('m.id', $this->mensajeId)
            ->first(['m.id', 'm.subject', 'm.body', 'm.sent_by', 'a.name as materia', 's.ulid as seccion']);
        $docente = $mensaje ? User::query()->find($mensaje->sent_by) : null;
        if ($mensaje === null || $docente === null) {
            return;
        }

        $aviso = new MensajeDocente(
            $mensaje->subject, $mensaje->body, route('mis-notas'), 'mass_message:'.$mensaje->id,
            $docente->fullName(), $docente->institutional_email, $mensaje->materia,
        );

        DB::table('mass_message_recipients')->where('mass_message_id', $mensaje->id)->whereNull('delivered_at')
            ->pluck('user_id')->chunk(100)->each(function ($grupo) use ($notificaciones, $aviso, $mensaje): void {
                $notificaciones->enviar($grupo->all(), $aviso);

                DB::table('mass_message_recipients')->where('mass_message_id', $mensaje->id)
                    ->whereIn('user_id', $grupo->all())->update(['delivered_at' => now()]);
            });
    }
}
