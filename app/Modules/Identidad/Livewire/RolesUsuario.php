<?php

namespace App\Modules\Identidad\Livewire;

use App\Models\User;
use App\Modules\Identidad\Livewire\Concerns\ConAsignacion;
use App\Modules\Identidad\Services\JerarquiaService;
use App\Modules\Identidad\Services\RolService;
use App\Modules\Identidad\Services\UsuarioService;
use App\Support\Navegacion\FormularioPorPasos;
use App\Support\Navegacion\PaginaLivewire;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Roles y asignaciones de una cuenta (RF04, ADR-037). Va en página y no en un modal: cada rol marcado suma
 * sus campos de ámbito, así que el formulario crece con los datos (docs/13 Layout/Layout base.md).
 *
 * El actor solo cambia los roles que puede asignar; los demás se muestran sin edición y sus asignaciones no
 * se tocan. Quién puede cambiar qué lo decide JerarquiaService.
 */
#[Layout('layouts::app')]
class RolesUsuario extends Component
{
    use ConAsignacion;
    use FormularioPorPasos;
    use PaginaLivewire;

    #[Locked]
    public int $usuarioId;

    /** @var array<int, string> */
    public array $roles = [];

    public function mount(int $usuario, UsuarioService $usuarios, JerarquiaService $jerarquia): void
    {
        $this->usuarioId = $usuario;
        $ficha = $usuarios->ficha(auth()->user(), $usuario);
        abort_unless($jerarquia->puedeCambiarRoles(auth()->user(), $ficha['usuario']), 403);

        $this->roles = $ficha['rolesActuales'];
        $this->precargarAsignacion($ficha['asignacion']);
    }

    /**
     * Qué roles tiene la cuenta, los datos de ámbito de cada uno y el resumen para confirmar.
     *
     * @return array<int, array{titulo: string, descripcion?: string, reglas?: array<string, mixed>, atributos?: array<string, string>}>
     */
    public function pasos(): array
    {
        return [
            [
                'titulo' => __('Roles'),
                'descripcion' => __('Marque los que tendrá la cuenta.'),
                'reglas' => ['roles' => 'required|array|min:1', 'roles.*' => ['string', Rule::in(RolService::ROLES)]],
                'atributos' => ['roles' => __('roles')],
            ],
            [
                'titulo' => __('Asignaciones'),
                'descripcion' => __('El ámbito de cada rol marcado.'),
                'reglas' => $this->reglasAsignacion(),
                'atributos' => $this->atributosAsignacion(),
            ],
            [
                'titulo' => __('Revisar y guardar'),
                'descripcion' => __('Confirme antes de aplicar los cambios.'),
            ],
        ];
    }

    /**
     * Combinación de roles (ADR-053): la regla vive en JerarquiaService; aquí solo se consulta para no dejar
     * avanzar con Estudiante junto a Docente o Soporte Técnico y mostrar el choque sobre las casillas.
     */
    protected function validarPaso(int $paso): void
    {
        if ($paso === 1) {
            app(JerarquiaService::class)->exigirRolesCompatibles($this->roles);
        }
    }

    public function guardar(UsuarioService $usuarios): void
    {
        $this->validarTodosLosPasos();

        try {
            $usuarios->cambiarRoles(auth()->user(), $this->usuarioId, $this->roles, $this->asignacionesDe($this->roles));
        } catch (ValidationException $e) {
            // Los errores de la lista de roles se ven en el primer paso: sin volver, el aviso quedaría oculto.
            if (array_key_exists('roles', $e->errors())) {
                $this->paso = 1;
            }

            throw $e;
        }

        session()->flash('aviso', ['tipo' => 'exito', 'mensaje' => __('Se actualizaron los roles y las asignaciones.')]);
        $this->redirectRoute('usuarios.show', ['usuario' => $this->usuarioId], navigate: true);
    }

    public function render(UsuarioService $usuarios, JerarquiaService $jerarquia): View
    {
        $ficha = $usuarios->ficha(auth()->user(), $this->usuarioId);

        return $this->pagina('modules.identidad.roles-usuario', [
            'usuario' => $ficha['usuario'],
            'rolesDisponibles' => RolService::ROLES,
            'rolesAsignables' => $jerarquia->rolesAsignables(auth()->user()),
        ], intermedias: [['etiqueta' => $ficha['usuario']->fullName(), 'url' => route('usuarios.show', ['usuario' => $this->usuarioId])]]);
    }

    protected function correoDeAsignacion(): string
    {
        return (string) User::query()->whereKey($this->usuarioId)->value('institutional_email');
    }

    protected function usuarioDeAsignacion(): ?int
    {
        return $this->usuarioId;
    }
}
