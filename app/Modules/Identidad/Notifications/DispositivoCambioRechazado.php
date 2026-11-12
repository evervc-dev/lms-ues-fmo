<?php

namespace App\Modules\Identidad\Notifications;

use App\Modules\Comunicacion\Notifications\Aviso;

/**
 * Soporte rechazó el cambio de teléfono (RF27): el aviso incluye el motivo. Es un Aviso del evento
 * `device_request.resolved`: bandeja, Reverb y correo, en la cola `notificaciones`.
 */
class DispositivoCambioRechazado extends Aviso
{
    public function __construct(int $solicitudId, public string $motivo, ?string $url = null)
    {
        parent::__construct(
            'device_request.resolved',
            __('Cambio de teléfono rechazado'),
            __('Soporte rechazó su solicitud. Motivo: :motivo', ['motivo' => $motivo]),
            $url,
            'device_request.resolved:'.$solicitudId,
        );
    }
}
