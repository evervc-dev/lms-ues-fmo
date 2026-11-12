<?php

namespace App\Modules\Asistencia\Livewire;

use App\Modules\Asistencia\Services\AsistenciaService;
use App\Modules\Asistencia\Services\ReporteMeetService;
use App\Support\Alcance;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Vista de sección › Asistencia › Clases virtuales (RF21, ADR-003): el docente programa la clase, sube el reporte
 * de Meet, revisa la vista previa y confirma; el estudiante ve las clases con su enlace y su grabación.
 */
class ClasesVirtuales extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $seccionId;

    #[Validate('required|string|max:200', as: 'tema', onUpdate: false)]
    public string $tema = '';

    #[Validate('required|date_format:Y-m-d H:i', as: 'inicio', onUpdate: false)]
    public string $inicio = '';

    #[Validate('required|integer|between:15,300', as: 'duración', onUpdate: false)]
    public int $duracion = 60;

    #[Validate('nullable|url|max:500', as: 'enlace de Meet', onUpdate: false)]
    public ?string $meetUrl = null;

    /** @var TemporaryUploadedFile|null */
    #[Validate('required|file|mimes:csv,txt,xlsx|max:5120', as: 'archivo')]
    public $archivo = null;

    /** Clase a la que se sube el reporte. */
    public ?int $claseId = null;

    /** Borrador en vista previa. */
    public ?int $reporteId = null;

    /** @var array<int|string, int|string|null> Matrícula elegida para cada fila sin asociar. */
    public array $asignaciones = [];

    /** @var array<int|string, string|null> Enlace de la grabación de cada clase. */
    #[Validate(['grabaciones.*' => 'nullable|url|max:500'], as: 'grabación', onUpdate: false)]
    public array $grabaciones = [];

    public function mount(int $seccionId): void
    {
        $this->seccionId = $seccionId;
    }

    public function programar(ReporteMeetService $reportes): void
    {
        $this->inicio = str_replace('T', ' ', $this->inicio);
        $this->meetUrl = blank($this->meetUrl) ? null : trim((string) $this->meetUrl);
        $this->validarSolo(['tema', 'inicio', 'duracion', 'meetUrl']);
        $reportes->programar(auth()->user(), $this->seccionId, $this->tema, $this->inicio, $this->duracion, $this->meetUrl);
        $this->reset('tema', 'inicio', 'duracion', 'meetUrl');
        $this->dispatch('cerrar-modal', 'programar-clase');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Clase virtual programada.'));
    }

    public function subir(ReporteMeetService $reportes): void
    {
        $this->validarSolo(['archivo']);
        if ($this->claseId === null || ! $this->archivo instanceof TemporaryUploadedFile) {
            $this->addError('archivo', __('Elija la clase y el archivo del reporte.'));

            return;
        }
        $this->reporteId = $reportes->subir(auth()->user(), $this->seccionId, $this->claseId, $this->archivo);
        $this->reset('archivo', 'asignaciones');
        $this->dispatch('cerrar-modal', 'subir-reporte');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Reporte leído. Revise la vista previa antes de confirmar.'));
    }

    public function asignar(ReporteMeetService $reportes, int $fila, ?int $matricula = null): void
    {
        $matricula ??= filled($this->asignaciones[$fila] ?? null) ? (int) $this->asignaciones[$fila] : null;
        if ($matricula === null) {
            $this->addError('asignaciones.'.$fila, __('Elija un estudiante.'));

            return;
        }
        $reportes->asignar(auth()->user(), $this->seccionId, $fila, $matricula);
        unset($this->asignaciones[$fila]);
    }

    public function descartar(ReporteMeetService $reportes, int $fila): void
    {
        $reportes->descartar(auth()->user(), $this->seccionId, $fila);
        unset($this->asignaciones[$fila]);
    }

    public function confirmar(ReporteMeetService $reportes): void
    {
        if ($this->reporteId === null) {
            return;
        }
        $reportes->confirmar(auth()->user(), $this->seccionId, $this->reporteId);
        $this->reset('reporteId', 'asignaciones');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Asistencia registrada con el reporte de Meet.'));
    }

    public function guardarGrabacion(ReporteMeetService $reportes, int $clase): void
    {
        $this->validarSolo(['grabaciones.*']);
        $reportes->grabacion(auth()->user(), $this->seccionId, $clase, $this->grabaciones[$clase] ?? null);
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Grabación guardada.'));
    }

    public function render(ReporteMeetService $reportes, Alcance $alcance): View
    {
        $usuario = auth()->user();
        $gestiona = $alcance->puedeEnSeccion($usuario, 'videoconferencias.gestionar', $this->seccionId);
        $clases = $reportes->clases($usuario, $this->seccionId);
        foreach ($clases as $c) {
            $this->grabaciones[$c->id] ??= $c->recording_url;
        }

        return view('modules.asistencia.clases-virtuales', [
            'clases' => $clases,
            'gestiona' => $gestiona,
            'seccion' => DB::table('sections')->where('id', $this->seccionId)->value('ulid'),
            'previa' => $gestiona && $this->reporteId !== null ? $reportes->vistaPrevia($usuario, $this->seccionId, $this->reporteId) : null,
            'estados' => ReporteMeetService::ESTADOS,
            'estadosAsistencia' => AsistenciaService::ESTADOS,
            'umbral' => ReporteMeetService::UMBRAL,
        ]);
    }

    /**
     * Valida solo los campos de la acción con sus reglas de #[Validate].
     *
     * @param  list<string>  $campos
     */
    private function validarSolo(array $campos): void
    {
        $this->validate(Arr::only($this->getRules(), $campos));
    }
}
