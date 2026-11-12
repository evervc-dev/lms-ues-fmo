<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\MatriculaService;
use App\Support\Alcance;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Plazos de matrícula (RF29, ADR-051): fecha límite de inscripción por departamento (Administrador G, Jefe D,
 * Coordinador C), prórroga del Jefe al departamento y prórrogas por estudiante (Jefe D, Coordinador C).
 * Las reglas y el alcance los aplica MatriculaService.
 */
#[Layout('layouts::app')]
class PlazosMatricula extends Component
{
    use PaginaLivewire;

    public ?int $departamentoId = null;

    public string $fecha = '';

    public string $hasta = '';

    public string $motivo = '';

    public string $estudianteBuscar = '';

    public ?int $estudianteId = null;

    public string $hastaEstudiante = '';

    public string $motivoEstudiante = '';

    #[Url(except: '')]
    public string $buscar = '';

    public function cambiarFecha(int $departamento, MatriculaService $matricula): void
    {
        $plazo = $matricula->plazos(auth()->user())->firstWhere('id', $departamento);
        $this->departamentoId = $departamento;
        $this->fecha = $plazo ? ($plazo->deadline ?? $plazo->por_defecto)->toDateString() : '';
        $this->resetValidation();
        $this->dispatch('abrir-modal', 'fecha-departamento');
    }

    public function guardarFecha(MatriculaService $matricula): void
    {
        $this->validate(['departamentoId' => 'required|integer', 'fecha' => 'required|date'], [], ['departamentoId' => 'departamento', 'fecha' => 'fecha límite']);
        $matricula->fijarPlazo(auth()->user(), (int) $this->departamentoId, $this->fecha);
        $this->reset('departamentoId', 'fecha');
        $this->dispatch('cerrar-modal', 'fecha-departamento');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Se guardó la fecha límite de inscripción.'));
    }

    public function prorrogar(int $departamento): void
    {
        $this->departamentoId = $departamento;
        $this->reset('hasta', 'motivo');
        $this->resetValidation();
        $this->dispatch('abrir-modal', 'prorroga-departamento');
    }

    public function guardarProrroga(MatriculaService $matricula): void
    {
        $this->validate([
            'departamentoId' => 'required|integer',
            'hasta' => 'required|date',
            'motivo' => 'required|string|min:5|max:500',
        ], [], ['departamentoId' => 'departamento', 'hasta' => 'nueva fecha', 'motivo' => 'motivo']);
        $matricula->prorrogarDepartamento(auth()->user(), (int) $this->departamentoId, $this->hasta, $this->motivo);
        $this->reset('departamentoId', 'hasta', 'motivo');
        $this->dispatch('cerrar-modal', 'prorroga-departamento');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('La inscripción del departamento quedó prorrogada.'));
    }

    public function otorgarProrroga(MatriculaService $matricula): void
    {
        $this->validate([
            'estudianteId' => 'required|integer',
            'hastaEstudiante' => 'required|date|after_or_equal:today',
            'motivoEstudiante' => 'required|string|min:5|max:500',
        ], [], ['estudianteId' => 'estudiante', 'hastaEstudiante' => 'fecha', 'motivoEstudiante' => 'motivo']);
        try {
            $matricula->prorrogarEstudiante(auth()->user(), (int) $this->estudianteId, $this->hastaEstudiante, $this->motivoEstudiante);
        } catch (ValidationException $e) {
            $campos = ['hasta' => 'hastaEstudiante', 'motivo' => 'motivoEstudiante'];
            foreach ($e->errors() as $campo => $mensajes) {
                $this->addError($campos[$campo] ?? 'estudianteId', $mensajes[0]);
            }

            return;
        }
        $this->reset('estudianteBuscar', 'estudianteId', 'hastaEstudiante', 'motivoEstudiante');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Se otorgó la prórroga al estudiante.'));
    }

    public function limpiar(): void
    {
        $this->reset('buscar');
    }

    public function render(MatriculaService $matricula, Alcance $alcance): View
    {
        $usuario = auth()->user();
        $gestiona = $usuario->checkPermissionTo('matricula.plazo.gestionar');
        $otorga = $usuario->checkPermissionTo('matricula.prorroga.otorgar');
        $termino = mb_strtolower(trim($this->estudianteBuscar));
        $candidatos = ! $otorga || mb_strlen($termino) < 2 ? collect() : $alcance->estudiantes(DB::table('users')->join('student_careers', 'student_careers.student_id', '=', 'users.id')
            ->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'users.id')->where('student_careers.status', 'active')
            ->where(fn ($q) => $q->whereRaw('lower(users.institutional_email) like ?', ["%{$termino}%"])->orWhereRaw('lower(student_profiles.student_code) like ?', ["%{$termino}%"])
                ->orWhereRaw("lower(users.first_name || ' ' || users.last_name) like ?", ["%{$termino}%"])), $usuario, 'matricula.prorroga.otorgar')
            ->limit(8)->get(['users.id', 'users.first_name', 'users.last_name', 'users.institutional_email', 'student_profiles.student_code']);

        return $this->pagina('modules.academica.plazos', [
            'gestiona' => $gestiona,
            'otorga' => $otorga,
            'puedeProrrogar' => $usuario->checkPermissionTo('matricula.plazo.prorrogar'),
            'plazos' => $gestiona ? $matricula->plazos($usuario) : collect(),
            'prorrogas' => $otorga ? $matricula->prorrogas($usuario, $this->buscar) : collect(),
            'candidatos' => $candidatos,
            'pestanas' => array_values(array_filter([
                ['etiqueta' => __('Estudiantes'), 'url' => route('matricula'), 'activa' => false],
                $usuario->checkPermissionTo('matricula.claves.gestionar') ? ['etiqueta' => __('Claves por asignatura'), 'url' => route('matricula.claves'), 'activa' => false] : null,
                ['etiqueta' => __('Solicitudes de cambio de grupo'), 'url' => route('matricula.solicitudes'), 'activa' => false],
                ['etiqueta' => __('Plazos'), 'url' => route('matricula.plazos'), 'activa' => true],
            ])),
        ]);
    }
}
