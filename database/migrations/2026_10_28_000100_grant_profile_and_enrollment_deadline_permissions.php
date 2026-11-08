<?php

use App\Support\DbContext;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permisos nuevos en una base que ya tiene datos. BaseSystemSeeder solo corre con la base vacía
 * (docker/entrypoint.sh) y sincroniza (syncPermissions), lo que pisaría los cambios hechos desde la pantalla de
 * roles (RF03). Aquí solo se **agregan**: si un rol ya tiene el permiso, o si el rol no existe, no pasa nada.
 *
 * - `perfiles.ver` (RF36, ADR-056), para todos los roles.
 * - Los de RF29 (ADR-051), que llegaron a database/seeders/data/rbac.php el 26/10/2026 sin migración y por eso
 *   no existían en staging.
 */
return new class extends Migration
{
    /** @var array<string, array<int, string>> permiso => roles */
    private const PERMISOS = [
        'perfiles.ver' => ['administrador', 'jefe_departamento', 'coordinador', 'docente', 'estudiante', 'soporte_tecnico'],
        'matricula.claves.gestionar' => ['jefe_departamento', 'coordinador'],
        'matricula.plazo.gestionar' => ['administrador', 'jefe_departamento', 'coordinador'],
        'matricula.plazo.prorrogar' => ['jefe_departamento'],
        'matricula.prorroga.otorgar' => ['jefe_departamento', 'coordinador'],
    ];

    public function up(): void
    {
        // role_has_permissions se audita en PostgreSQL: la escritura va como sistema.
        DbContext::system(function () {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::PERMISOS as $permiso => $roles) {
                Permission::findOrCreate($permiso, 'web');
                Role::query()->where('guard_name', 'web')->whereIn('name', $roles)->get()
                    ->each(fn (Role $rol) => $rol->hasPermissionTo($permiso) || $rol->givePermissionTo($permiso));
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }, 'permissions.granted');
    }

    public function down(): void
    {
        // Solo se quita lo propio de RF36: los de RF29 ya estaban en el seeder y los usa la aplicación.
        DbContext::system(function () {
            Permission::query()->where('name', 'perfiles.ver')->where('guard_name', 'web')->first()?->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }, 'permissions.revoked');
    }
};
