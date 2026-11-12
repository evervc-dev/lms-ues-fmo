<?php

namespace App\Modules\Evaluaciones\Livewire;

use App\Modules\Evaluaciones\Services\CuestionarioService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
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

    public function crear(CuestionarioService $cuestionarios): void
    {
        $this->validate(['nombre' => 'required|string|max:150'], [], ['nombre' => 'nombre']);
        $id = $cuestionarios->crearBanco(auth()->user(), $this->nombre, $this->asignatura);
        $this->redirectRoute('bancos-de-preguntas.show', ['banco' => $id], navigate: true);
    }

    public function render(CuestionarioService $cuestionarios): View
    {
        $asignaturas = DB::table('subjects')->whereIn('id', DB::table('sections')->join('teaching_assignments', 'teaching_assignments.section_id', '=', 'sections.id')
            ->where('teacher_id', auth()->id())->select('sections.subject_id'))->orderBy('code')->get(['id', 'code', 'name']);

        return $this->pagina('modules.evaluaciones.bancos', ['bancos' => $cuestionarios->bancos(auth()->user()), 'asignaturas' => $asignaturas]);
    }
}
