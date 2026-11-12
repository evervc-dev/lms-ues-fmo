<?php

namespace App\Modules\Evaluaciones\Livewire;

use App\Modules\Evaluaciones\Services\CuestionarioService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Banco de preguntas (RF16, maqueta 32): preguntas de 4 tipos y compartir con colegas.
 */
#[Layout('layouts::app')]
class Banco extends Component
{
    use PaginaLivewire;

    #[Locked]
    public int $bancoId;

    public ?int $colega = null;

    public function mount(int $banco): void
    {
        $this->bancoId = $banco;
    }

    public function compartir(CuestionarioService $cuestionarios): void
    {
        $this->validate(['colega' => 'required|integer'], [], ['colega' => 'docente']);
        $cuestionarios->compartir(auth()->user(), $this->bancoId, (int) $this->colega);
        $this->reset('colega');
        $this->dispatch('aviso', tipo: 'exito', mensaje: __('Banco compartido.'));
    }

    public function render(CuestionarioService $cuestionarios): View
    {
        $datos = $cuestionarios->banco(auth()->user(), $this->bancoId);

        return $this->pagina('modules.evaluaciones.banco', $datos + [
            'tipos' => CuestionarioService::TIPOS,
            'colegas' => DB::table('teacher_profiles')->join('users', 'users.id', '=', 'teacher_profiles.user_id')->where('users.id', '!=', auth()->id())
                ->orderBy('users.last_name')->get(['users.id', 'users.first_name', 'users.last_name']),
        ], titulo: $datos['banco']->name);
    }
}
