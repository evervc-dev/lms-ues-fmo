<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\PlanEstudianteService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Mi plan (RF37): el plan de estudios del estudiante por ciclo, con lo aprobado, lo que cursa y lo pendiente. El
 * selector muestra cualquier plan de su carrera; por defecto, el suyo.
 */
#[Layout('layouts::app')]
class MiPlan extends Component
{
    use PaginaLivewire;

    /** El plan elegido en el selector (?plan= en la URL). No se llama $plan: pisaría el plan que recibe la vista. */
    #[Url(as: 'plan', except: null)]
    public ?int $planId = null;

    public function render(PlanEstudianteService $planes): View
    {
        return $this->pagina('modules.academica.mi-plan', $planes->plan(auth()->user(), $this->planId));
    }
}
