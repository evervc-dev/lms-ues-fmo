<?php

namespace App\Modules\Identidad\Livewire;

use App\Modules\Identidad\Livewire\Concerns\ConAsignacion;
use App\Modules\Identidad\Services\JerarquiaService;
use App\Modules\Identidad\Services\UsuarioService;
use App\Support\Navegacion\FormularioPorPasos;
use App\Support\Navegacion\PaginaLivewire;
use App\Support\Nombre;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Crear una cuenta institucional (RF04, maqueta 29, ADR-037). Va en página y no en un modal porque con los
 * campos de asignación del rol pasa de 6 (docs/13 Layout/Layout base.md).
 *
 * Quién puede crear y qué roles puede dar lo decide JerarquiaService: el Administrador todos; Soporte,
 * estudiantes, y docentes solo con el permiso directo `usuarios.crear-docente`.
 */
#[Layout('layouts::app')]
class CrearUsuario extends Component
{
    use ConAsignacion;
    use FormularioPorPasos;
    use PaginaLivewire;

    /** Dominio fijo de las cuentas: el formulario pide solo la parte antes de la @. */
    public const DOMINIO = '@ues.edu.sv';

    /** Solo la parte antes de la @ (ll22017); el dominio lo pone el formulario. */
    public string $correo = '';

    public string $nombres = '';

    public string $apellidos = '';

    public string $rolNuevo = '';

    /**
     * Los tres pasos del alta: los datos de la cuenta, el rol con su ámbito y el resumen para confirmar.
     *
     * @return array<int, array{titulo: string, descripcion?: string, reglas?: array<string, mixed>, atributos?: array<string, string>}>
     */
    public function pasos(): array
    {
        return [
            [
                'titulo' => __('Datos de la cuenta'),
                'descripcion' => __('Correo institucional y nombre.'),
                'reglas' => ['correo' => ['required', 'string', 'max:60', function (string $atributo, mixed $valor, Closure $fallar): void {
                    if (preg_match('/^[a-z0-9._%+-]+$/', (string) $valor) !== 1) {
                        $fallar(__('Escriba solo la parte antes de :dominio, p. ej. ll22017.', ['dominio' => self::DOMINIO]));
                    }
                }], 'nombres' => ['required', 'string', Nombre::regla()], 'apellidos' => ['required', 'string', Nombre::regla()]],
                'atributos' => ['correo' => __('correo'), 'nombres' => __('nombres'), 'apellidos' => __('apellidos')],
            ],
            [
                'titulo' => __('Rol y asignación'),
                'descripcion' => __('Cada rol pide los datos de su ámbito.'),
                'reglas' => ['rolNuevo' => ['required', Rule::in($this->rolesCreables())]] + $this->reglasAsignacion(),
                'atributos' => ['rolNuevo' => __('rol')] + $this->atributosAsignacion(),
            ],
            [
                'titulo' => __('Revisar y crear'),
                'descripcion' => __('Confirme antes de crear la cuenta.'),
            ],
        ];
    }

    /**
     * Si se pega el correo completo, se queda solo la parte antes de @ues.edu.sv. Otro dominio se deja tal cual
     * para que la validación lo rechace. Al salir del campo avisa si el correo ya tiene cuenta (QA sesión 4).
     */
    public function updatedCorreo(): void
    {
        $correo = mb_strtolower(trim($this->correo));
        $this->correo = str_ends_with($correo, self::DOMINIO) ? mb_substr($correo, 0, -mb_strlen(self::DOMINIO)) : $correo;

        $this->resetErrorBag('correo');
        if (preg_match('/^[a-z0-9._%+-]+$/', $this->correo) === 1) {
            try {
                app(UsuarioService::class)->exigirCorreoDisponible($this->correoCompleto());
            } catch (ValidationException $e) {
                $this->addError('correo', $e->errors()['correo'][0]);
            }
        }
    }

    /**
     * El primer paso no avanza con un correo que ya tiene cuenta: la regla es del Service.
     */
    protected function validarPaso(int $paso): void
    {
        if ($paso === 1) {
            app(UsuarioService::class)->exigirCorreoDisponible($this->correoCompleto());
        }
    }

    protected function correoCompleto(): string
    {
        return $this->correo === '' ? '' : $this->correo.self::DOMINIO;
    }

    public function mount(JerarquiaService $jerarquia): void
    {
        abort_unless($jerarquia->puedeCrear(auth()->user()), 403);

        $this->rolNuevo = $this->rolesCreables()[0] ?? '';
    }

    /** Orden de la lista, del rol más frecuente al menos frecuente. */
    private const ORDEN = ['estudiante', 'docente', 'coordinador', 'jefe_departamento', 'soporte_tecnico', 'administrador'];

    /**
     * Roles que el actor puede dar al crear la cuenta.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function rolesCreables(): array
    {
        return array_values(array_intersect(self::ORDEN, app(JerarquiaService::class)->rolesAsignables(auth()->user())));
    }

    public function crear(UsuarioService $usuarios): void
    {
        $this->validarTodosLosPasos();

        $usuario = $this->guardarPorPasos(fn () => $usuarios->crear(auth()->user(), [
            'correo' => $this->correoCompleto(), 'nombres' => $this->nombres, 'apellidos' => $this->apellidos,
            'rol' => $this->rolNuevo, 'asignacion' => $this->asignacionDe($this->rolNuevo),
        ]));
        if ($usuario === null) {
            return;
        }

        session()->flash('aviso', ['tipo' => 'exito', 'mensaje' => __('Se creó la cuenta de :correo.', ['correo' => $usuario->institutional_email])]);
        $this->redirectRoute('usuarios.show', ['usuario' => $usuario->id], navigate: true);
    }

    public function render(): View
    {
        return $this->pagina('modules.identidad.crear-usuario', ['dominio' => self::DOMINIO]);
    }

    protected function correoDeAsignacion(): string
    {
        return $this->correoCompleto();
    }

    protected function usuarioDeAsignacion(): ?int
    {
        return null;
    }
}
