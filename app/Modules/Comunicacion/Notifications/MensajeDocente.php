<?php

namespace App\Modules\Comunicacion\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Mensaje del docente a un grupo de estudiantes filtrado desde el libro de calificaciones (RF31). Es un Aviso
 * del evento `mass_message.sent`: llega a la bandeja y por correo, y el correo no se puede silenciar ni se
 * agrupa (ADR-044).
 *
 * Cada estudiante recibe **su propio correo**, dirigido solo a él: nadie ve a los demás destinatarios. El
 * correo responde al docente (Reply-To) para que el estudiante pueda contestar sin mensajería interna.
 */
class MensajeDocente extends Aviso
{
    public function __construct(
        string $asunto,
        string $texto,
        ?string $url,
        string $dedup,
        public string $docente,
        public string $correoDocente,
        public string $materia,
    ) {
        parent::__construct('mass_message.sent', $asunto, $texto, $url, $dedup);
    }

    public function toMail(User $notifiable): MailMessage
    {
        $correo = (new MailMessage)
            ->subject($this->titulo)
            ->replyTo($this->correoDocente, $this->docente)
            ->greeting(__('Hola, :nombre', ['nombre' => $notifiable->first_name]));

        foreach (preg_split('/\R{2,}/', trim($this->cuerpo)) ?: [] as $parrafo) {
            $correo->line(trim($parrafo));
        }

        return $correo
            ->line(__('Mensaje de :docente, docente de :materia. Puede responder a este correo.', ['docente' => $this->docente, 'materia' => $this->materia]))
            ->salutation(__('LMS UES FMO'));
    }
}
