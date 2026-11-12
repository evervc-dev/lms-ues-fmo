<?php

namespace App\Modules\Comunicacion\Livewire;

use App\Modules\Comunicacion\Services\NotificacionService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Notificaciones (RF23).
 */
#[Layout('layouts::app')]
class Notificaciones extends Component
{
    use PaginaLivewire;
    use WithPagination;

    #[Url(except: false)]
    public bool $sinLeer = false;

    public function updatingSinLeer(): void
    {
        $this->resetPage();
    }

    public function marcarLeida(string $id, NotificacionService $notificaciones): void
    {
        $notificaciones->marcarLeida(auth()->user(), $id);
        $this->dispatch('avisos-actualizados');
    }

    /**
     * Abrir un aviso lo marca leído y lleva a la pantalla del evento. Si ese aviso no tiene pantalla, se queda
     * aquí: su texto ya trae el detalle.
     */
    public function abrir(string $id, NotificacionService $notificaciones): void
    {
        $url = $notificaciones->abrir(auth()->user(), $id);
        $this->dispatch('avisos-actualizados');

        if ($url !== null) {
            $this->redirect($url, navigate: str_starts_with($url, url('/')));
        }
    }

    public function marcarTodas(NotificacionService $notificaciones): void
    {
        $n = $notificaciones->marcarTodas(auth()->user());
        $this->dispatch('avisos-actualizados');
        $this->dispatch('aviso', tipo: 'exito', mensaje: trans_choice('{0} No había avisos sin leer.|{1} Se marcó 1 aviso como leído.|[2,*] Se marcaron :count avisos como leídos.', $n));
    }

    public function render(NotificacionService $notificaciones): View
    {
        return $this->pagina('modules.comunicacion.notificaciones', [
            'avisos' => $notificaciones->listar(auth()->user(), $this->sinLeer),
            'pendientes' => auth()->user()->unreadNotifications()->count(),
        ]);
    }
}
