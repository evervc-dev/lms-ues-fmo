<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\SeccionService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Secciones por ámbito: Mis secciones (docente), Supervisión de aulas (coordinador) y
 * Secciones del departamento (jefe). Cada tarjeta lleva a la Vista de sección.
 */
#[Layout('layouts::app')]
class Secciones extends Component
{
    use PaginaLivewire;

    #[Url]
    public string $ambito = '';

    #[Url(except: '')]
    public string $buscar = '';

    public function mount(SeccionService $secciones): void
    {
        if (! in_array($this->ambito, $secciones->ambitos(auth()->user()), true)) {
            $this->ambito = (string) $secciones->ambitoPorDefecto(auth()->user());
        }
        abort_if($this->ambito === '', 403);
    }

    public function render(SeccionService $secciones): View
    {
        $titulos = ['propias' => __('Mis secciones'), 'carrera' => __('Supervisión de aulas'), 'departamento' => __('Secciones del departamento')];
        $ambitos = $secciones->ambitos(auth()->user());

        return $this->pagina('modules.academica.secciones', [
            'secciones' => $secciones->listar(auth()->user(), $this->ambito, $this->buscar),
            'titulo' => $titulos[$this->ambito],
            'pestanas' => count($ambitos) > 1 ? array_map(fn ($a) => ['etiqueta' => $titulos[$a], 'url' => route('secciones', ['ambito' => $a]), 'activa' => $a === $this->ambito], $ambitos) : [],
            'soloLectura' => $this->ambito === 'carrera',
        ], titulo: $titulos[$this->ambito]);
    }
}
