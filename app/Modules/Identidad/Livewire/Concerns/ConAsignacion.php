<?php

namespace App\Modules\Identidad\Livewire\Concerns;

use App\Modules\Identidad\Services\AsignacionService;
use App\Modules\Identidad\Services\JerarquiaService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * Campos de asignación de un rol (RF04, ADR-037), compartidos por crear una cuenta y cambiar sus roles:
 * la carrera que coordina, el departamento que dirige, el del docente y la carrera, el plan, el ciclo y
 * el carné del estudiante. La vista los dibuja con modules/identidad/partials/asignacion.
 *
 * Aquí solo va el **formato**; que cada rol exija su asignación es regla de negocio y la aplican
 * UsuarioService y AsignacionService.
 */
trait ConAsignacion
{
    public ?int $carreraCoordina = null;

    public ?int $departamentoJefatura = null;

    public ?int $departamentoDocente = null;

    public ?int $carreraEstudiante = null;

    public ?int $planEstudiante = null;

    public ?int $cicloEstudiante = null;

    public string $carne = '';

    /** El carné ya asignado no se cambia desde la interfaz. */
    #[Locked]
    public bool $carneFijo = false;

    /**
     * Correo institucional de la cuenta: de él sale el carné (ll22017@ues.edu.sv → ll22017).
     */
    abstract protected function correoDeAsignacion(): string;

    /**
     * Cuenta a la que se asignan los roles; null si todavía no existe (alta).
     */
    abstract protected function usuarioDeAsignacion(): ?int;

    /**
     * Titulares que se reemplazarían al guardar: el coordinador vigente de la carrera elegida y el jefe
     * vigente del departamento elegido. La vista lo avisa antes de confirmar.
     *
     * @return array<string, object>
     */
    #[Computed]
    public function relevos(): array
    {
        $asignaciones = app(AsignacionService::class);

        return array_filter([
            'coordinador' => $asignaciones->titularARelevar('coordinador', $this->carreraCoordina, $this->usuarioDeAsignacion()),
            'jefe_departamento' => $asignaciones->titularARelevar('jefe_departamento', $this->departamentoJefatura, $this->usuarioDeAsignacion()),
        ]);
    }

    /**
     * Carné que tomará la cuenta según su correo; null si ya tiene uno o si el correo no da uno válido
     * (entonces se escribe a mano).
     */
    #[Computed]
    public function carneDelCorreo(): ?string
    {
        return $this->carneFijo ? null : AsignacionService::carneDeCorreo($this->correoDeAsignacion());
    }

    /**
     * Si el plan elegido no es de la nueva carrera, se limpia para que elija otro.
     */
    public function updatedCarreraEstudiante(): void
    {
        $plan = $this->opciones['planes']->firstWhere('id', $this->planEstudiante);
        if ($plan === null || (int) $plan->career_id !== $this->carreraEstudiante) {
            $this->planEstudiante = null;
        }
    }

    /**
     * Catálogos de los selectores de asignación.
     *
     * @return array{departamentos: Collection<int, object>, carreras: Collection<int, object>, planes: Collection<int, object>}
     */
    #[Computed]
    public function opciones(): array
    {
        return app(AsignacionService::class)->opciones(auth()->user());
    }

    /**
     * @return array<string, string>
     */
    protected function reglasAsignacion(): array
    {
        return [
            'carreraCoordina' => 'nullable|integer',
            'departamentoJefatura' => 'nullable|integer',
            'departamentoDocente' => 'nullable|integer',
            'carreraEstudiante' => 'nullable|integer',
            'planEstudiante' => 'nullable|integer',
            'cicloEstudiante' => 'nullable|integer|between:1,'.AsignacionService::CICLO_MAXIMO,
            'carne' => 'nullable|string|max:20',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function atributosAsignacion(): array
    {
        return [
            'carreraCoordina' => __('carrera que coordina'),
            'departamentoJefatura' => __('departamento que dirige'),
            'departamentoDocente' => __('departamento del docente'),
            'carreraEstudiante' => __('carrera'),
            'planEstudiante' => __('plan de estudios'),
            'cicloEstudiante' => __('ciclo'),
            'carne' => __('carné'),
        ];
    }

    /**
     * Asignación del rol indicado, en el formato de AsignacionService::aplicar(), sin los campos vacíos.
     *
     * @return array<string, mixed>
     */
    protected function asignacionDe(string $rol): array
    {
        return $this->sinVacios($this->porRol()[$rol] ?? []);
    }

    /**
     * Asignación de cada rol marcado que el actor puede asignar: las de los demás roles no se tocan.
     *
     * @param  array<int, string>  $roles
     * @return array<string, array<string, mixed>>
     */
    protected function asignacionesDe(array $roles): array
    {
        $asignables = app(JerarquiaService::class)->rolesAsignables(auth()->user());
        $marcados = array_intersect_key($this->porRol(), array_flip(array_intersect($roles, $asignables)));

        return array_filter(array_map($this->sinVacios(...), $marcados));
    }

    /**
     * Deja los campos de la ficha en las propiedades del componente.
     *
     * @param  array<string, mixed>  $asignacion
     */
    protected function precargarAsignacion(array $asignacion): void
    {
        $this->carreraCoordina = $asignacion['carreraCoordina'];
        $this->departamentoJefatura = $asignacion['departamentoJefatura'];
        $this->departamentoDocente = $asignacion['departamentoDocente'];
        $this->carreraEstudiante = $asignacion['carreraEstudiante'];
        $this->planEstudiante = $asignacion['planEstudiante'];
        $this->cicloEstudiante = $asignacion['cicloEstudiante'];
        $this->carne = (string) ($asignacion['carne'] ?? '');
        $this->carneFijo = $asignacion['carne'] !== null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function porRol(): array
    {
        return [
            'coordinador' => ['carrera' => $this->carreraCoordina],
            'jefe_departamento' => ['departamento' => $this->departamentoJefatura],
            'docente' => ['departamento' => $this->departamentoDocente],
            'estudiante' => [
                'carrera' => $this->carreraEstudiante, 'plan' => $this->planEstudiante,
                'ciclo' => $this->cicloEstudiante, 'carne' => $this->carneFijo || $this->carneDelCorreo() !== null ? null : $this->carne,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $campos
     * @return array<string, mixed>
     */
    private function sinVacios(array $campos): array
    {
        return array_filter($campos, fn ($valor) => $valor !== null && $valor !== '');
    }
}
