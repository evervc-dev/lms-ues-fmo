<?php

namespace App\Modules\Calificaciones\Livewire;

use App\Modules\Calificaciones\Services\CalificacionService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Actas por validar (RF34, maqueta 25): el Coordinador valida o devuelve con observaciones.
 */
#[Layout('layouts::app')]
class ActasPorValidar extends Component
{
    use PaginaLivewire;

    #[Url(except: 'published')]
    public string $estado = 'published';

    /** Estados que ofrece el selector; cualquier otro valor de la URL cae en «por validar». */
    private const ESTADOS = ['published', 'validated', 'returned'];

    public ?int $devolverId = null;

    public string $motivo = '';

    public function validar(int $seccion, CalificacionService $calificaciones): void
    {
        try {
            $calificaciones->validarActa(auth()->user(), $seccion);
            $this->dispatch('aviso', tipo: 'exito', mensaje: __('Acta validada.'));
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
        }
    }

    public function confirmarDevolucion(int $seccion): void
    {
        $this->devolverId = $seccion;
        $this->motivo = '';
        $this->dispatch('abrir-modal', 'devolver-acta');
    }

    public function devolver(CalificacionService $calificaciones): void
    {
        $this->validate(['motivo' => 'required|string|min:5|max:500'], [], ['motivo' => 'observaciones']);
        try {
            $calificaciones->devolverActa(auth()->user(), (int) $this->devolverId, $this->motivo);
            $this->dispatch('aviso', tipo: 'exito', mensaje: __('Acta devuelta al docente.'));
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
        }
        $this->dispatch('cerrar-modal', 'devolver-acta');
    }

    public function render(CalificacionService $calificaciones): View
    {
        $actas = $calificaciones->actasPorValidar(auth()->user(), in_array($this->estado, self::ESTADOS, true) ? $this->estado : 'published');
        $minima = CalificacionService::notaMinima();
        $actas->each(function ($a) use ($calificaciones, $minima) {
            $filas = $calificaciones->libro(auth()->user(), (int) $a->section_id)['filas'];
            $a->aprobados = $filas->where('final', '>=', $minima)->count();
            $a->promedio = $filas->isEmpty() ? null : round($filas->avg('final'), 1);
        });

        return $this->pagina('modules.calificaciones.actas', ['actas' => $actas, 'estados' => ['published' => __('Por validar'), 'validated' => __('Validadas'), 'returned' => __('Devueltas')]]);
    }
}
