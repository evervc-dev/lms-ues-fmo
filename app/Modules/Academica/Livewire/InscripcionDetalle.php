<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\MatriculaService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Detalle de una materia para inscribirse (RF29, ADR-050): descripción, grupos abiertos con cupo, choque,
 * horario y docente, y «Inscribirme con clave». La validación de la clave y el cupo los hace MatriculaService.
 */
#[Layout('layouts::app')]
class InscripcionDetalle extends Component
{
    use PaginaLivewire;

    #[Locked]
    public int $asignaturaId;

    public string $clave = '';

    public ?int $seccionElegida = null;

    public function mount(int $asignatura): void
    {
        $this->asignaturaId = $asignatura;
    }

    public function inscribirme(MatriculaService $matricula): void
    {
        $this->validate(['clave' => 'required|string|max:40', 'seccionElegida' => 'required|integer'], [], ['clave' => 'clave', 'seccionElegida' => 'grupo']);
        try {
            $matricula->automatricular(auth()->user(), $this->asignaturaId, $this->clave, (int) $this->seccionElegida);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $this->addError($campo === 'seccion' ? 'seccionElegida' : 'clave', $mensajes[0]);
            }

            return;
        }
        session()->flash('aviso', ['tipo' => 'exito', 'mensaje' => __('Quedó inscrito en la materia.')]);
        $this->redirectRoute('materias', navigate: true);
    }

    public function render(MatriculaService $matricula): View
    {
        $detalle = $matricula->detalleMateria(auth()->user(), $this->asignaturaId);

        return $this->pagina('modules.academica.inscripcion-detalle', $detalle + ['plazo' => $matricula->plazoDe((int) auth()->id())],
            titulo: $detalle['asignatura']->code.' · '.$detalle['asignatura']->name);
    }
}
