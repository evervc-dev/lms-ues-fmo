<?php

namespace App\Modules\Reportes\Livewire;

use App\Modules\Reportes\Services\PanelService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Inicio: panel por rol (RF24, maquetas 02, 03, 17, 44, 45 y 46). Un bloque por cada rol del usuario,
 * en el orden del menú (ADR-034).
 */
#[Layout('layouts::app', ['encabezado' => false])]
#[Title('Inicio')]
class Inicio extends Component
{
    private const ORDEN = ['estudiante', 'docente', 'coordinador', 'jefe_departamento', 'administrador', 'soporte_tecnico'];

    public function render(PanelService $panel): View
    {
        $usuario = auth()->user();
        $metodos = ['estudiante' => 'estudiante', 'docente' => 'docente', 'coordinador' => 'coordinador', 'jefe_departamento' => 'jefe', 'administrador' => 'administrador', 'soporte_tecnico' => 'soporte'];
        $bloques = collect(self::ORDEN)->filter(fn ($rol) => $usuario->hasRole($rol))
            ->mapWithKeys(fn ($rol) => [$rol => $panel->{$metodos[$rol]}($usuario)])->all();

        return view('modules.reportes.inicio', ['usuario' => $usuario, 'bloques' => $bloques]);
    }
}
