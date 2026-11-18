<?php

namespace App\Modules\Identidad\Livewire;

use App\Modules\Identidad\Livewire\Concerns\ConCambioDeRol;
use App\Modules\Identidad\Services\AccesoDesarrolloService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::guest', ['ancho' => 'lg'])]
#[Title('Entrar como…')]
class EntrarComo extends Component
{
    use ConCambioDeRol;

    public function mount(AccesoDesarrolloService $acceso): void
    {
        abort_unless($acceso->habilitado(), 404);
    }

    /**
     * Las cuentas de desarrollo ya son una por rol: entran directo, sin preguntar el rol.
     */
    public function entrar(int $usuario, AccesoDesarrolloService $acceso): void
    {
        $acceso->entrarComo($usuario);

        $this->redirectRoute('inicio');
    }

    public function render(AccesoDesarrolloService $acceso): View
    {
        return view('modules.identidad.entrar-como', [
            'cuentas' => $acceso->cuentasDeDesarrollo(),
            'resultados' => $acceso->buscar($this->buscar),
            'elegida' => $this->cuentaElegida(),
            'roles' => $acceso->rolesDisponibles(),
        ]);
    }
}
