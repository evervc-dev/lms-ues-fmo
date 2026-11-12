<?php

namespace App\Modules\Evaluaciones\Livewire;

use App\Modules\Evaluaciones\Services\CuestionarioService;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Nuevo cuestionario (RF16): preguntas sorteadas de un banco, tiempo, intentos y navegación.
 */
#[Layout('layouts::app')]
class NuevoCuestionario extends Component
{
    use PaginaDeSeccion;

    public string $titulo = '';

    public string $instrucciones = '';

    public ?int $componente = null;

    public float $peso = 30;

    public string $abre = '';

    public string $cierra = '';

    public int $minutos = 60;

    public int $intentos = 1;

    public string $navegacion = 'free';

    public ?int $banco = null;

    public int $cantidad = 10;

    public string $tema = '';

    public function mount(): void
    {
        $this->abre = now()->addDay()->setTime(8, 0)->format('Y-m-d\TH:i');
        $this->cierra = now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i');
    }

    public function guardar(CuestionarioService $cuestionarios): void
    {
        $this->validate([
            'titulo' => 'required|string|max:200', 'componente' => 'required|integer', 'peso' => 'required|numeric|min:1|max:100', 'abre' => 'required|date', 'cierra' => 'required|date',
            'minutos' => 'required|integer|min:1|max:600', 'intentos' => 'required|integer|min:1|max:10', 'navegacion' => 'required|in:free,linear',
            'banco' => 'required|integer', 'cantidad' => 'required|integer|min:1|max:200',
        ], [], ['titulo' => 'título', 'componente' => 'cómputo', 'banco' => 'banco']);
        $ulid = $cuestionarios->crearCuestionario(auth()->user(), $this->seccionId(), [
            'componente' => (int) $this->componente, 'titulo' => $this->titulo, 'instrucciones' => $this->instrucciones ?: null, 'abre' => $this->abre, 'cierra' => $this->cierra,
            'peso' => $this->peso, 'minutos' => $this->minutos, 'intentos' => $this->intentos, 'navegacion' => $this->navegacion, 'banco' => (int) $this->banco,
            'cantidad' => $this->cantidad, 'tema' => $this->tema ?: null,
        ]);
        session()->flash('aviso', ['tipo' => 'exito', 'mensaje' => __('Cuestionario creado.')]);
        $this->redirectRoute('secciones.cuestionarios.show', ['seccion' => $this->seccion, 'cuestionario' => $ulid], navigate: true);
    }

    public function render(CuestionarioService $cuestionarios): View
    {
        return $this->paginaDeSeccion('modules.evaluaciones.nuevo-cuestionario', [
            'computos' => DB::table('grading_components')->where('section_id', $this->seccionId())->orderBy('number')->get(),
            'bancos' => $cuestionarios->bancos(auth()->user()),
        ]);
    }
}
