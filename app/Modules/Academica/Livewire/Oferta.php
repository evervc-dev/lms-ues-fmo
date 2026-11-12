<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\OfertaService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Estadísticas de oferta académica (RF10, maqueta 50).
 */
#[Layout('layouts::app')]
class Oferta extends Component
{
    use PaginaLivewire;

    /** Carrera por la que se filtra la tabla; vacío muestra todas las del alcance. */
    #[Url(as: 'carrera', except: '')]
    public string $filtroCarrera = '';

    public function render(OfertaService $oferta): View
    {
        $datos = $oferta->estadisticas(auth()->user(), $this->filtroCarrera === '' ? null : (int) $this->filtroCarrera);
        // El servicio descarta la carrera que queda fuera del alcance: el filtro vuelve a «todas» para no mentir.
        $this->filtroCarrera = $datos['carrera'] === null ? '' : (string) $datos['carrera'];

        return $this->pagina('modules.academica.oferta', $datos + ['cupo' => OfertaService::CUPO_REFERENCIA]);
    }
}
