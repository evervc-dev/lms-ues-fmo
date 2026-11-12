<?php

namespace App\Modules\Comunicacion\Livewire;

use App\Modules\Comunicacion\Services\ForoService;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Vista de sección › Foro (RF30, maqueta 40).
 */
#[Layout('layouts::app')]
class Foro extends Component
{
    use PaginaDeSeccion;

    public string $titulo = '';

    public string $mensaje = '';

    public function crear(ForoService $foro): void
    {
        $this->validate(['titulo' => 'required|string|max:200', 'mensaje' => 'required|string|max:5000'], [], ['titulo' => 'título', 'mensaje' => 'mensaje']);
        $hilo = $foro->crearHilo(auth()->user(), $this->seccionId(), $this->titulo, $this->mensaje);
        $this->redirectRoute('secciones.foro.hilo', ['seccion' => $this->seccion, 'hilo' => $hilo], navigate: true);
    }

    public function render(ForoService $foro): View
    {
        return $this->paginaDeSeccion('modules.comunicacion.foro', ['hilos' => $foro->hilos(auth()->user(), $this->seccionId())]);
    }
}
