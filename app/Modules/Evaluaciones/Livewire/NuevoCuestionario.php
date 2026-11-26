<?php

namespace App\Modules\Evaluaciones\Livewire;

use App\Modules\Evaluaciones\Services\CuestionarioService;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Crear o editar cuestionario (RF16): preguntas sorteadas de un banco, tiempo, intentos y navegación.
 */
#[Layout('layouts::app')]
class NuevoCuestionario extends Component
{
    use PaginaDeSeccion;

    #[Locked]
    public ?string $editandoUlid = null;

    #[Locked]
    public int $intentosCount = 0;

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

    /** Preguntas al azar por intento; 0 significa todas las del banco con el tema elegido. */
    public int $cantidad = 10;

    public string $tema = '';

    public function updatedBanco(): void
    {
        $this->tema = '';
    }

    public function mount(CuestionarioService $cuestionarios, ?string $cuestionario = null): void
    {
        if ($cuestionario !== null) {
            $datos = $cuestionarios->cuestionarioParaEditar(auth()->user(), $this->seccionId(), $cuestionario);
            $c = $datos['cuestionario'];
            $r = $datos['regla'];
            $this->editandoUlid = $c->ulid;
            $this->intentosCount = $datos['intentosCount'];
            $this->titulo = $c->title;
            $this->instrucciones = $c->instructions ?? '';
            $this->componente = (int) $c->component_id;
            $this->peso = (float) $c->weight_percent;
            $this->abre = Carbon::parse($c->opens_at)->timezone('America/El_Salvador')->format('Y-m-d\TH:i');
            $this->cierra = Carbon::parse($c->closes_at)->timezone('America/El_Salvador')->format('Y-m-d\TH:i');
            $this->minutos = (int) $c->time_limit_minutes;
            $this->intentos = (int) $c->max_attempts;
            $this->navegacion = $c->navigation;
            if ($r) {
                $this->banco = (int) $r->bank_id;
                $this->cantidad = (int) $r->quantity;
                $this->tema = (string) ($r->topic ?? '');
            }

            return;
        }

        $this->abre = now()->addDay()->setTime(8, 0)->format('Y-m-d\TH:i');
        $this->cierra = now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i');
    }

    public function guardar(CuestionarioService $cuestionarios): void
    {
        $this->validate([
            'titulo' => 'required|string|max:200', 'componente' => 'required|integer', 'peso' => 'required|numeric|min:1|max:100', 'abre' => 'required|date', 'cierra' => 'required|date',
            'minutos' => 'required|integer|min:1|max:600', 'intentos' => 'required|integer|min:1|max:10', 'navegacion' => 'required|in:free,linear',
            'banco' => 'required|integer', 'cantidad' => 'required|integer|min:0|max:200',
        ], [], ['titulo' => 'título', 'componente' => 'cómputo', 'banco' => 'banco']);

        if ($this->editandoUlid !== null) {
            $cuestionarios->actualizarCuestionario(auth()->user(), $this->seccionId(), $this->editandoUlid, [
                'componente' => (int) $this->componente, 'titulo' => $this->titulo, 'instrucciones' => $this->instrucciones ?: null, 'abre' => $this->abre, 'cierra' => $this->cierra,
                'peso' => $this->peso, 'minutos' => $this->minutos, 'intentos' => $this->intentos, 'navegacion' => $this->navegacion, 'banco' => (int) $this->banco,
                'cantidad' => $this->cantidad, 'tema' => $this->tema ?: null,
            ]);
            session()->flash('aviso', ['tipo' => 'exito', 'mensaje' => __('Cuestionario actualizado.')]);
            $this->redirectRoute('secciones.cuestionarios.show', ['seccion' => $this->seccion, 'cuestionario' => $this->editandoUlid], navigate: true);

            return;
        }

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
            'editando' => $this->editandoUlid !== null,
            'intentosCount' => $this->intentosCount,
            'computos' => DB::table('grading_components')->where('section_id', $this->seccionId())->orderBy('number')->get(),
            'bancos' => $cuestionarios->bancos(auth()->user()),
            // Los temas del banco elegido: antes era texto libre y una palabra mal escrita dejaba el filtro en 0.
            'temas' => $this->banco === null ? collect() : $cuestionarios->temasDelBanco(auth()->user(), (int) $this->banco),
        ]);
    }
}
