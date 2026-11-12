<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\MatriculaService;
use App\Modules\Academica\Services\PeriodoService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Mis materias (RF29, RF18, RF09, maqueta 05): las materias inscritas, con su avance y la solicitud de cambio
 * de grupo dentro del plazo. La inscripción con clave está en «Inscripción de materias» (ADR-050).
 */
#[Layout('layouts::app')]
class MisMaterias extends Component
{
    use PaginaLivewire;

    public ?int $matriculaCambio = null;

    /** @var array<string, mixed>|null */
    public ?array $cambio = null;

    public ?int $destino = null;

    public string $justificacion = '';

    public function abrirCambio(MatriculaService $matricula, int $matriculaId): void
    {
        $datos = $matricula->opcionesDeCambio(auth()->user(), $matriculaId);
        $this->reset('destino', 'justificacion');
        $this->resetErrorBag();
        $this->matriculaCambio = $matriculaId;
        $this->cambio = [
            'materia' => $datos['materia']->code.' · '.$datos['materia']->name,
            'plazo' => $datos['plazo']->format('d/m/Y'),
            'abierto' => $datos['abierto'],
            'pendiente' => $datos['pendiente'],
            'secciones' => $datos['secciones']->map(fn ($s) => ['id' => $s->id, 'grupo' => $s->code, 'cupo' => $s->max_capacity - $s->inscritos, 'llena' => $s->llena, 'choque' => $s->choque])->all(),
        ];
        $this->dispatch('abrir-modal', 'cambio-grupo');
    }

    public function solicitarCambio(MatriculaService $matricula): void
    {
        $this->validate(['destino' => 'required|integer', 'justificacion' => 'required|string|min:10|max:1000'], [], ['destino' => 'grupo', 'justificacion' => 'justificación']);
        try {
            $matricula->solicitarCambio(auth()->user(), (int) $this->matriculaCambio, (int) $this->destino, $this->justificacion);
        } catch (ValidationException $e) {
            $this->addError('destino', collect($e->errors())->flatten()->first());

            return;
        }
        $this->reset('matriculaCambio', 'cambio', 'destino', 'justificacion');
        $this->dispatch('cerrar-modal', 'cambio-grupo');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Solicitud enviada: la revisará su coordinador.'));
    }

    public function render(): View
    {
        $usuario = auth()->user();
        $materias = DB::table('enrollments as e')->join('sections as s', 's.id', '=', 'e.section_id')->join('subjects as a', 'a.id', '=', 's.subject_id')
            ->where('e.student_id', $usuario->id)->where('e.status', 'active')->where('e.period_id', PeriodoService::activoId())
            ->select('e.id', 's.ulid', 's.code as grupo', 'a.code', 'a.name',
                DB::raw('(select count(*) from resources r join units u on u.id = r.unit_id where u.section_id = s.id and r.deleted_at is null and u.deleted_at is null) as recursos'),
                DB::raw('(select count(*) from resource_progress p where p.enrollment_id = e.id) as completados'),
                DB::raw("(select users.first_name || ' ' || users.last_name from teaching_assignments t join users on users.id = t.teacher_id where t.section_id = s.id and t.is_responsible = true and t.ended_at is null limit 1) as docente"))
            ->orderBy('a.code')->get();

        return $this->pagina('modules.academica.mis-materias', ['materias' => $materias]);
    }
}
