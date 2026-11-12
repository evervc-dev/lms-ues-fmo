<?php

namespace App\Modules\Identidad\Livewire;

use App\Modules\Identidad\Services\PerfilContextualService;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Perfil de una persona (RF36, ADR-056): lo que muestra depende del rol de quien se visita y del vínculo con quien
 * mira. Solo lectura; la gestión de la cuenta sigue en Usuarios (RF04).
 */
#[Layout('layouts::app')]
class PerfilPersona extends Component
{
    use PaginaLivewire;

    #[Locked]
    public int $usuarioId;

    public function mount(int $usuario, PerfilContextualService $perfiles): void
    {
        $perfiles->perfil(auth()->user(), $usuario);
        $this->usuarioId = $usuario;
    }

    public function render(PerfilContextualService $perfiles): View
    {
        $actor = auth()->user();
        $perfil = $perfiles->perfil($actor, $this->usuarioId);

        return $this->pagina('modules.identidad.perfil-persona', $perfil + [
            'administra' => $actor->checkPermissionTo('usuarios.ver') && $this->usuarioId !== $actor->id,
        ], titulo: $perfil['usuario']->fullName(), miga: $perfil['usuario']->fullName());
    }
}
