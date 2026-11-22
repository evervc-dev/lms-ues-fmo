<?php

namespace App\Modules\Identidad\Livewire;

use App\Modules\Identidad\Services\AccesoLocalService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Ingreso con contraseña de la cuenta superadministradora (ADR-082). No aparece en `/login`.
 */
#[Layout('layouts::guest')]
#[Title('Acceso local')]
class AccesoLocal extends Component
{
    #[Validate('required|email|max:255')]
    public string $correo = '';

    #[Validate('required|string|max:255')]
    public string $clave = '';

    public function entrar(AccesoLocalService $acceso): void
    {
        $this->validate();
        $this->resetErrorBag();

        try {
            $acceso->entrar($this->correo, $this->clave);
        } finally {
            $this->reset('clave');
        }

        $this->redirectRoute('inicio');
    }

    public function render(): View
    {
        return view('modules.identidad.acceso-local');
    }
}
