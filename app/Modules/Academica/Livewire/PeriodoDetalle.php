<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\PeriodoService;
use App\Support\Exportacion;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Detalle del período (RF06, RF34): etapas, cómputos, cierres departamentales y reapertura. El calendario de
 * evaluaciones tiene su propia página, porque también lo gestionan el Jefe y el Coordinador (ADR-068).
 */
#[Layout('layouts::app')]
class PeriodoDetalle extends Component
{
    use PaginaLivewire;

    public int $periodoId;

    public string $motivo = '';

    public function mount(int $periodo): void
    {
        $this->periodoId = $periodo;
    }

    /**
     * El histórico del período a Excel, PDF o CSV (RF06, ADR-049). El permiso lo exige `detalle()`.
     */
    public function exportar(string $formato, PeriodoService $periodos): StreamedResponse
    {
        $detalle = $periodos->detalleExportable(auth()->user(), $this->periodoId);
        $nombre = 'periodo-'.Exportacion::nombre($detalle['titulo']);

        return match ($formato) {
            'xlsx' => Exportacion::xlsx($nombre.'.xlsx', $detalle['columnas'], $detalle['filas'], titulo: $detalle['titulo'], subtitulo: $detalle['subtitulo']),
            'pdf' => Exportacion::pdf($nombre.'.pdf', 'exportaciones.tabla', $detalle + ['desdeColumna' => 2]),
            default => Exportacion::csv($nombre.'.csv', $detalle['columnas'], $detalle['filas']),
        };
    }

    public function avanzar(PeriodoService $periodos): void
    {
        $this->intentar(fn () => $periodos->avanzarEtapa(auth()->user(), $this->periodoId), __('Etapa actualizada.'));
    }

    public function activar(PeriodoService $periodos): void
    {
        $this->intentar(fn () => $periodos->activar(auth()->user(), $this->periodoId), __('El período quedó en curso.'));
    }

    public function cerrar(PeriodoService $periodos): void
    {
        $this->intentar(function () use ($periodos): void {
            $congelados = $periodos->cerrar(auth()->user(), $this->periodoId);
            $this->dispatch('aviso', tipo: 'exito', mensaje: __('Período cerrado: :n resultados congelados.', ['n' => $congelados]));
        }, null);
    }

    public function reabrir(PeriodoService $periodos): void
    {
        $this->validate(['motivo' => 'required|string|min:5|max:500'], [], ['motivo' => 'motivo']);
        $this->intentar(fn () => $periodos->reabrir(auth()->user(), $this->periodoId, $this->motivo), __('El período quedó en curso de nuevo.'));
        $this->reset('motivo');
        $this->dispatch('cerrar-modal', 'reabrir-periodo');
    }

    private function intentar(callable $accion, ?string $mensaje): void
    {
        try {
            $accion();
            if ($mensaje !== null) {
                $this->dispatch('aviso', tipo: 'exito', mensaje: $mensaje);
            }
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
        }
    }

    public function render(PeriodoService $periodos): View
    {
        $detalle = $periodos->detalle(auth()->user(), $this->periodoId);

        return $this->pagina('modules.academica.periodo-detalle', $detalle + ['etapas' => PeriodoService::ETAPAS, 'estados' => PeriodoService::ESTADOS],
            titulo: __('Período :nombre', ['nombre' => $detalle['periodo']->name]));
    }
}
