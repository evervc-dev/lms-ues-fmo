<?php

namespace App\Modules\Identidad\Notifications;

use App\Modules\Comunicacion\Notifications\Aviso;

/**
 * Una solicitud de cambio de teléfono quedó resuelta (RF27): aviso para el **resto** del equipo que atiende
 * la bandeja (`dispositivos.gestionar`), para que no vuelva a trabajarla. A quien la resolvió no se le avisa,
 * y al estudiante le llega su propio aviso (DispositivoCambioAprobado o DispositivoCambioRechazado).
 * Es un Aviso del evento `device_request.resolved`: bandeja, Reverb y correo, en la cola `notificaciones`.
 */
class DispositivoCambioResuelto extends Aviso
{
    public function __construct(int $solicitudId, string $estudiante, string $quien, bool $aprobada, ?string $url = null)
    {
        parent::__construct(
            'device_request.resolved',
            __('Solicitud de cambio de teléfono resuelta'),
            $aprobada
                ? __(':quien aprobó la solicitud de :estudiante: su teléfono anterior quedó revocado.', ['quien' => $quien, 'estudiante' => $estudiante])
                : __(':quien rechazó la solicitud de :estudiante.', ['quien' => $quien, 'estudiante' => $estudiante]),
            $url,
            'device_request.resolved:soporte:'.$solicitudId,
        );
    }
}
