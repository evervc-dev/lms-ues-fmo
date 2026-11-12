<?php

namespace App\Modules\Identidad\Notifications;

use App\Modules\Comunicacion\Notifications\Aviso;

/**
 * Un estudiante pidió cambiar su teléfono de asistencia (RF27): aviso para quienes atienden las solicitudes
 * (`dispositivos.gestionar`), que es la entrada del flujo «Atender un cambio de teléfono»
 * (docs/13 Layout/Layout Soporte Técnico.md: «Aviso o contador del menú»).
 * Es un Aviso del evento `device_request.pending`: bandeja, Reverb y correo, en la cola `notificaciones`.
 */
class DispositivoCambioSolicitado extends Aviso
{
    public function __construct(int $solicitudId, string $estudiante, string $motivo, ?string $url = null)
    {
        parent::__construct(
            'device_request.pending',
            __('Nueva solicitud de cambio de teléfono'),
            __(':estudiante solicitó cambiar su teléfono de asistencia. Motivo: :motivo', ['estudiante' => $estudiante, 'motivo' => $motivo]),
            $url,
            'device_request.pending:'.$solicitudId,
        );
    }
}
