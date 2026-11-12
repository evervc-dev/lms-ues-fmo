<?php

namespace App\Modules\Calificaciones\Livewire;

use App\Modules\Calificaciones\Services\RevisionService;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Vista de sección › Revisiones (RF35): el docente resuelve las solicitudes de revisión ítem por ítem; el
 * Coordinador las consulta sin resolverlas.
 */
#[Layout('layouts::app')]
class RevisionesSeccion extends Component
{
    use PaginaDeSeccion;

    #[Url(except: 'pending')]
    public string $estado = 'pending';

    public function render(RevisionService $revisiones): View
    {
        $seccionId = $this->seccionId();

        return $this->paginaDeSeccion('modules.calificaciones.revisiones-seccion', [
            'solicitudes' => $revisiones->deSeccion(auth()->user(), $seccionId, $this->estado),
            'estados' => RevisionService::ESTADOS,
        ]);
    }
}
