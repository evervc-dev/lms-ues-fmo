<?php

namespace App\Modules\Identidad\Services;

use App\Models\User;
use App\Support\Alcance;
use App\Support\DbContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles y permisos (RF03, «roles seguros»): el Administrador asigna o quita permisos a los roles,
 * no crea permisos nuevos y no puede quitarle `roles.gestionar` al rol Administrador.
 */
class RolService
{
    public const ROLES = ['administrador', 'jefe_departamento', 'coordinador', 'docente', 'estudiante', 'soporte_tecnico'];

    public function __construct(private Alcance $alcance) {}

    /**
     * @return array{roles: array<string, array{usuarios: int, permisos: array<int, string>}>, grupos: array<string, array<int, string>>}
     */
    public function matriz(User $actor): array
    {
        $this->alcance->exigir($actor, 'roles.gestionar');
        $usuarios = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->groupBy('roles.name')->selectRaw('roles.name as rol, count(*) as total')->pluck('total', 'rol');

        $roles = [];
        foreach (Role::query()->with('permissions')->whereIn('name', self::ROLES)->get()->sortBy(fn ($r) => array_search($r->name, self::ROLES, true)) as $rol) {
            $roles[$rol->name] = ['usuarios' => (int) ($usuarios[$rol->name] ?? 0), 'permisos' => $rol->permissions->pluck('name')->all()];
        }

        $grupos = Permission::query()->orderBy('name')->pluck('name')
            ->groupBy(fn (string $permiso) => explode('.', $permiso)[0])->map->values()->all();

        return ['roles' => $roles, 'grupos' => $grupos];
    }

    /**
     * Aplica de una vez los cambios que el Administrador acumuló en la pantalla (QA admin 21/11: un clic de más ya no
     * quita un permiso al instante). Todo va en una transacción auditada: si un cambio no vale, no se aplica ninguno.
     *
     * @param  array<int, array{rol: string, permiso: string, conceder: bool}>  $cambios
     * @return int Cantidad de permisos que cambiaron.
     *
     * @throws ValidationException
     */
    public function aplicar(User $actor, array $cambios): int
    {
        $this->alcance->exigir($actor, 'roles.gestionar');
        $pendientes = [];
        foreach ($cambios as $cambio) {
            if (! in_array($cambio['rol'], self::ROLES, true)) {
                throw ValidationException::withMessages(['permiso' => __('Uno de los roles elegidos no existe.')]);
            }
            Permission::findByName($cambio['permiso'], 'web'); // Solo permisos existentes: no se crean desde la interfaz.
            if ($cambio['rol'] === 'administrador' && $cambio['permiso'] === 'roles.gestionar' && ! $cambio['conceder']) {
                throw ValidationException::withMessages(['permiso' => __('El rol Administrador no puede perder el control de roles y permisos.')]);
            }
            $modelo = Role::findByName($cambio['rol'], 'web');
            if ($modelo->hasPermissionTo($cambio['permiso']) !== $cambio['conceder']) {
                $pendientes[] = [$modelo, $cambio['permiso'], $cambio['conceder']];
            }
        }

        DbContext::run(function () use ($pendientes): void {
            foreach ($pendientes as [$modelo, $permiso, $conceder]) {
                $conceder ? $modelo->givePermissionTo($permiso) : $modelo->revokePermissionTo($permiso);
            }
        }, 'role.permission.changed');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return count($pendientes);
    }

    public function alternar(User $actor, string $rol, string $permiso): bool
    {
        $this->alcance->exigir($actor, 'roles.gestionar');
        $modelo = Role::findByName($rol, 'web');
        Permission::findByName($permiso, 'web'); // Solo permisos existentes: no se crean desde la interfaz.

        if ($rol === 'administrador' && $permiso === 'roles.gestionar') {
            throw ValidationException::withMessages(['permiso' => __('El rol Administrador no puede perder el control de roles y permisos.')]);
        }

        $tenia = $modelo->hasPermissionTo($permiso);
        DbContext::run(fn () => $tenia ? $modelo->revokePermissionTo($permiso) : $modelo->givePermissionTo($permiso), 'role.permission.changed');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return ! $tenia;
    }
}
