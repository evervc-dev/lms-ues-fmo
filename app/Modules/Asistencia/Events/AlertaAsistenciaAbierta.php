<?php

namespace App\Modules\Asistencia\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se abrió una alerta de inasistencia que avisa al estudiante (RF22): preventiva bajo 85 % o crítica bajo 78 %,
 * con el porcentaje del cómputo en ese momento.
 */
class AlertaAsistenciaAbierta
{
    use Dispatchable;

    public function __construct(public int $alertaId, public float $porcentaje) {}
}
