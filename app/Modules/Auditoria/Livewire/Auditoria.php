<?php

namespace App\Modules\Auditoria\Livewire;

use App\Modules\Auditoria\Services\AuditoriaService;
use App\Support\Exportacion;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Auditoría (RF05, maqueta 28): eventos inmutables con filtros, detalle antes/después y exportación CSV.
 */
#[Layout('layouts::app')]
class Auditoria extends Component
{
    use PaginaLivewire;
    use WithPagination;

    #[Url(except: '')]
    public string $desde = '';

    #[Url(except: '')]
    public string $hasta = '';

    #[Url(except: '')]
    public string $accion = '';

    #[Url(except: '')]
    public string $entidad = '';

    #[Url(except: '')]
    public string $buscar = '';

    public ?int $detalle = null;

    public function updating(string $propiedad): void
    {
        if ($propiedad !== 'detalle') {
            $this->resetPage();
        }
    }

    #[Computed]
    public function eventos(): Paginator
    {
        return app(AuditoriaService::class)->listar(auth()->user(), [
            'desde' => $this->desde, 'hasta' => $this->hasta, 'accion' => $this->accion, 'entidad' => $this->entidad, 'buscar' => $this->buscar,
        ]);
    }

    public function ver(int $evento): void
    {
        $this->detalle = $evento;
        $this->dispatch('abrir-modal', 'detalle-auditoria');
    }

    /**
     * Los eventos filtrados a Excel o CSV (CP-RF05-02, ADR-049). Sin PDF: la exportación llega a 10 000
     * filas y los dos formatos se escriben en streaming, mientras que dompdf las armaría todas en memoria.
     */
    public function exportar(string $formato, AuditoriaService $auditoria): StreamedResponse
    {
        $reporte = $auditoria->exportar(auth()->user(), [
            'desde' => $this->desde, 'hasta' => $this->hasta, 'accion' => $this->accion, 'entidad' => $this->entidad, 'buscar' => $this->buscar,
        ]);
        $nombre = 'auditoria-'.now()->format('Y-m-d');

        return $formato === 'xlsx'
            ? Exportacion::xlsx($nombre.'.xlsx', $reporte['columnas'], $reporte['filas'], titulo: __('Auditoría'), subtitulo: Exportacion::filtros([
                __('Desde') => $this->desde, __('Hasta') => $this->hasta, __('Búsqueda') => $this->buscar,
                __('Acción') => ['insert' => __('Creación'), 'update' => __('Cambio'), 'delete' => __('Eliminación')][$this->accion] ?? null,
                __('Sobre') => $this->entidad === '' ? null : AuditoriaService::entidad($this->entidad),
            ]))
            : Exportacion::csv($nombre.'.csv', $reporte['columnas'], $reporte['filas']);
    }

    public function render(AuditoriaService $auditoria): View
    {
        $global = auth()->user()->hasRole('administrador');

        return $this->pagina('modules.auditoria.auditoria', [
            'entidades' => $auditoria->entidades(auth()->user()),
            'evento' => $this->detalle ? $this->eventos->getCollection()->firstWhere('id', $this->detalle) : null,
            'subtitulo' => $global ? __('Todos los eventos del sistema.') : __('Solo los eventos de su departamento (ADR-029).'),
        ]);
    }
}
