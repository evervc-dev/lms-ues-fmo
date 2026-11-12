<?php

namespace App\Modules\Contenidos\Livewire;

use App\Modules\Contenidos\Services\ContenidoService;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Analítica de la sección (RF13, maqueta 42): mapa de calor de recursos por estudiante, coloreado por aperturas o por
 * tiempo con la pestaña visible.
 */
#[Layout('layouts::app')]
class Analitica extends Component
{
    use PaginaDeSeccion;

    #[Url(except: 'aperturas')]
    public string $medida = 'aperturas';

    /**
     * Duración legible: «menos de 1 min», «12 min» o «1 h 5 min».
     */
    public static function duracion(int $segundos): string
    {
        $minutos = intdiv($segundos, 60);
        if ($minutos < 1) {
            return $segundos > 0 ? __('menos de 1 min') : __('0 min');
        }

        return $minutos < 60 ? __(':m min', ['m' => $minutos]) : __(':h h :m min', ['h' => intdiv($minutos, 60), 'm' => $minutos % 60]);
    }

    public function render(ContenidoService $contenidos): View
    {
        $medida = in_array($this->medida, ['aperturas', 'tiempo'], true) ? $this->medida : 'aperturas';

        return $this->paginaDeSeccion('modules.contenidos.analitica', $contenidos->analitica(auth()->user(), $this->seccionId()) + ['medidaActiva' => $medida]);
    }
}
