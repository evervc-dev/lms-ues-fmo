<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\MatriculaService;
use App\Modules\Academica\Services\PeriodoService;
use App\Modules\Identidad\Services\PerfilContextualService;
use App\Support\Alcance;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Matrícula · Estudiantes (RF09, maqueta 35): inscribir y retirar con motivo, según el alcance.
 */
#[Layout('layouts::app')]
class Matricula extends Component
{
    use PaginaLivewire;
    use WithPagination;

    #[Url(except: '')]
    public string $buscar = '';

    public string $estudianteBuscar = '';

    public ?int $estudianteId = null;

    public ?int $seccionId = null;

    public ?int $retirarId = null;

    public string $motivo = '';

    public function updatingBuscar(): void
    {
        $this->resetPage();
    }

    public function inscribir(MatriculaService $matricula): void
    {
        $this->validate(['estudianteId' => 'required|integer', 'seccionId' => 'required|integer'], [], ['estudianteId' => 'estudiante', 'seccionId' => 'sección']);
        $matricula->inscribir(auth()->user(), (int) $this->estudianteId, (int) $this->seccionId);
        $this->reset('estudianteBuscar', 'estudianteId', 'seccionId');
        $this->dispatch('cerrar-modal', 'inscribir');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Estudiante inscrito.'));
    }

    public function confirmarRetiro(int $matricula): void
    {
        $this->retirarId = $matricula;
        $this->motivo = '';
        $this->dispatch('abrir-modal', 'retirar');
    }

    public function retirar(MatriculaService $matricula): void
    {
        $this->validate(['motivo' => 'required|string|min:5|max:300'], [], ['motivo' => 'motivo']);
        $matricula->retirar(auth()->user(), (int) $this->retirarId, $this->motivo);
        $this->reset('retirarId', 'motivo');
        $this->dispatch('cerrar-modal', 'retirar');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('La matrícula quedó retirada.'));
    }

    public function render(MatriculaService $matricula, Alcance $alcance, PerfilContextualService $perfiles): View
    {
        $usuario = auth()->user();
        $termino = mb_strtolower(trim($this->estudianteBuscar));
        $candidatos = mb_strlen($termino) < 2 ? collect() : $alcance->estudiantes(DB::table('users')->join('student_careers', 'student_careers.student_id', '=', 'users.id')
            ->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'users.id')->where('student_careers.status', 'active')
            ->where(fn ($q) => $q->whereRaw('lower(users.institutional_email) like ?', ["%{$termino}%"])->orWhereRaw('lower(student_profiles.student_code) like ?', ["%{$termino}%"])
                ->orWhereRaw("lower(users.first_name || ' ' || users.last_name) like ?", ["%{$termino}%"])), $usuario, 'matricula.gestionar')
            ->limit(8)->get(['users.id', 'users.first_name', 'users.last_name', 'student_profiles.student_code']);
        $secciones = $alcance->secciones(DB::table('sections')->join('subjects', 'subjects.id', '=', 'sections.subject_id')
            ->where('sections.period_id', PeriodoService::activoId())->where('sections.status', 'open')
            // CP-RF09-14: elegido el estudiante, solo las secciones de asignaturas del plan de su carrera.
            ->when($this->estudianteId, fn ($q, $id) => $q->whereIn('sections.subject_id', DB::table('career_subjects')
                ->join('student_careers', 'student_careers.study_plan_id', '=', 'career_subjects.study_plan_id')
                ->where('student_careers.student_id', $id)->where('student_careers.status', 'active')->select('career_subjects.subject_id'))), $usuario, 'matricula.gestionar')
            ->orderBy('subjects.code')->orderBy('sections.code')->get(['sections.id', 'subjects.code', 'subjects.name', 'sections.code as grupo']);

        $matriculas = $matricula->matriculas($usuario, ['buscar' => $this->buscar]);

        return $this->pagina('modules.academica.matricula', [
            'matriculas' => $matriculas,
            // RF36: el nombre enlaza al perfil solo donde hay vínculo (al Coordinador, los de su carrera).
            'conPerfil' => $usuario->checkPermissionTo('perfiles.ver')
                ? $perfiles->visibles($usuario, collect($matriculas->items())->pluck('estudiante_id')->map(fn ($id) => (int) $id)->unique()->values()->all()) : [],
            'candidatos' => $candidatos,
            'secciones' => $secciones,
            'puedeRetirar' => $alcance->de($usuario, 'matricula.gestionar') !== 'S',
            'pestanas' => $this->pestanasMatricula(),
        ]);
    }

    /**
     * @return array<int, array{etiqueta: string, url: string, activa: bool}>
     */
    private function pestanasMatricula(): array
    {
        $usuario = auth()->user();

        return array_values(array_filter([
            ['etiqueta' => __('Estudiantes'), 'url' => route('matricula'), 'activa' => true],
            $usuario->checkPermissionTo('matricula.claves.gestionar') ? ['etiqueta' => __('Claves por asignatura'), 'url' => route('matricula.claves'), 'activa' => false] : null,
            ['etiqueta' => __('Solicitudes de cambio de grupo'), 'url' => route('matricula.solicitudes'), 'activa' => false],
            $usuario->hasAnyPermission(['matricula.plazo.gestionar', 'matricula.prorroga.otorgar']) ? ['etiqueta' => __('Plazos'), 'url' => route('matricula.plazos'), 'activa' => false] : null,
        ]));
    }
}
