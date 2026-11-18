<?php

namespace App\Modules\Identidad\Livewire\Concerns;

use App\Models\User;
use App\Modules\Identidad\Services\AccesoDesarrolloService;
use Livewire\Attributes\Locked;

/**
 * Elegir una cuenta de «Entrar como…» y el rol con el que entrar (docs/12 Desarrollo/Setup del entorno.md),
 * compartido por la pantalla de desarrollo y el menú de usuario. La vista lo dibuja con
 * modules/identidad/partials/cambio-de-rol.
 *
 * Solo es una herramienta de desarrollo: el Service comprueba LMS_DEV_LOGIN en cada llamada.
 */
trait ConCambioDeRol
{
    /** Nombre del modal donde se confirma la entrada. */
    public const MODAL = 'cambio-de-rol';

    public string $buscar = '';

    #[Locked]
    public ?int $seleccionado = null;

    public string $rol = '';

    public function seleccionar(int $usuario): void
    {
        $this->seleccionado = $usuario;
        $this->rol = '';
        $this->resetValidation();
        $this->dispatch('abrir-modal', self::MODAL);
    }

    public function confirmar(AccesoDesarrolloService $acceso): void
    {
        abort_if($this->seleccionado === null, 404);

        $acceso->entrarConRol($this->seleccionado, $this->rol === '' ? null : $this->rol);

        $this->redirectRoute('inicio');
    }

    public function restaurar(AccesoDesarrolloService $acceso): void
    {
        $usuario = $acceso->restaurarRoles();

        $this->dispatch('aviso', tipo: 'exito', mensaje: $usuario === null
            ? __('No había roles que restaurar.')
            : __('Se restauraron los roles de :correo.', ['correo' => $usuario->institutional_email]));
    }

    /** Cuenta elegida en el modal, con sus roles. */
    protected function cuentaElegida(): ?User
    {
        return $this->seleccionado === null ? null : User::query()->with('roles')->find($this->seleccionado);
    }
}
