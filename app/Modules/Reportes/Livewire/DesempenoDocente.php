<?php

namespace App\Modules\Reportes\Livewire;

use App\Modules\Reportes\Services\DesempenoService;
use App\Support\Exportacion;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Desempeño docente (RF33, maqueta 27).
 */
#[Layout('layouts::app')]
class DesempenoDocente extends Component
{
    use PaginaLivewire;

    #[Url(except: 'last_name')]
    public string $orden = 'last_name';

    /** Período consultado (RF33); vacío es el período en curso. */
    #[Url(except: null)]
    public ?int $periodo = null;

    /**
     * El panel a Excel, PDF o CSV (RF33, ADR-049). El permiso y el alcance los exige `indicadores()`.
     */
    public function exportar(string $formato, DesempenoService $desempeno): StreamedResponse
    {
        $periodo = $this->periodoElegido($desempeno);
        $columnas = [__('Docente'), __('Departamento'), __('Secciones'), __('Retraso (min)'), __('A tiempo %'), __('Recursos'), __('Revisión (h)'), __('Sin publicar'), __('Notas publicadas %')];
        $filas = $desempeno->indicadores(auth()->user(), $periodo)
            ->map(fn (object $f) => [$f->last_name.', '.$f->first_name, $f->departamento, $f->secciones, $f->retraso, $f->aTiempo, $f->recursos, $f->revisionHoras, $f->sinPublicar, $f->publicadas])
            ->values()->all();
        $nombre = 'desempeno-docente-'.now()->format('Y-m-d');
        $subtitulo = $desempeno->periodos()->firstWhere('id', $periodo)?->name
            ?? $desempeno->periodos()->firstWhere('status', 'active')?->name ?? '';

        return match ($formato) {
            'xlsx' => Exportacion::xlsx($nombre.'.xlsx', $columnas, $filas, titulo: __('Desempeño docente'), subtitulo: $subtitulo),
            'pdf' => Exportacion::pdf($nombre.'.pdf', 'exportaciones.tabla', [
                'titulo' => __('Desempeño docente'),
                'subtitulo' => $subtitulo,
                'columnas' => $columnas,
                'filas' => $filas,
                'desdeColumna' => 2,
            ], orientacion: 'landscape'),
            default => Exportacion::csv($nombre.'.csv', $columnas, $filas),
        };
    }

    /**
     * El período de la URL solo vale si es uno de los consultables; si no, el período en curso.
     */
    private function periodoElegido(DesempenoService $desempeno): ?int
    {
        return $desempeno->periodos()->contains('id', $this->periodo) ? $this->periodo : null;
    }

    public function render(DesempenoService $desempeno): View
    {
        $filas = $desempeno->indicadores(auth()->user(), $this->periodoElegido($desempeno));
        // Más es mejor en a tiempo, recursos y publicadas; menos es mejor en retraso y revisión (sin dato, al final).
        $filas = match (true) {
            in_array($this->orden, ['aTiempo', 'recursos', 'publicadas'], true) => $filas->sortByDesc($this->orden)->values(),
            in_array($this->orden, ['retraso', 'revisionHoras'], true) => $filas->sortBy(fn ($f) => $f->{$this->orden} ?? INF)->values(),
            default => $filas,
        };

        return $this->pagina('modules.reportes.desempeno', [
            'filas' => $filas,
            'periodos' => $desempeno->periodos(),
            'promedios' => [
                'retraso' => $filas->whereNotNull('retraso')->avg('retraso'),
                'recursos' => $filas->avg('recursos'),
                'revision' => $filas->whereNotNull('revisionHoras')->avg('revisionHoras'),
            ],
        ]);
    }
}
