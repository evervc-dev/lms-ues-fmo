<?php

namespace App\Modules\Evaluaciones\Livewire;

use App\Modules\Evaluaciones\Services\CuestionarioService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Bancos de preguntas (RF16): propios y compartidos.
 */
#[Layout('layouts::app')]
class Bancos extends Component
{
    use PaginaLivewire;

    public string $nombre = '';

    public ?int $asignatura = null;

    /** Banco que se está editando en el modal; null al crear uno nuevo. */
    public ?int $editando = null;

    public function crear(CuestionarioService $cuestionarios): void
    {
        $this->validate(['nombre' => 'required|string|max:150'], [], ['nombre' => 'nombre']);
        $id = $cuestionarios->crearBanco(auth()->user(), $this->nombre, $this->asignatura);
        $this->redirectRoute('bancos-de-preguntas.show', ['banco' => $id], navigate: true);
    }

    public function abrirEdicion(int $banco, CuestionarioService $cuestionarios): void
    {
        $fila = $cuestionarios->bancos(auth()->user())->firstWhere('id', $banco) ?? abort(404);
        $this->editando = $banco;
        $this->nombre = (string) $fila->name;
        $this->asignatura = $fila->subject_id !== null ? (int) $fila->subject_id : null;
        $this->resetValidation();
        $this->dispatch('abrir-modal', 'editar-banco');
    }

    public function guardarEdicion(CuestionarioService $cuestionarios): void
    {
        $this->validate(['nombre' => 'required|string|max:150'], [], ['nombre' => 'nombre']);
        $cuestionarios->renombrarBanco(auth()->user(), (int) $this->editando, $this->nombre, $this->asignatura);
        $this->dispatch('cerrar-modal', 'editar-banco');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Banco actualizado.'));
        $this->reset('nombre', 'asignatura', 'editando');
    }

    public function eliminar(int $banco, CuestionarioService $cuestionarios): void
    {
        try {
            $cuestionarios->eliminarBanco(auth()->user(), $banco);
            $this->dispatch('aviso', tipo: 'exito', mensaje: __('Banco eliminado.'));
        } catch (ValidationException $e) {
            $this->dispatch('aviso', tipo: 'error', mensaje: collect($e->errors())->flatten()->first());
        }
    }

    public function render(CuestionarioService $cuestionarios): View
    {
        $asignaturas = DB::table('subjects')->whereIn('id', DB::table('sections')->join('teaching_assignments', 'teaching_assignments.section_id', '=', 'sections.id')
            ->where('teacher_id', auth()->id())->select('sections.subject_id'))->orderBy('code')->get(['id', 'code', 'name']);

        return $this->pagina('modules.evaluaciones.bancos', ['bancos' => $cuestionarios->bancos(auth()->user()), 'asignaturas' => $asignaturas]);
    }
}
