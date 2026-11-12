<?php

namespace App\Modules\Comunicacion\Notifications;

use App\Models\User;
use App\Modules\Comunicacion\Services\NotificacionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso de RF23, en la cola `notificaciones` (RNF12). La fila de la bandeja la crea NotificacionService::enviar()
 * antes de encolar, y el id de esa fila viaja en `$id`; aquí solo salen el push por Reverb y el correo.
 *
 * Los tres métodos de contenido (`toDatabase`, `toBroadcast`, `toMail`) dicen lo mismo con el mismo texto.
 */
class Aviso extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $evento,
        public string $titulo,
        public string $cuerpo,
        public ?string $url = null,
        public ?string $dedup = null,
    ) {
        $this->onQueue('notificaciones');
    }

    /**
     * El push siempre; el correo solo si el usuario no lo desactivó para este tipo de aviso (CA2).
     *
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return app(NotificacionService::class)->canales($notifiable, $this->evento);
    }

    /**
     * @return array{title: string, body: string, url: string|null}
     */
    public function toDatabase(object $notifiable): array
    {
        return ['title' => $this->titulo, 'body' => $this->cuerpo, 'url' => $this->url];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toDatabase($notifiable)))->onQueue('notificaciones');
    }

    public function toMail(User $notifiable): MailMessage
    {
        $correo = (new MailMessage)
            ->subject($this->titulo)
            ->greeting(__('Hola, :nombre', ['nombre' => $notifiable->first_name]))
            ->line($this->cuerpo);

        if ($this->url !== null) {
            $correo->action(__('Abrir en la plataforma'), $this->url);
        }

        return $correo->salutation(__('LMS UES FMO'));
    }
}
