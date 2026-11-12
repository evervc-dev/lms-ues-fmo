<?php

namespace App\Modules\Identidad\Livewire;

use App\Modules\Identidad\Services\IntegracionService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
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

    public function render(IntegracionService $integraciones): View
    {
        return $this->pagina('modules.identidad.integraciones', ['integraciones' => $integraciones->listar(auth()->user())]);
    }
}
