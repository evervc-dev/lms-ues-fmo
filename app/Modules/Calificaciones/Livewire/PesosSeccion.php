<?php

namespace App\Modules\Calificaciones\Livewire;

use App\Modules\Calificaciones\Services\ComputoService;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Vista de sección › Notas › Pesos (RF17, CP-RF17-01): el docente ajusta el peso de cada cómputo y de las
 * actividades de cada cómputo. El formato se valida aquí; la suma del 100 % y el bloqueo con notas publicadas, en
 * ComputoService.
 */
#[Layout('layouts::app')]
class PesosSeccion extends Component
{
    use PaginaDeSeccion;

    /** @var array<int|string, string|float|int|null> grading_component_id => peso */
    public array $computos = [];

    /** @var array<int|string, string|float|int|null> grade_item_id => peso */
    public array $actividades = [];

    /**
     * Lo que leyó cargar() en esta petición; no se guarda entre peticiones (no es pública).
     *
     * @var array{computos: Collection<int, \stdClass>, bloqueo: string|null, problemas: array<int, string>}|null
     */
    private ?array $datos = null;

    public function mount(ComputoService $servicio): void
    {
        $this->cargar($servicio);
    }

    public function guardar(ComputoService $servicio): void
    {
        $this->validate([
            'computos.*' => 'required|numeric|gt:0|max:100',
            'actividades.*' => 'required|numeric|gt:0|max:100',
        ], [], ['computos.*' => __('peso del cómputo'), 'actividades.*' => __('peso de la actividad')]);

        try {
            $servicio->guardarPesos(auth()->user(), $this->seccionId(), $this->computos, $this->actividades);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $campo => $mensajes) {
                $this->addError($campo, $mensajes[0]);
            }

            return;
        }

        $this->cargar($servicio);
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Pesos guardados.'));
    }

    public function render(ComputoService $servicio): View
    {
        // Tras mount() o guardar() reutiliza lo que cargar() ya leyó en esta misma petición.
        $datos = $this->datos ?? $servicio->pesos(auth()->user(), $this->seccionId());

        // «estructura» y no «computos»: la propiedad pública $computos (los pesos escritos) ya ocupa ese nombre en la vista.
        return $this->paginaDeSeccion('modules.calificaciones.pesos', [
            'estructura' => $datos['computos'],
            'bloqueo' => $datos['bloqueo'],
            'problemas' => $datos['problemas'],
            'sumaComputos' => $this->suma($this->computos),
            'sumasActividades' => $datos['computos']->mapWithKeys(fn (object $c) => [
                $c->id => $this->suma(array_intersect_key($this->actividades, array_flip($c->items->pluck('id')->all()))),
            ])->all(),
        ]);
    }

    /**
     * Suma de los pesos escritos en pantalla, para mostrarla mientras el docente edita.
     *
     * @param  array<int|string, string|float|int|null>  $pesos
     */
    protected function suma(array $pesos): string
    {
        return ComputoService::porcentaje(round(collect($pesos)->sum(fn ($p) => is_numeric($p) ? (float) $p : 0.0), 2));
    }

    private function cargar(ComputoService $servicio): void
    {
        $this->datos = $servicio->pesos(auth()->user(), $this->seccionId());
        $computos = $this->datos['computos'];
        $this->computos = $computos->mapWithKeys(fn (object $c) => [$c->id => ComputoService::porcentaje((float) $c->weight_percent)])->all();
        $this->actividades = $computos->flatMap(fn (object $c) => $c->items)
            ->mapWithKeys(fn (object $i) => [$i->id => ComputoService::porcentaje((float) $i->weight_percent)])->all();
    }
}
