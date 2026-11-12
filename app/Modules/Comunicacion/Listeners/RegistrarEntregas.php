<?php

namespace App\Modules\Comunicacion\Listeners;

use App\Modules\Comunicacion\Notifications\Aviso;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;

/**
 * Deja en `notification_deliveries` cuándo salió cada aviso por cada canal (RF23, RNF12): es lo que permite
 * medir «marca de tiempo del evento frente a la entrega». La entrega por la bandeja (`database`) la registra
 * NotificacionService al crear la fila; aquí quedan el push (`broadcast`) y el correo (`mail`).
 *
 * Es idempotente: un reintento de la cola no duplica la fila (UNIQUE notification_id + channel).
 */
class RegistrarEntregas
{
    private const CANALES = ['broadcast' => 'broadcast', 'mail' => 'mail'];

    public function enviada(NotificationSent $evento): void
    {
        $this->registrar($evento->notification, $evento->channel, 'sent', null);
    }

    public function fallida(NotificationFailed $evento): void
    {
        $this->registrar($evento->notification, $evento->channel, 'failed', json_encode($evento->data, JSON_UNESCAPED_UNICODE));
    }

    private function registrar(object $aviso, string $canal, string $estado, ?string $error): void
    {
        if (! $aviso instanceof Aviso || ! isset(self::CANALES[$canal]) || ! $aviso->id) {
            return;
        }

        $ahora = now();
        DB::table('notification_deliveries')->upsert(
            [['notification_id' => $aviso->id, 'channel' => self::CANALES[$canal], 'status' => $estado,
                'sent_at' => $estado === 'sent' ? $ahora : null, 'error' => $error, 'created_at' => $ahora, 'updated_at' => $ahora]],
            ['notification_id', 'channel'],
            ['status', 'sent_at', 'error', 'updated_at'],
        );
    }
}
