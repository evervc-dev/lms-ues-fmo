<?php

namespace App\Modules\Asistencia\Livewire;

use App\Modules\Asistencia\Services\AsistenciaService;
use App\Modules\Asistencia\Services\ImportacionAsistenciaService;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Sesión de asistencia (RF19, RF28, RF32, maqueta 21): contadores, marcar o corregir, código temporal,
 * rechazos, justificaciones, cierre, solicitud de cancelación e importación desde Excel o CSV.
 */
#[Layout('layouts::app')]
class SesionAsistencia extends Component
{
    use PaginaDeSeccion;
    use WithFileUploads;

    #[Locked]
    public string $sesionUlid = '';

    public ?int $matriculaId = null;

    public string $estado = 'present';

    public string $motivo = '';

    public ?string $codigo = null;

    public string $fechaCancelacion = '';

    public string $tipoCancelacion = 'cancellation';

    public string $motivoCancelacion = '';

    /** @var TemporaryUploadedFile|null Archivo de asistencia a importar (CP-RF19-02). */
    public $archivo = null;

    /**
     * Vista previa de la importación. Bloqueada: al confirmar se vuelve a leer el archivo, no esto.
     *
     * @var array{aplicables: list<array<string, mixed>>, rechazadas: list<array<string, mixed>>}|null
     */
    #[Locked]
    public ?array $previa = null;

    public function mount(string $sesion): void
    {
        $this->sesionUlid = $sesion;
        $this->fechaCancelacion = now()->addDay()->toDateString();
    }

    public function marcarRapido(int $matricula, string $estado, AsistenciaService $asistencia): void
    {
        $asistencia->marcar(auth()->user(), $this->seccionId(), $this->sesionUlid, $matricula, $estado);
    }

    public function abrirCorreccion(int $matricula, string $estado): void
    {
        $this->matriculaId = $matricula;
        $this->estado = $estado;
        $this->motivo = '';
        $this->dispatch('abrir-modal', 'corregir-asistencia');
    }

    public function corregir(AsistenciaService $asistencia): void
    {
        $this->validate(['motivo' => 'required|string|min:5|max:300', 'estado' => 'required|in:present,late,absent,justified'], [], ['motivo' => 'motivo']);
        $asistencia->marcar(auth()->user(), $this->seccionId(), $this->sesionUlid, (int) $this->matriculaId, $this->estado, $this->motivo);
        $this->dispatch('cerrar-modal', 'corregir-asistencia');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Registro corregido.'));
    }

    public function generarCodigo(int $matricula, AsistenciaService $asistencia): void
    {
        try {
            $this->codigo = $asistencia->generarCodigo(auth()->user(), $this->seccionId(), $this->sesionUlid, $matricula);
            $this->dispatch('abrir-modal', 'codigo-temporal');
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
        }
    }

    public function cerrar(AsistenciaService $asistencia): void
    {
        $asistencia->cerrar(auth()->user(), $this->seccionId(), $this->sesionUlid);
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Sesión cerrada: quienes no marcaron quedaron ausentes.'));
    }

    public function revisar(int $justificacion, bool $aceptar, AsistenciaService $asistencia): void
    {
        $asistencia->revisarJustificacion(auth()->user(), $this->seccionId(), $justificacion, $aceptar);
        $this->dispatch('aviso', tipo: 'exito', mensaje: $aceptar ? __('Justificación aceptada.') : __('Justificación rechazada.'));
    }

    public function solicitarCancelacion(AsistenciaService $asistencia): void
    {
        $this->validate(['fechaCancelacion' => 'required|date|after_or_equal:today', 'tipoCancelacion' => 'required|in:cancellation,modality_change', 'motivoCancelacion' => 'required|string|min:5|max:300'],
            [], ['fechaCancelacion' => 'fecha', 'motivoCancelacion' => 'motivo']);
        $asistencia->solicitarCancelacion(auth()->user(), $this->seccionId(), $this->fechaCancelacion, $this->tipoCancelacion, $this->motivoCancelacion, 'virtual');
        $this->reset('motivoCancelacion');
        $this->dispatch('cerrar-modal', 'cancelar-clase');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Solicitud enviada al Coordinador.'));
    }

    public function previsualizar(ImportacionAsistenciaService $importacion): void
    {
        $this->validate(['archivo' => 'required|file|max:2048'], [], ['archivo' => 'archivo']);
        $this->previa = $importacion->previsualizar(auth()->user(), $this->seccionId(), $this->sesionUlid, $this->archivo);
    }

    public function importar(ImportacionAsistenciaService $importacion): void
    {
        $this->validate(['archivo' => 'required|file|max:2048'], [], ['archivo' => 'archivo']);
        $cambios = $importacion->importar(auth()->user(), $this->seccionId(), $this->sesionUlid, $this->archivo);
        $this->reset('archivo', 'previa');
        $this->dispatch('aviso', tipo: 'exito', mensaje: trans_choice('{0} Importado: nadie cambió de estado.|{1} Importado: 1 registro creado o corregido.|[2,*] Importado: :count registros creados o corregidos.', $cambios));
    }

    public function descartarImportacion(): void
    {
        $this->reset('archivo', 'previa');
    }

    public function render(AsistenciaService $asistencia): View
    {
        $datos = $asistencia->sesion(auth()->user(), $this->seccionId(), $this->sesionUlid);

        return $this->paginaDeSeccion('modules.asistencia.sesion', $datos + [
            'justificaciones' => $asistencia->justificacionesPendientes(auth()->user(), $this->seccionId()),
            'estados' => AsistenciaService::ESTADOS,
            'metodos' => AsistenciaService::METODOS,
        ]);
    }
}
