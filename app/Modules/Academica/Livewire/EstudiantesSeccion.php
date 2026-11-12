<?php

namespace App\Modules\Academica\Livewire;

use App\Modules\Academica\Services\MatriculaService;
use App\Modules\Academica\Services\SeccionService;
use App\Support\Alcance;
use App\Support\Navegacion\PaginaDeSeccion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Vista de sección › Estudiantes (RF09, maqueta 38): lista con asistencia, promedio y alertas;
 * inscribir (Docente, Coordinador, Jefe) y retirar con motivo (Coordinador, Jefe, Administrador).
 */
#[Layout('layouts::app')]
class EstudiantesSeccion extends Component
{
    use PaginaDeSeccion;

    public string $carne = '';

    public ?int $retirarId = null;

    public string $motivo = '';

    public function inscribir(MatriculaService $matricula): void
    {
        $this->validate(['carne' => 'required|string|max:120'], [], ['carne' => 'carné o correo']);
        $termino = mb_strtolower(trim($this->carne));
        $estudiante = DB::table('users')->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->where(fn ($q) => $q->whereRaw('lower(users.institutional_email) = ?', [$termino])->orWhereRaw('lower(student_profiles.student_code) = ?', [$termino]))
            ->value('users.id');
        if (! $estudiante) {
            $this->addError('carne', __('No encontramos a ese estudiante.'));

            return;
        }
        try {
            $matricula->inscribir(auth()->user(), (int) $estudiante, $this->seccionId());
        } catch (ValidationException $e) {
            $this->addError('carne', collect($e->errors())->flatten()->first());

            return;
        }
        $this->reset('carne');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Estudiante inscrito en la sección.'));
    }

    public function confirmarRetiro(int $matricula): void
    {
        $this->retirarId = $matricula;
        $this->motivo = '';
        $this->dispatch('abrir-modal', 'retirar-seccion');
    }

    public function retirar(MatriculaService $matricula): void
    {
        $this->validate(['motivo' => 'required|string|min:5|max:300'], [], ['motivo' => 'motivo']);
        $matricula->retirar(auth()->user(), (int) $this->retirarId, $this->motivo);
        $this->reset('retirarId', 'motivo');
        $this->dispatch('cerrar-modal', 'retirar-seccion');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('La matrícula quedó retirada.'));
    }

    public function render(SeccionService $secciones, Alcance $alcance): View
    {
        return $this->paginaDeSeccion('modules.academica.estudiantes-seccion', [
            'estudiantes' => $secciones->estudiantes(auth()->user(), $this->seccionId()),
            'puedeRetirar' => $alcance->de(auth()->user(), 'matricula.gestionar') !== 'S',
        ]);
    }
}
