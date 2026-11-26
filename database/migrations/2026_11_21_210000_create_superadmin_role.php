<?php

use App\Support\DbContext;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rol `superadmin` (ADR-082) en una base que ya tiene datos: lo crea y le da los permisos que le asigna
 * database/seeders/data/rbac.php. Solo agrega; la cuenta se crea aparte con `php artisan superadmin:crear`. En una base
 * vacía no hace nada: ahí BaseSystemSeeder siembra todos los roles, este incluido.
 */
return new class extends Migration
{
    private const ROL = 'superadmin';

    public function up(): void
    {
        /** @var array{permissions: array<string, array<string, string>>} $rbac */
        $rbac = require database_path('seeders/data/rbac.php');
        $permisos = collect($rbac['permissions'])->filter(fn (array $alcances) => isset($alcances[self::ROL]))->keys();

        // role_has_permissions se audita en PostgreSQL: la escritura va como sistema.
        if (Role::query()->doesntExist()) {
            return;
        }

        DbContext::system(function () use ($permisos) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $rol = Role::findOrCreate(self::ROL, 'web');
            foreach ($permisos as $permiso) {
                Permission::findOrCreate($permiso, 'web');
                $rol->hasPermissionTo($permiso) || $rol->givePermissionTo($permiso);
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }, 'permissions.granted');
    }

    public function down(): void
    {
        DbContext::system(function () {
            Role::query()->where('guard_name', 'web')->where('name', self::ROL)->first()?->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }, 'permissions.revoked');
    }
};
