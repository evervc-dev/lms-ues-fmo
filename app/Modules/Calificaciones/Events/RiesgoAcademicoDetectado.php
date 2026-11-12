<?php

namespace App\Modules\Calificaciones\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se abrió una alerta de riesgo académico (`grade_risk`, RF17): la nota proyectada del estudiante con las notas
 * publicadas quedó por debajo de la mínima de aprobación, que viaja con el evento.
 */
class RiesgoAcademicoDetectado
{
    use Dispatchable;

    public function __construct(public int $alertaId, public float $notaProyectada, public float $notaMinima) {}
}
