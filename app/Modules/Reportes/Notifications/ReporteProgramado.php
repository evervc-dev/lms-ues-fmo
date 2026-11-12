<?php

namespace App\Modules\Reportes\Notifications;

use App\Models\User;
use App\Modules\Reportes\Services\ReporteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Reporte programado (RF25): sale solo por correo, con el CSV adjunto, en la cola `notificaciones`. No es un aviso
 * de la bandeja: el reporte ya va en el correo.
 */
class ReporteProgramado extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $tipo, public string $csv, public int $filas)
    {
        $this->onQueue('notificaciones');
    }

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $nombre = __(ReporteService::TIPOS[$this->tipo] ?? $this->tipo);

        return (new MailMessage)
            ->subject(__('Reporte programado: :r', ['r' => $nombre]))
            ->greeting(__('Hola, :nombre', ['nombre' => $notifiable->first_name]))
            ->line(trans_choice('{0} Adjuntamos su reporte «:r»: esta vez no tiene filas.|{1} Adjuntamos su reporte «:r», con 1 fila.|[2,*] Adjuntamos su reporte «:r», con :count filas.', $this->filas, ['r' => $nombre]))
            ->line(__('Puede cambiar o cancelar los reportes programados en la sección Reportes.'))
            ->attachData($this->csv, "reporte-{$this->tipo}-".now()->format('Y-m-d').'.csv', ['mime' => 'text/csv'])
            ->salutation(__('LMS UES FMO'));
    }
}
