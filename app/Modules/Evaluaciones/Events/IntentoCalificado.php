<?php

namespace App\Modules\Evaluaciones\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Se calificó un intento de cuestionario y su nota quedó publicada en el momento (RF16): la actividad ya estaba
 * publicada, así que no hay un «publicar notas» posterior que avise. Lo escucha Contenidos para la regla de nota
 * mínima (RF18, ADR-067).
 */
class IntentoCalificado
{
    use Dispatchable;

    public function __construct(public int $evaluacionId, public int $matriculaId) {}
}
