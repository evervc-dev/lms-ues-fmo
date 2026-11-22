<?php

namespace App\Modules\Identidad\Livewire;

use App\Modules\Identidad\Services\IntegracionService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Integraciones (RNF09, maqueta 51): la configuración de Google Workspace y del correo que está en uso, de solo
 * lectura; se cambia en el `.env` del servidor.
 */
#[Layout('layouts::app')]
class Integraciones extends Component
{
    use PaginaLivewire;

    public function enviarPrueba(IntegracionService $integraciones): void
    {
        try {
            $integraciones->enviarCorreoDePrueba(auth()->user());
            $this->dispatch('aviso', tipo: 'exito', mensaje: __('Se envió un correo de prueba a :c. Revise su bandeja (y la carpeta de spam).', ['c' => auth()->user()->institutional_email]));
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
        }
    }

    public function render(IntegracionService $integraciones): View
    {
        return $this->pagina('modules.identidad.integraciones', ['integraciones' => $integraciones->listar(auth()->user())]);
    }
}
