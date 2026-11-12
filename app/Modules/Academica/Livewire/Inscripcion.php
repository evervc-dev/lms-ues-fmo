<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\MatriculaService;
use App\Modules\Academica\Services\PeriodoService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Inscripción de materias (RF29, ADR-050): las asignaturas de la carrera del estudiante para el ciclo en curso,
 * agrupadas por el ciclo del plan y con búsqueda por código o nombre. Cada tarjeta lleva al detalle, donde se
 * inscribe con la clave. Arriba, hasta cuándo puede inscribirse (ADR-051).
 */
#[Layout('layouts::app')]
class Inscripcion extends Component
{
    use PaginaLivewire;

    #[Url(except: '')]
    public string $buscar = '';

    public function render(MatriculaService $matricula): View
    {
        $materias = $matricula->materiasInscribibles(auth()->user(), $this->buscar);

        return $this->pagina('modules.academica.inscripcion', [
            // Por ciclo del plan: en un período solo salen los impares o los pares, así que son cinco grupos a lo sumo.
            'porCiclo' => $materias->groupBy('cycle'),
            'total' => $materias->count(),
            'buscando' => trim($this->buscar) !== '',
            'ciclo' => PeriodoService::ciclo(),
            'plazo' => $matricula->plazoDe((int) auth()->id()),
        ]);
    }
}
