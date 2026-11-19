<?php

use App\Support\DbContext;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * El Jefe gestiona las asignaturas de su departamento (ADR-072) y crea sus grupos teóricos (ADR-073) en una base
 * que ya tiene datos, como 2026_11_01_000200: BaseSystemSeeder solo corre con la base vacía y sincroniza, lo que
 * pisaría los cambios hechos desde la pantalla de roles (RF03). Aquí solo se agrega lo que falta.
 */
return new class extends Migration
{
    private const ROL = 'jefe_departamento';

    /** @var array<int, string> */
    private const PERMISOS = ['asignaturas.gestionar', 'secciones.gestionar'];

    public function up(): void
    {
        // role_has_permissions se audita en PostgreSQL: la escritura va como sistema.
        DbContext::system(function () {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $rol = Role::query()->where('guard_name', 'web')->where('name', self::ROL)->first();
            foreach (self::PERMISOS as $permiso) {
                Permission::findOrCreate($permiso, 'web');
                if ($rol !== null && ! $rol->hasPermissionTo($permiso)) {
                    $rol->givePermissionTo($permiso);
                }
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }, 'permissions.granted');
    }

    public function down(): void
    {
        DbContext::system(function () {
            Role::query()->where('guard_name', 'web')->where('name', self::ROL)->first()?->revokePermissionTo(self::PERMISOS);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }, 'permissions.revoked');
    }
};
