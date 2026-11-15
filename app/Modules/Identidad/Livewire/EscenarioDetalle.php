<?php

namespace App\Modules\Identidad\Livewire;

use App\Modules\Identidad\Services\EscenarioService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * La página de un escenario: el reparto, los enlaces de cada rol y la checklist.
 *
 * Es la que se comparte con el grupo de testers. El código del escenario va en la URL y hace de permiso
 * para verla; marcar un caso sí pide sesión iniciada, para que quede a nombre de quien lo probó.
 */
#[Layout('layouts::guest', ['ancho' => 'xl'])]
#[Title('Escenario de prueba')]
class EscenarioDetalle extends Component
{
    public string $codigo = '';

    /** Caso de la checklist cuyo cuadro de nota está abierto. */
    public ?int $editando = null;

    public string $nota = '';

    public function mount(string $codigo, EscenarioService $escenarios): void
    {
        abort_unless($escenarios->habilitado(), 404);
        abort_if($escenarios->porCodigo($codigo) === null, 404);

        $this->codigo = strtoupper($codigo);
    }

    public function abrirNota(int $checkId, EscenarioService $escenarios): void
    {
        $caso = $escenarios->detalle($this->escenario())['checklist']->firstWhere('id', $checkId);

        if ($caso === null) {
            return;
        }

        $this->editando = $checkId;
        $this->nota = (string) ($caso->note ?? '');
    }

    public function cerrarNota(): void
    {
        $this->editando = null;
        $this->nota = '';
    }

    public function marcar(int $checkId, string $estado, EscenarioService $escenarios): void
    {
        if (! auth()->check()) {
            $this->addError('checklist', __('Inicie sesión para marcar un caso: así queda a su nombre.'));

            return;
        }

        $actual = $escenarios->detalle($this->escenario())['checklist']->firstWhere('id', $checkId);

        if ($actual === null) {
            return;
        }

        $escenarios->marcar($checkId, $estado, $this->editando === $checkId ? (trim($this->nota) ?: null) : $actual->note, auth()->id());
        $this->cerrarNota();
    }

    public function guardarNota(int $checkId, EscenarioService $escenarios): void
    {
        if (! auth()->check()) {
            $this->addError('checklist', __('Inicie sesión para anotar un caso.'));

            return;
        }

        $actual = $escenarios->detalle($this->escenario())['checklist']->firstWhere('id', $checkId);

        if ($actual !== null) {
            $escenarios->marcar($checkId, $actual->status, trim($this->nota) ?: null, auth()->id());
        }

        $this->cerrarNota();
    }

    public function limpiar(EscenarioService $escenarios): void
    {
        if (! auth()->check() || ! auth()->user()->hasRole('administrador')) {
            $this->addError('checklist', __('Solo un Administrador con sesión iniciada puede limpiar un escenario.'));

            return;
        }

        $escenarios->limpiar((int) $this->escenario()->id);

        $this->redirectRoute('dev.escenarios', navigate: true);
    }

    public function render(EscenarioService $escenarios): View
    {
        $detalle = $escenarios->detalle($this->escenario());

        return view('modules.identidad.escenario', $detalle + [
            'roles' => EscenarioService::ROLES,
            'conteo' => $detalle['checklist']->groupBy('status')->map->count(),
        ]);
    }

    private function escenario(): object
    {
        return app(EscenarioService::class)->porCodigo($this->codigo) ?? abort(404);
    }
}
