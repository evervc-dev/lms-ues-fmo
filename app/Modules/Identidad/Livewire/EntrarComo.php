<?php

namespace App\Modules\Identidad\Livewire;

use App\Modules\Identidad\Services\AccesoDesarrolloService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::guest', ['ancho' => 'lg'])]
#[Title('Entrar como…')]
class EntrarComo extends Component
{
    public string $buscar = '';

    public function mount(AccesoDesarrolloService $acceso): void
    {
        abort_unless($acceso->habilitado(), 404);
    }

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
        ]);
    }
}
