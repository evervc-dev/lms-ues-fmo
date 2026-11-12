<?php

namespace App\Modules\Comunicacion\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un aviso nuevo se sumó a uno reciente sin leer (RF23, CA3): no hay fila nueva ni, por tanto, el evento de
 * Laravel que anuncia una notificación creada. Este avisa a la campana del usuario para que se refresque.
 *
 * Va por el canal privado de cada usuario (App.Models.User.{id}), el mismo del push de notificaciones, y por
 * la cola `notificaciones`.
 */
class AvisoAgrupado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public string $queue = 'notificaciones';

    public function __construct(public int $usuarioId) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$this->usuarioId);
    }

    public function broadcastAs(): string
    {
        return 'avisos.actualizados';
    }
}
