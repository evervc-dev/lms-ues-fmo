<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\CargaAcademicaService;
use App\Modules\Academica\Services\PeriodoService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Carga académica (RF08, maquetas 23 y 24): secciones, horarios, aulas y docentes, sin choques.
 * Quien gestiona secciones crea y programa; el Jefe (ADR-037) solo asigna docentes y cambia el cupo en su departamento.
 */
#[Layout('layouts::app')]
class CargaAcademica extends Component
{
    use PaginaLivewire;

    #[Url(except: '')]
    public string $buscar = '';

    #[Url(except: '')]
    public string $estado = '';

    public ?int $seccionId = null;

    public ?int $asignaturaId = null;

    public string $grupo = '01';

    public int $cupo = 40;

    public int $dia = 1;

    public string $inicio = '07:00';

    public string $fin = '08:40';

    public ?int $aulaId = null;

    public ?int $docenteId = null;

    public bool $responsable = true;

    public int $cupoNuevo = 40;

    #[Computed]
    public function secciones(): Collection
    {
        return app(CargaAcademicaService::class)->secciones(auth()->user(), PeriodoService::activoId(), ['buscar' => $this->buscar, 'estado' => $this->estado]);
    }

    public function abrir(string $modal, ?int $seccion = null): void
    {
        $this->seccionId = $seccion;
        $this->resetValidation();
        $this->dispatch('abrir-modal', $modal);
    }

    public function crearSeccion(CargaAcademicaService $carga): void
    {
        $this->validate(['asignaturaId' => 'required|integer', 'grupo' => 'required|string|max:5', 'cupo' => 'required|integer|min:1|max:200'], [], ['asignaturaId' => 'asignatura', 'cupo' => 'cupo']);
        $carga->crearSeccion(auth()->user(), (int) PeriodoService::activoId(), (int) $this->asignaturaId, $this->grupo, $this->cupo);
        $this->terminar('nueva-seccion', __('Sección creada. Agregue su horario y su docente.'));
    }

    public function agregarHorario(CargaAcademicaService $carga): void
    {
        $this->validate(['dia' => 'required|integer|between:1,6', 'inicio' => 'required|date_format:H:i', 'fin' => 'required|date_format:H:i'], [], ['dia' => 'día']);
        $carga->agregarHorario(auth()->user(), (int) $this->seccionId, $this->dia, $this->inicio, $this->fin, $this->aulaId);
        $this->terminar('horario', __('Horario agregado.'));
    }

    /**
     * Quita un bloque del horario de la sección abierta en el modal (RF08).
     */
    public function quitarHorario(int $bloque, CargaAcademicaService $carga): void
    {
        $carga->quitarHorario(auth()->user(), (int) $this->seccionId, $bloque);
        unset($this->secciones);
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Horario quitado. Se avisó a los estudiantes y docentes de la sección.'));
    }

    /**
     * Cambia el aula de un bloque; vacío es «virtual / sin aula» (RF08).
     */
    public function cambiarAula(int $bloque, string $aula, CargaAcademicaService $carga): void
    {
        try {
            $carga->cambiarAula(auth()->user(), (int) $this->seccionId, $bloque, $aula === '' ? null : (int) $aula);
            $this->dispatch('aviso', tipo: 'exito', mensaje: __('Aula cambiada. Se avisó a los estudiantes y docentes de la sección.'));
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
        }
        unset($this->secciones);
    }

    public function asignarDocente(CargaAcademicaService $carga): void
    {
        $this->validate(['docenteId' => 'required|integer'], [], ['docenteId' => 'docente']);
        $carga->asignarDocente(auth()->user(), (int) $this->seccionId, (int) $this->docenteId, $this->responsable);
        $this->terminar('docente', __('Docente asignado.'));
    }

    public function abrirCupo(int $seccion): void
    {
        $this->cupoNuevo = (int) ($this->secciones->firstWhere('id', $seccion)->max_capacity ?? 0);
        $this->abrir('cupo', $seccion);
    }

    public function cambiarCupo(CargaAcademicaService $carga): void
    {
        $this->validate(['cupoNuevo' => 'required|integer|min:1|max:200'], [], ['cupoNuevo' => 'cupo']);
        try {
            $carga->cambiarCupo(auth()->user(), (int) $this->seccionId, $this->cupoNuevo);
        } catch (ValidationException $excepcion) {
            throw ValidationException::withMessages(['cupoNuevo' => $excepcion->errors()['cupo'] ?? collect($excepcion->errors())->flatten()->all()]);
        }
        $this->terminar('cupo', __('Cupo actualizado.'));
    }

    public function quitarDocente(int $seccion, int $docente, CargaAcademicaService $carga): void
    {
        $carga->quitarDocente(auth()->user(), $seccion, $docente);
        unset($this->secciones);
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Se quitó al docente de la sección.'));
    }

    private function terminar(string $modal, string $mensaje): void
    {
        unset($this->secciones);
        $this->reset('docenteId', 'aulaId');
        $this->dispatch('cerrar-modal', $modal);
        $this->dispatch('aviso', tipo: 'exito', mensaje: $mensaje);
    }

    public function render(CargaAcademicaService $carga): View
    {
        $secciones = $this->secciones;
        $actor = auth()->user();
        $gestiona = $actor->checkPermissionTo('secciones.gestionar');

        return $this->pagina('modules.academica.carga-academica', [
            'periodo' => DB::table('academic_periods')->where('id', PeriodoService::activoId())->value('name'),
            'kpi' => [
                'secciones' => $secciones->count(),
                'sinDocente' => $secciones->where('estado', 'sin_docente')->count(),
                'choques' => $secciones->where('estado', 'choque')->count(),
                'ocupacion' => $secciones->sum('max_capacity') ? round(100 * $secciones->sum('inscritos') / $secciones->sum('max_capacity'), 1) : 0,
            ],
            'gestiona' => $gestiona,
            'asignaDocentes' => $actor->checkPermissionTo('secciones.asignar-docente'),
            'cambiaCupo' => $actor->checkPermissionTo('secciones.cupo'),
            'asignaturas' => $gestiona ? $carga->asignaturas($actor) : collect(),
            'docentes' => $carga->docentesDisponibles(PeriodoService::activoId()),
            'aulas' => DB::table('classrooms')->orderBy('code')->get(['id', 'code', 'capacity']),
            'actual' => $this->seccionId ? $secciones->firstWhere('id', $this->seccionId) : null,
        ]);
    }
}
