<?php

namespace App\Modules\Asistencia\Livewire;

use App\Modules\Asistencia\Services\AsistenciaService;
use App\Support\Alcance;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Vista de sección › Asistencia (RF19, RF21, RF22): sesiones del docente o historial del estudiante, y las clases virtuales.
 */
#[Layout('layouts::app')]
class AsistenciaSeccion extends Component
{
    use PaginaDeSeccion;
    use WithFileUploads;

    public int $minutos = 10;

    public string $modalidad = 'in_person';

    /** Motivo para abrir fuera del horario del grupo teórico (ADR-075). */
    public string $motivoFueraHorario = '';

    public ?int $registroId = null;

    public string $motivoJustificacion = '';

    /** @var TemporaryUploadedFile|null */
    public $documento = null;

    public function abrir(AsistenciaService $asistencia): void
    {
        $this->validate(['minutos' => 'required|integer|between:5,15', 'modalidad' => 'required|in:in_person,virtual', 'motivoFueraHorario' => 'nullable|string|min:5|max:300'],
            [], ['minutos' => 'vigencia', 'motivoFueraHorario' => 'motivo']);
        $ulid = $asistencia->abrir(auth()->user(), $this->seccionId(), $this->minutos, $this->modalidad, $this->motivoFueraHorario ?: null);
        $this->redirectRoute('secciones.asistencia.sesion', ['seccion' => $this->seccion, 'sesion' => $ulid], navigate: true);
    }

    public function pedirJustificacion(int $registro): void
    {
        $this->registroId = $registro;
        $this->reset('motivoJustificacion', 'documento');
        $this->dispatch('abrir-modal', 'justificar');
    }

    public function justificar(AsistenciaService $asistencia): void
    {
        $this->validate(['motivoJustificacion' => 'required|string|min:5|max:500', 'documento' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120'],
            [], ['motivoJustificacion' => 'motivo', 'documento' => 'documento']);
        $asistencia->justificar(auth()->user(), (int) $this->registroId, $this->motivoJustificacion, $this->documento);
        $this->dispatch('cerrar-modal', 'justificar');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Justificación enviada a su docente.'));
    }

    /**
     * Acepta o rechaza una justificación desde el panel de la sección (RF19).
     */
    public function revisar(int $justificacion, bool $aceptar, AsistenciaService $asistencia): void
    {
        $asistencia->revisarJustificacion(auth()->user(), $this->seccionId(), $justificacion, $aceptar);
        $this->dispatch('aviso', tipo: 'exito', mensaje: $aceptar ? __('Justificación aceptada.') : __('Justificación rechazada.'));
    }

    public function render(AsistenciaService $asistencia, Alcance $alcance): View
    {
        $usuario = auth()->user();
        $seccion = $this->seccionId();
        $esEstudiante = $alcance->de($usuario, 'asistencia.ver') === 'P';
        $corrige = ! $esEstudiante && $alcance->puedeEnSeccion($usuario, 'asistencia.corregir', $seccion);
        $modalidadHoy = $esEstudiante ? null : $asistencia->modalidadAprobada($seccion, now()->toDateString());
        if ($modalidadHoy !== null) {
            $this->modalidad = $modalidadHoy;
        }

        $abre = $alcance->puedeEnSeccion($usuario, 'asistencia.sesion.abrir', $seccion);

        return $this->paginaDeSeccion('modules.asistencia.asistencia-seccion', [
            'seccionId' => $seccion,
            'esEstudiante' => $esEstudiante,
            'historial' => $esEstudiante ? $asistencia->historial($usuario, $seccion) : null,
            'sesiones' => $esEstudiante ? collect() : $asistencia->sesiones($usuario, $seccion),
            'abre' => $abre,
            'fueraDeHorario' => $abre && ! $asistencia->dentroDelHorario($seccion),
            'periodoActivo' => AsistenciaService::periodoActivo($seccion),
            'corrige' => $corrige,
            'justificaciones' => $corrige ? $asistencia->justificacionesPendientes($usuario, $seccion) : collect(),
            'modalidadHoy' => $modalidadHoy,
            'estados' => AsistenciaService::ESTADOS,
            'metodos' => AsistenciaService::METODOS,
        ]);
    }
}
