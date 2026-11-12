<?php

namespace App\Modules\Asistencia\Livewire;

use App\Modules\Asistencia\Services\CalendarConexionService;
use App\Modules\Identidad\Livewire\Perfil;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Mi perfil · Google Calendar (RF21, RF06, ADR-018, maqueta 52): el docente conecta su cuenta @ues.edu.sv para que sus
 * clases virtuales creen el evento con enlace de Meet, y el Administrador para sincronizar el calendario de
 * evaluaciones del período; o la desconectan.
 */
#[Layout('layouts::app')]
class GoogleCalendar extends Component
{
    use PaginaLivewire;

    public function desconectar(CalendarConexionService $conexion): void
    {
        $conexion->desconectar(auth()->user());
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Se desconectó Google Calendar. Sus próximas clases virtuales no crearán eventos.'));
    }

    public function render(CalendarConexionService $conexion): View
    {
        $usuario = auth()->user();

        return $this->pagina('modules.asistencia.google-calendar', [
            'usuario' => $usuario,
            'pestanas' => Perfil::pestanas($usuario, 'google-calendar'),
            'habilitada' => $conexion->habilitada(),
            'conectado' => $conexion->conectado($usuario),
            'dominio' => $conexion->dominio(),
        ], titulo: __('Mi perfil'));
    }
}
