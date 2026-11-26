<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\CargaAcademicaService;
use App\Modules\Academica\Services\PeriodoService;
use App\Support\Alcance;
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
 * Crean grupos el Administrador (departamento → carrera → materia) y el Jefe (carrera → materia de su departamento),
 * con secciones.crear (ADR-074); el Coordinador programa horario, aula y docentes de los de su carrera, sin crearlos.
 */
#[Layout('layouts::app')]
class CargaAcademica extends Component
{
    use PaginaLivewire;

    #[Url(except: '')]
    public string $buscar = '';

    #[Url(except: '')]
    public string $estado = '';

    /** Período que se prepara o se trabaja: el que está en curso o uno planificado (Ciclo de vida del período). */
    #[Url(as: 'periodo')]
    public ?int $periodoId = null;

    public ?int $seccionId = null;

    public ?int $departamentoGrupo = null;

    public ?int $carreraGrupo = null;

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
        return app(CargaAcademicaService::class)->secciones(auth()->user(), $this->periodo(), ['buscar' => $this->buscar, 'estado' => $this->estado]);
    }

    private function periodo(): ?int
    {
        return PeriodoService::preparableOActivo($this->periodoId);
    }

    public function abrir(string $modal, ?int $seccion = null): void
    {
        if ($modal === 'nueva-seccion') {
            $this->prepararNuevoGrupo(app(CargaAcademicaService::class));
        }
        $this->seccionId = $seccion;
        $this->resetValidation();
        $this->dispatch('abrir-modal', $modal);
    }

    /** Al cambiar el departamento se borran la carrera y la materia; si el departamento tiene una sola carrera, se elige sola. */
    public function updatedDepartamentoGrupo(CargaAcademicaService $carga): void
    {
        $this->reset('carreraGrupo', 'asignaturaId');
        $carreras = $this->carrerasDelFormulario($carga);
        if ($carreras->count() === 1) {
            $this->carreraGrupo = (int) $carreras->first()->id;
        }
    }

    /** Al cambiar la carrera se borra la materia elegida. */
    public function updatedCarreraGrupo(): void
    {
        $this->reset('asignaturaId');
    }

    public function crearSeccion(CargaAcademicaService $carga): void
    {
        $this->validate([
            'departamentoGrupo' => $this->eligeDepartamento($carga) ? 'required|integer' : 'nullable',
            'carreraGrupo' => 'required|integer',
            'asignaturaId' => 'required|integer', 'grupo' => 'required|string|max:5', 'cupo' => 'required|integer|min:1|max:200',
        ], [], ['departamentoGrupo' => 'departamento', 'carreraGrupo' => 'carrera', 'asignaturaId' => 'asignatura', 'cupo' => 'cupo']);
        $carga->crearSeccion(auth()->user(), (int) $this->periodo(), (int) $this->asignaturaId, $this->grupo, $this->cupo, carreraId: (int) $this->carreraGrupo);
        $this->terminar('nueva-seccion', __('Grupo teórico creado. Agregue su horario y su docente.'));
    }

    /**
     * Último día (ISO) en que se programa clase: el sábado. ASIS_BYPASS_WEEKEND habilita también el domingo para
     * probar, salvo en producción, igual que en la apertura de la asistencia (ADR-075).
     */
    public static function ultimoDia(): int
    {
        return config('lms.asistencia.bypass_horario') && ! app()->isProduction() ? 7 : 6;
    }

    public function agregarHorario(CargaAcademicaService $carga): void
    {
        $this->validate(['dia' => 'required|integer|between:1,'.self::ultimoDia(), 'inicio' => 'required|date_format:H:i', 'fin' => 'required|date_format:H:i'], [], ['dia' => 'día']);
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
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Horario quitado. Se avisó a los estudiantes y docentes del grupo teórico.'));
    }

    /**
     * Cambia el aula de un bloque; vacío es «virtual / sin aula» (RF08).
     */
    public function cambiarAula(int $bloque, string $aula, CargaAcademicaService $carga): void
    {
        try {
            $carga->cambiarAula(auth()->user(), (int) $this->seccionId, $bloque, $aula === '' ? null : (int) $aula);
            $this->dispatch('aviso', tipo: 'exito', mensaje: __('Aula cambiada. Se avisó a los estudiantes y docentes del grupo teórico.'));
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
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Se quitó al docente del grupo teórico.'));
    }

    private function terminar(string $modal, string $mensaje): void
    {
        unset($this->secciones);
        $this->reset('docenteId', 'aulaId');
        $this->dispatch('cerrar-modal', $modal);
        $this->dispatch('aviso', tipo: 'exito', mensaje: $mensaje);
    }

    /**
     * El Administrador (alcance G) elige el departamento; el Jefe y el Coordinador, no (salvo que tengan varios).
     */
    private function eligeDepartamento(CargaAcademicaService $carga): bool
    {
        return $this->alcanceGrupos() === 'G' || $carga->departamentosParaGrupos(auth()->user())->count() > 1;
    }

    private function alcanceGrupos(): ?string
    {
        return app(Alcance::class)->de(auth()->user(), 'secciones.crear');
    }

    /**
     * Deja el formulario vacío y, si solo hay un departamento o una carrera posibles, los elige solos.
     */
    private function prepararNuevoGrupo(CargaAcademicaService $carga): void
    {
        $this->reset('departamentoGrupo', 'carreraGrupo', 'asignaturaId', 'grupo', 'cupo');
        if ($this->alcanceGrupos() === null) {
            return;
        }
        $departamentos = $carga->departamentosParaGrupos(auth()->user());
        if (! $this->eligeDepartamento($carga) && $departamentos->count() === 1) {
            $this->departamentoGrupo = (int) $departamentos->first()->id;
        }
        $carreras = $this->carrerasDelFormulario($carga);
        if ($carreras->count() === 1) {
            $this->carreraGrupo = (int) $carreras->first()->id;
        }
    }

    /**
     * @return Collection<int, \stdClass>
     */
    private function carrerasDelFormulario(CargaAcademicaService $carga): Collection
    {
        $carreras = $carga->carrerasParaGrupos(auth()->user());

        return $this->departamentoGrupo === null ? ($this->eligeDepartamento($carga) ? collect() : $carreras)
            : $carreras->where('department_id', $this->departamentoGrupo)->values();
    }

    public function render(CargaAcademicaService $carga): View
    {
        $secciones = $this->secciones;
        $actor = auth()->user();
        $gestiona = $actor->checkPermissionTo('secciones.gestionar');
        $crea = $actor->checkPermissionTo('secciones.crear');

        return $this->pagina('modules.academica.carga-academica', [
            'periodo' => DB::table('academic_periods')->where('id', $this->periodo())->value('name'),
            'periodos' => PeriodoService::preparables(),
            'periodoActual' => $this->periodo(),
            'kpi' => [
                'secciones' => $secciones->count(),
                'sinDocente' => $secciones->where('estado', 'sin_docente')->count(),
                'choques' => $secciones->where('estado', 'choque')->count(),
                'ocupacion' => $secciones->sum('max_capacity') ? round(100 * $secciones->sum('inscritos') / $secciones->sum('max_capacity'), 1) : 0,
            ],
            'gestiona' => $gestiona,
            'asignaDocentes' => $actor->checkPermissionTo('secciones.asignar-docente'),
            'cambiaCupo' => $actor->checkPermissionTo('secciones.cupo'),
            'crea' => $crea,
            'eligeDepartamento' => $crea && $this->eligeDepartamento($carga),
            'departamentosGrupo' => $crea ? $carga->departamentosParaGrupos($actor) : collect(),
            'carrerasGrupo' => $crea ? $this->carrerasDelFormulario($carga) : collect(),
            'asignaturas' => $crea && $this->carreraGrupo ? $carga->asignaturasDeCarrera($actor, (int) $this->carreraGrupo) : collect(),
            'docentes' => $carga->docentesDisponibles($this->periodo()),
            'aulas' => DB::table('classrooms')->orderBy('code')->get(['id', 'code', 'capacity']),
            'actual' => $this->seccionId ? $secciones->firstWhere('id', $this->seccionId) : null,
        ]);
    }
}
