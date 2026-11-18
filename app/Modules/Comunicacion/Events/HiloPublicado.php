<?php

namespace App\Modules\Comunicacion\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Alguien abrió un hilo en el foro de una sección (RF30). Se avisa a los demás participantes de la sección:
 * antes solo se avisaba de las respuestas, así que un hilo nuevo pasaba inadvertido.
 */
class HiloPublicado
{
    use Dispatchable;

    public function __construct(public int $hiloId) {}
}
