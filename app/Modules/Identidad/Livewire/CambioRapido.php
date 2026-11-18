<?php

namespace App\Modules\Identidad\Livewire;

use App\Modules\Identidad\Livewire\Concerns\ConCambioDeRol;
use App\Modules\Identidad\Services\AccesoDesarrolloService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * «Entrar como…» desde el menú de usuario, sin volver al login (docs/12 Desarrollo/Setup del entorno.md):
 * el mismo buscador y el mismo cambio de rol de la pantalla de desarrollo, más el enlace para restaurar los
 * roles que esta sesión reemplazó. Solo existe con LMS_DEV_LOGIN=true y fuera de producción.
 */
class CambioRapido extends Component
{
    use ConCambioDeRol;

    public function mount(AccesoDesarrolloService $acceso): void
    {
        abort_unless($acceso->habilitado(), 404);
    }

    public function render(AccesoDesarrolloService $acceso): View
    {
        return view('modules.identidad.cambio-rapido', [
            'resultados' => $acceso->buscar($this->buscar),
            'elegida' => $this->cuentaElegida(),
            'roles' => $acceso->rolesDisponibles(),
            'previos' => $acceso->rolesPrevios(),
        ]);
    }
}
