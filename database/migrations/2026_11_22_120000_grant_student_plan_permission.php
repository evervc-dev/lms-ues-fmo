<?php

use App\Support\DbContext;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * `plan.ver` (RF37) en una base que ya tiene datos, como 2026_11_21_144053: el estudiante ve su plan de estudios con lo
 * aprobado, lo que cursa y lo pendiente.
 */
return new class extends Migration
{
    private const PERMISO = 'plan.ver';

    /** @var array<int, string> */
    private const ROLES = ['estudiante'];

    public function up(): void
    {
        // role_has_permissions se audita en PostgreSQL: la escritura va como sistema.
        DbContext::system(function () {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            Permission::findOrCreate(self::PERMISO, 'web');
            Role::query()->where('guard_name', 'web')->whereIn('name', self::ROLES)->get()
                ->each(fn (Role $rol) => $rol->hasPermissionTo(self::PERMISO) || $rol->givePermissionTo(self::PERMISO));
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }, 'permissions.granted');
    }

    public function down(): void
    {
        DbContext::system(function () {
            Permission::query()->where('name', self::PERMISO)->where('guard_name', 'web')->first()?->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }, 'permissions.revoked');
    }
};
