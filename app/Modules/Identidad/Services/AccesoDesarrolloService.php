<?php

namespace App\Modules\Identidad\Services;

use App\Models\User;
use App\Support\DbContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * «Entrar como…» (docs/12 Desarrollo/Setup del entorno.md): elegir un usuario sembrado sin Google y, si hace
 * falta, entrar con otro rol para ver la plataforma desde él. Solo con LMS_DEV_LOGIN=true y nunca en producción.
 *
 * El cambio de rol es una herramienta de desarrollo, no el camino de negocio: **reemplaza** los roles de la
 * cuenta (`syncRoles`) sin pasar por UsuarioService ni por la jerarquía de ADR-037, queda auditado como cambio
 * del sistema y guarda los roles originales en la sesión para poder restaurarlos. La única regla que se respeta
 * es que la cuenta de emergencia (ADR-007) no admite cambios de roles.
 */
class AccesoDesarrolloService
{
    /** Roles que esta sesión reemplazó, para el enlace «restaurar» del menú de usuario. */
    private const SESION_ROLES = 'dev.roles_previos';

    public function habilitado(): bool
    {
        return config('lms.dev_login') === true && ! app()->isProduction();
    }

    /**
     * Las cuentas *.dev@ues.edu.sv de los seeders, una por rol.
     *
     * @return Collection<int, User>
     */
    public function cuentasDeDesarrollo(): Collection
    {
        return User::query()->with('roles')->where('institutional_email', 'like', '%.dev@ues.edu.sv')->where('is_active', true)->orderBy('id')->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function buscar(string $texto): Collection
    {
        if (mb_strlen(trim($texto)) < 2) {
            return collect();
        }

        $patron = '%'.mb_strtolower(trim($texto)).'%';

        return User::query()->with('roles')->where('is_active', true)
            ->where(fn ($query) => $query->whereRaw('lower(institutional_email) like ?', [$patron])
                ->orWhereRaw("lower(first_name || ' ' || last_name) like ?", [$patron]))
            ->orderBy('institutional_email')->limit(10)->get();
    }

    /**
     * Roles del selector, en el orden de RolService.
     *
     * @return array<string, string>
     */
    public function rolesDisponibles(): array
    {
        return collect(RolService::ROLES)->mapWithKeys(fn (string $rol) => [$rol => __('roles.'.$rol)])->all();
    }

    public function entrarComo(int $usuarioId): User
    {
        return $this->entrarConRol($usuarioId, null);
    }

    /**
     * Entra como la cuenta y, con `$rol`, reemplaza antes sus roles por ese único rol.
     *
     * @throws ValidationException
     */
    public function entrarConRol(int $usuarioId, ?string $rol): User
    {
        abort_unless($this->habilitado(), 404);

        $usuario = User::query()->with('roles')->where('is_active', true)->findOrFail($usuarioId);
        $previos = $usuario->roles->pluck('name')->map(fn ($nombre) => (string) $nombre)->sort()->values()->all();
        $cambia = $rol !== null && $previos !== [$rol];
        if ($cambia) {
            $this->reemplazarRol($usuario, $rol);
        }

        Auth::login($usuario);
        session()->regenerate();
        if ($cambia) {
            session()->put(self::SESION_ROLES, ['usuario' => $usuario->id, 'correo' => $usuario->institutional_email, 'roles' => $previos]);
        }

        return $usuario;
    }

    /**
     * Roles que esta sesión reemplazó, o null si no hay nada que restaurar.
     *
     * @return array{usuario: int, correo: string, roles: list<string>}|null
     */
    public function rolesPrevios(): ?array
    {
        $datos = session()->get(self::SESION_ROLES);

        return $this->habilitado() && is_array($datos) ? $datos : null;
    }

    /**
     * Devuelve a la cuenta los roles que tenía antes del cambio, o null si ya no había nada que restaurar.
     */
    public function restaurarRoles(): ?User
    {
        abort_unless($this->habilitado(), 404);

        $datos = $this->rolesPrevios();
        session()->forget(self::SESION_ROLES);
        $usuario = $datos === null ? null : User::query()->find($datos['usuario']);
        if ($datos === null || $usuario === null) {
            return null;
        }

        DbContext::system(fn () => $usuario->syncRoles($datos['roles']), 'user.dev_roles_restored');

        return $usuario;
    }

    /**
     * @throws ValidationException
     */
    private function reemplazarRol(User $usuario, string $rol): void
    {
        if (! in_array($rol, RolService::ROLES, true)) {
            throw ValidationException::withMessages(['rol' => __('Uno de los roles elegidos no existe.')]);
        }
        if ($usuario->is_emergency) {
            throw ValidationException::withMessages(['rol' => __('La cuenta de emergencia no admite cambios de roles.')]);
        }

        DbContext::system(fn () => $usuario->syncRoles([$rol]), 'user.dev_roles_replaced');
    }
}
