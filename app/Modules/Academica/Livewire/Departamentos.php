<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Livewire\Concerns\GestionaDepartamentos;
use App\Modules\Academica\Services\CarreraService;
use App\Modules\Academica\Services\CatalogoService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Departamentos de la universidad para el Administrador (RF07, ADR-083): primer nivel de la navegación
 * Departamentos → Carreras → Carrera. CarreraService exige el alcance global. Desde aquí también se crean, editan y
 * eliminan departamentos (antes solo en Catálogo › Estructura, donde el Administrador no los encontraba).
 */
#[Layout('layouts::app')]
class Departamentos extends Component
{
    use GestionaDepartamentos;
    use PaginaLivewire;

    public function render(CarreraService $carreras, CatalogoService $catalogo): View
    {
        return $this->pagina('modules.academica.departamentos', [
            'departamentos' => $carreras->departamentos(auth()->user()),
            'gestiona' => $catalogo->gestionaEstructura(auth()->user()),
        ]);
    }
}
