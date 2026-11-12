<?php

namespace App\Modules\Identidad\Notifications;

use App\Modules\Comunicacion\Notifications\Aviso;

/**
 * Soporte aprobó el cambio de teléfono (RF27): el anterior quedó revocado y puede registrar el nuevo.
 * Es un Aviso del evento `device_request.resolved`: bandeja, Reverb y correo, en la cola `notificaciones`.
 */
class DispositivoCambioAprobado extends Aviso
{
    public function __construct(int $solicitudId, ?string $url = null)
    {
        parent::__construct(
            'device_request.resolved',
            __('Cambio de teléfono aprobado'),
            __('Soporte aprobó su solicitud. Su teléfono anterior quedó revocado: entre a su perfil, en Dispositivo de asistencia, y registre el nuevo.'),
            $url,
            'device_request.resolved:'.$solicitudId,
        );
    }
}
