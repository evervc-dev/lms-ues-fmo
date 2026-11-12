<?php

namespace App\Modules\Asistencia\Livewire;

use App\Modules\Asistencia\Services\DiagnosticoService;
use App\Support\Exportacion;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Diagnóstico de asistencia (RF20, maqueta 31). Solo lectura, con filtros de fecha y motivo y exportación.
 */
#[Layout('layouts::app')]
class DiagnosticoAsistencia extends Component
{
    use PaginaLivewire;

    #[Url(except: '')]
    public string $buscar = '';

    #[Url(except: '')]
    public string $desde = '';

    #[Url(except: '')]
    public string $hasta = '';

    #[Url(except: DiagnosticoService::SOLO_RECHAZOS)]
    public string $motivo = DiagnosticoService::SOLO_RECHAZOS;

    /**
     * @return array{buscar: string, desde: string, hasta: string, motivo: string}
     */
    private function filtros(): array
    {
        return ['buscar' => $this->buscar, 'desde' => $this->desde, 'hasta' => $this->hasta, 'motivo' => $this->motivo];
    }

    public function hoy(): void
    {
        $this->desde = $this->hasta = now()->toDateString();
    }

    public function limpiar(): void
    {
        $this->reset('buscar', 'desde', 'hasta', 'motivo');
    }

    /**
     * Los intentos filtrados a Excel o CSV (ADR-049). Sin PDF: la exportación llega a 10 000 filas y los dos
     * formatos se escriben en streaming, mientras que dompdf las armaría todas en memoria.
     */
    public function exportar(string $formato, DiagnosticoService $diagnostico): StreamedResponse
    {
        $reporte = $diagnostico->exportar(auth()->user(), $this->filtros());
        $nombre = 'diagnostico-asistencia-'.now()->format('Y-m-d');

        return $formato === 'xlsx'
            ? Exportacion::xlsx($nombre.'.xlsx', $reporte['columnas'], $reporte['filas'], titulo: __('Diagnóstico de asistencia'), subtitulo: Exportacion::filtros([
                __('Desde') => $this->desde, __('Hasta') => $this->hasta, __('Búsqueda') => $this->buscar,
                __('Resultado') => match ($this->motivo) {
                    '' => null,
                    DiagnosticoService::SOLO_RECHAZOS => __('Solo los rechazos'),
                    default => __(DiagnosticoService::MOTIVOS[$this->motivo] ?? $this->motivo),
                },
            ]))
            : Exportacion::csv($nombre.'.csv', $reporte['columnas'], $reporte['filas']);
    }

    public function render(DiagnosticoService $diagnostico): View
    {
        return $this->pagina('modules.asistencia.diagnostico', [
            'intentos' => $diagnostico->intentos(auth()->user(), $this->filtros()),
            'hoy' => $diagnostico->resumenDeHoy(auth()->user()),
            'motivos' => DiagnosticoService::MOTIVOS,
            'conRango' => $this->desde !== '' || $this->hasta !== '',
        ]);
    }
}
