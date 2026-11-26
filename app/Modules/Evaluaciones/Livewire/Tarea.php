<?php

namespace App\Modules\Evaluaciones\Livewire;

use App\Modules\Calificaciones\Services\CalificacionService;
use App\Modules\Evaluaciones\Services\ActividadService;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Tarea: detalle y entrega (RF15, maquetas 08 y 09).
 */
#[Layout('layouts::app')]
class Tarea extends Component
{
    use PaginaDeSeccion;
    use WithFileUploads;

    #[Locked]
    public string $tareaUlid = '';

    /** @var TemporaryUploadedFile|null */
    public $archivo = null;

    public function mount(string $tarea): void
    {
        $this->tareaUlid = $tarea;
    }

    public function entregar(ActividadService $actividades): void
    {
        $this->validate(['archivo' => 'required|file|max:102400'], [], ['archivo' => 'archivo']);
        $actividades->entregar(auth()->user(), $this->seccionId(), $this->tareaUlid, $this->archivo);
        $this->reset('archivo');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Entrega enviada.'));
    }

    public function cancelar(ActividadService $actividades): void
    {
        try {
            $actividades->cancelarEntrega(auth()->user(), $this->seccionId(), $this->tareaUlid);
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());

            return;
        }
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Entrega cancelada. Puede enviar otra mientras la tarea esté abierta.'));
    }

    public function render(ActividadService $actividades): View
    {
        $datos = $actividades->tarea(auth()->user(), $this->seccionId(), $this->tareaUlid);

        return $this->paginaDeSeccion('modules.evaluaciones.tarea', $datos + [
            'politicas' => ActividadService::POLITICAS,
            // La nota se colorea como en la boleta: verde si aprueba, rojo si no (QA: «sale en rojo aunque sea mayor a 6»).
            'minima' => CalificacionService::notaMinima(),
            'calificada' => $actividades->entregaCalificada($datos),
        ]);
    }
}
