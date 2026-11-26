<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\MatriculaService;
use App\Modules\Academica\Services\PeriodoService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
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

    /** Solo las materias con algún grupo abierto (revisión UX del rol Estudiante). */
    #[Url(except: false)]
    public bool $soloConGrupos = false;

    public function render(MatriculaService $matricula): View
    {
        $materias = $matricula->materiasInscribibles(auth()->user(), $this->buscar)
            ->when($this->soloConGrupos, fn ($c) => $c->filter(fn ($m) => $m->secciones > 0));

        return $this->pagina('modules.academica.inscripcion', [
            // Por ciclo del plan: en un período solo salen los impares o los pares, así que son cinco grupos a lo sumo.
            // Dentro de cada ciclo, primero las que tienen grupo abierto: antes había que recorrerlas todas.
            'porCiclo' => $materias->groupBy('cycle')->map(fn ($grupo) => $grupo->sortBy(fn ($m) => [$m->secciones > 0 ? 0 : 1, $m->code])->values()),
            'total' => $materias->count(),
            'buscando' => trim($this->buscar) !== '' || $this->soloConGrupos,
            'periodo' => DB::table('academic_periods')->where('id', PeriodoService::activoId())->value('name'),
            'plazo' => $matricula->plazoDe((int) auth()->id()),
            'plan' => $matricula->planDe((int) auth()->id()),
        ]);
    }
}
