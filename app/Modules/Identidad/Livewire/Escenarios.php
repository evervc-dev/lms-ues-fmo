<?php

namespace App\Modules\Identidad\Livewire;

use App\Modules\Identidad\Services\EscenarioService;
use Database\Seeders\Escenarios\Tester;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

/**
 * Wizard de escenarios de prueba, enlazado desde el inicio de sesión.
 *
 * Tres pasos: elegir el flujo, repartir los roles entre los testers del equipo y confirmar. Al terminar
 * lleva a la página del escenario, que es la que se comparte con el grupo.
 *
 * Preparar siembra datos y asigna roles, así que pide la clave del entorno de pruebas
 * (`LMS_ESCENARIOS_CLAVE`); si no hay clave configurada, solo un Administrador con sesión iniciada puede.
 */
#[Layout('layouts::guest', ['ancho' => 'xl'])]
#[Title('Escenarios de prueba')]
class Escenarios extends Component
{
    public int $paso = 1;

    public string $flujo = '';

    /** @var array<string, array<int, int>> rol => id de usuario */
    public array $reparto = [];

    public int $relleno = 0;

    public string $notas = '';

    public string $clave = '';

    public bool $autorizado = false;

    public function mount(EscenarioService $escenarios): void
    {
        abort_unless($escenarios->habilitado(), 404);

        $this->autorizado = $this->yaAutorizado();
    }

    public function elegirFlujo(string $clave, EscenarioService $escenarios): void
    {
        $definicion = $escenarios->flujo($clave);
        $this->flujo = $clave;
        $this->relleno = $definicion['relleno'];
        $this->reparto = array_fill_keys(array_keys($definicion['roles']), []);
        $this->paso = 2;
    }

    public function alternar(string $rol, int $usuarioId, EscenarioService $escenarios): void
    {
        $regla = $escenarios->flujo($this->flujo)['roles'][$rol] ?? null;

        if ($regla === null) {
            return;
        }

        $actuales = $this->reparto[$rol] ?? [];

        if (in_array($usuarioId, $actuales, true)) {
            $this->reparto[$rol] = array_values(array_diff($actuales, [$usuarioId]));

            return;
        }

        // Con tope de uno, elegir a otra persona sustituye a la anterior en vez de dar un error.
        if ($regla['maximo'] === 1) {
            $this->reparto[$rol] = [$usuarioId];

            return;
        }

        if (count($actuales) >= $regla['maximo']) {
            $this->addError('reparto', __('Este rol admite como máximo :n personas.', ['n' => $regla['maximo']]));

            return;
        }

        $this->reparto[$rol] = [...$actuales, $usuarioId];
    }

    public function irAlPaso(int $paso): void
    {
        $this->resetErrorBag();
        $this->paso = max(1, min(3, $paso));
    }

    public function autorizar(): void
    {
        $esperada = (string) config('lms.escenarios_clave');

        if ($esperada !== '' && hash_equals($esperada, trim($this->clave))) {
            session()->put('escenarios.autorizado', true);
            $this->autorizado = true;
            $this->clave = '';
            // Se limpian los avisos de los intentos anteriores: ya no aplican.
            $this->resetErrorBag();

            return;
        }

        $this->addError('clave', __('La clave del entorno de pruebas no es correcta.'));
    }

    public function preparar(EscenarioService $escenarios): void
    {
        if (! $this->yaAutorizado()) {
            $this->addError('clave', __('Primero hay que autorizarse para poder preparar escenarios.'));

            return;
        }

        try {
            $escenario = $escenarios->preparar(
                $this->flujo,
                array_map(fn (array $ids) => array_map('intval', $ids), array_filter($this->reparto)),
                relleno: max(0, $this->relleno),
                preparadoPor: auth()->id(),
                notas: trim($this->notas) !== '' ? trim($this->notas) : null,
            );
        } catch (InvalidArgumentException|RuntimeException $excepcion) {
            $this->addError('reparto', $excepcion->getMessage());

            return;
        }

        $this->redirectRoute('dev.escenarios.show', ['codigo' => $escenario->code], navigate: true);
    }

    public function render(EscenarioService $escenarios): View
    {
        return view('modules.identidad.escenarios', [
            'catalogo' => $escenarios->catalogo(),
            'testers' => $this->autorizado ? $escenarios->testers() : collect(),
            'activos' => $escenarios->lista('ready'),
            'definicion' => $this->flujo !== '' ? $escenarios->flujo($this->flujo) : null,
            'pideClave' => (string) config('lms.escenarios_clave') !== '',
            'roles' => EscenarioService::ROLES,
        ]);
    }

    /**
     * @return Collection<int, Tester>
     */
    public function getElegidosProperty(EscenarioService $escenarios): Collection
    {
        $ids = array_merge(...array_values($this->reparto) ?: [[]]);

        return $escenarios->testers()->whereIn('id', $ids)->values();
    }

    private function yaAutorizado(): bool
    {
        if ((string) config('lms.escenarios_clave') === '') {
            return auth()->check() && auth()->user()->hasRole('administrador');
        }

        return (bool) session('escenarios.autorizado', false);
    }
}
