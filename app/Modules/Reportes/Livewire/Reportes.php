<?php

namespace App\Modules\Reportes\Livewire;

use App\Modules\Reportes\Services\ReporteService;
use App\Support\Alcance;
use App\Support\Exportacion;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reportes (RF25, maqueta 47): vista previa con gráfico, filtros de período y carrera, exportación y programación
 * por correo.
 */
#[Layout('layouts::app')]
class Reportes extends Component
{
    use PaginaLivewire;

    #[Url(except: 'asistencia')]
    public string $tipo = 'asistencia';

    /** Período consultado (R-12); vacío es el período en curso. */
    #[Url(except: null)]
    public ?int $periodo = null;

    /** Carrera por la que se filtra; vacío son todas las de su alcance. */
    #[Url(except: null)]
    public ?int $carrera = null;

    public string $frecuencia = 'weekly';

    /**
     * El reporte a Excel, PDF o CSV (RF25, ADR-049). El permiso y el alcance los exige `generar()`.
     */
    public function exportar(string $formato, ReporteService $reportes): StreamedResponse
    {
        $reporte = $reportes->generar(auth()->user(), $this->tipo, $this->periodo, $this->carrera);
        $titulo = __(ReporteService::TIPOS[$this->tipo]);
        $nombre = "reporte-{$this->tipo}-".now()->format('Y-m-d');
        $subtitulo = $this->subtitulo($reporte);

        return match ($formato) {
            'xlsx' => Exportacion::xlsx($nombre.'.xlsx', $reporte['columnas'], $reporte['filas'], titulo: $titulo, subtitulo: $subtitulo),
            'pdf' => Exportacion::pdf($nombre.'.pdf', 'exportaciones.tabla', [
                'titulo' => $titulo,
                'subtitulo' => $subtitulo,
                'columnas' => $reporte['columnas'],
                'filas' => $reporte['filas'],
                // Desde qué columna van números, alineados a la derecha.
                'desdeColumna' => ['asistencia' => 3, 'notas' => 3, 'carga_docente' => 1][$this->tipo] ?? count($reporte['columnas']),
            ], orientacion: 'landscape'),
            default => Exportacion::csv($nombre.'.csv', $reporte['columnas'], $reporte['filas']),
        };
    }

    /**
     * «Período 2026-II · Carrera ISI», para la cabecera de la exportación.
     *
     * @param  array{periodo: ?\stdClass, carrera: ?int}  $reporte
     */
    private function subtitulo(array $reporte): string
    {
        $carrera = $reporte['carrera'] === null ? __('Todas las carreras de su alcance')
            : __('Carrera :c', ['c' => app(Alcance::class)->carrerasVisibles(auth()->user(), 'reportes.ver')->firstWhere('id', $reporte['carrera'])?->code]);

        return __('Período :p', ['p' => $reporte['periodo']->name ?? '—']).' · '.$carrera;
    }

    public function programar(ReporteService $reportes): void
    {
        $reportes->programar(auth()->user(), $this->tipo, $this->frecuencia);
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Reporte programado: le llegará por correo.'));
    }

    public function cancelar(int $id, ReporteService $reportes): void
    {
        $reportes->cancelarProgramado(auth()->user(), $id);
    }

    public function render(ReporteService $reportes): View
    {
        $reporte = $reportes->generar(auth()->user(), $this->tipo, $this->periodo, $this->carrera);
        // El servicio descarta un período o una carrera fuera de lo consultable: los filtros vuelven a su valor por defecto.
        $this->periodo = $reporte['periodo']?->status === 'closed' ? (int) $reporte['periodo']->id : null;
        $this->carrera = $reporte['carrera'];

        return $this->pagina('modules.reportes.reportes', [
            'tipos' => ReporteService::TIPOS,
            'reporte' => $reporte,
            'grafico' => ReporteService::grafico($this->tipo, $reporte['filas']),
            'periodos' => $reportes->periodos(),
            'carreras' => app(Alcance::class)->carrerasVisibles(auth()->user(), 'reportes.ver'),
            'programados' => $reportes->programados(auth()->user()),
            'programa' => auth()->user()->checkPermissionTo('reportes.programar'),
        ]);
    }
}
