<?php

namespace App\Modules\Comunicacion\Livewire;

use App\Modules\Comunicacion\Services\NotificacionService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Campana de la cabecera (RF23, docs/13 Layout/Layout base.md): el contador de avisos sin leer y un panel con
 * los últimos 10. Llega en vivo por Reverb y, si el WebSocket no conecta, consulta cada 15 s (ADR-010, ADR-043).
 *
 * Es lo propio de cada usuario (alcance P): solo lee y marca los avisos del usuario autenticado.
 */
class Campana extends Component
{
    public int $sinLeer = 0;

    public function mount(NotificacionService $notificaciones): void
    {
        $this->sinLeer = $notificaciones->contarSinLeer(auth()->user());
    }

    /**
     * Un aviso nuevo llega por el evento de Laravel (`BroadcastNotificationCreated`); uno que se sumó a otro
     * reciente, por `avisos.actualizados`. Los dos solo piden volver a leer. El prefijo «.» le dice a Echo
     * que el nombre del evento es el completo y no debe anteponerle el espacio de nombres de la aplicación.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $canal = 'echo-private:App.Models.User.'.auth()->id();

        return [
            $canal.',.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated' => 'refrescar',
            $canal.',.avisos.actualizados' => 'refrescar',
        ];
    }

    /**
     * Relee el conteo de la base (no suma en el cliente). Si subió, la campana se mece y sale el toast con el
     * título del aviso; un aviso repetido que la bandeja ignoró no hace ruido.
     *
     * @param  array<string, mixed>  $aviso  carga del broadcast: title, body, url, event_code
     */
    public function refrescar(NotificacionService $notificaciones, array $aviso = []): void
    {
        $anterior = $this->sinLeer;
        $this->sinLeer = $notificaciones->contarSinLeer(auth()->user());

        if ($this->sinLeer > $anterior) {
            $this->dispatch('campana-nueva');
            $this->dispatch('aviso', tipo: 'info', mensaje: $aviso['title'] ?? __('Tiene un aviso nuevo.'));
        }
    }

    /**
     * La página de notificaciones marcó avisos como leídos: la campana baja su número al instante.
     */
    #[On('avisos-actualizados')]
    public function alMarcarLeidos(NotificacionService $notificaciones): void
    {
        $this->refrescar($notificaciones);
    }

    public function abrir(string $id, NotificacionService $notificaciones): void
    {
        $url = $notificaciones->abrir(auth()->user(), $id);

        if ($url !== null) {
            $this->redirect($url, navigate: str_starts_with($url, url('/')));
        }
    }

    public function marcarTodas(NotificacionService $notificaciones): void
    {
        $notificaciones->marcarTodas(auth()->user());
    }

    public function render(NotificacionService $notificaciones): View
    {
        return view('modules.comunicacion.campana', [
            'sinLeer' => $this->sinLeer = $notificaciones->contarSinLeer(auth()->user()),
            'recientes' => $notificaciones->recientes(auth()->user()),
        ]);
    }
}
