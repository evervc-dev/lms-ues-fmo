<?php

use App\Support\DbContext;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * `matricula.claves.gestionar` para el Administrador (G) en una base que ya tiene datos, como 2026_11_21_144053: la
 * facultad asigna una sola clave de matrícula para todas las asignaturas (ADR-080).
 */
return new class extends Migration
{
    private const PERMISO = 'matricula.claves.gestionar';

    private const ROL = 'administrador';

    public function up(): void
    {
        // role_has_permissions se audita en PostgreSQL: la escritura va como sistema.
        DbContext::system(function () {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            Permission::findOrCreate(self::PERMISO, 'web');
            $rol = Role::query()->where('guard_name', 'web')->where('name', self::ROL)->first();
            if ($rol !== null && ! $rol->hasPermissionTo(self::PERMISO)) {
                $rol->givePermissionTo(self::PERMISO);
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }, 'permissions.granted');
    }

    public function down(): void
    {
        DbContext::system(function () {
            Role::query()->where('guard_name', 'web')->where('name', self::ROL)->first()?->revokePermissionTo(self::PERMISO);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }, 'permissions.revoked');
    }
};
