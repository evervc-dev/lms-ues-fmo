<?php

namespace App\Modules\Evaluaciones\Livewire;

use App\Modules\Academica\Services\SeccionService;
use App\Modules\Evaluaciones\Services\CuestionarioService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Monitor del cuestionario para el docente (RF16, ADR-042): estado de cada estudiante con su tiempo restante,
 * actualizado cada 15 s mientras haya algo en curso. Cierra intentos y da minutos extra a uno, varios o todos.
 */
class MonitorCuestionario extends Component
{
    #[Locked]
    public string $seccion = '';

    #[Locked]
    public string $cuestionario = '';

    /** @var array<int, string> ulids de los intentos marcados */
    public array $seleccionados = [];

    public int $minutos = 10;

    public function mount(string $seccion, string $cuestionario): void
    {
        $this->seccion = $seccion;
        $this->cuestionario = $cuestionario;
    }

    public function darTiempo(bool $todos, CuestionarioService $cuestionarios): void
    {
        $this->validate(['minutos' => 'required|integer|min:1|max:60', 'seleccionados.*' => 'string|max:40'], [], ['minutos' => __('minutos')]);
        $n = $cuestionarios->darTiempo(auth()->user(), $this->seccionId(), $this->cuestionario, $todos ? null : $this->seleccionados, $this->minutos);

        $this->reset('seleccionados');
        $this->dispatch('aviso', tipo: $n > 0 ? 'exito' : 'info', mensaje: $n > 0
            ? trans_choice('{1} Se dieron :m min a 1 estudiante.|[2,*] Se dieron :m min a :count estudiantes.', $n, ['m' => $this->minutos])
            : __('Ningún intento se amplió: ya están en el máximo de :t min después del cierre.', ['t' => CuestionarioService::TIEMPO_EXTRA_MAXIMO]));
    }

    public function cerrar(string $intento, CuestionarioService $cuestionarios): void
    {
        try {
            $cuestionarios->cerrarIntento(auth()->user(), $this->seccionId(), $this->cuestionario, $intento);
        } catch (ValidationException $excepcion) {
            $this->dispatch('aviso', tipo: 'error', mensaje: (string) collect($excepcion->errors())->flatten()->first());

            return;
        }
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Intento cerrado y corregido con lo que el estudiante había guardado.'));
    }

    public function render(CuestionarioService $cuestionarios): View
    {
        $datos = $cuestionarios->monitor(auth()->user(), $this->seccionId(), $this->cuestionario);

        return view('modules.evaluaciones.monitor-cuestionario', $datos + ['revisionVisible' => now()->gte($datos['revisionDesde'])]);
    }

    private function seccionId(): int
    {
        return app(SeccionService::class)->idPorUlid($this->seccion);
    }
}
