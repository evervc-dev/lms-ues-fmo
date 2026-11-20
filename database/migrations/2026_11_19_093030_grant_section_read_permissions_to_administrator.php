<?php

use App\Support\DbContext;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * El Administrador ve las secciones en solo lectura (ADR-075): `contenidos.ver`, `calificaciones.ver`,
 * `asistencia.ver` y `progreso.ver` con alcance G, sin ningún `*.gestionar`. Como 2026_11_01_000200, para una base
 * que ya tiene datos: BaseSystemSeeder solo corre con la base vacía y sincroniza, lo que pisaría los cambios
 * hechos desde la pantalla de roles (RF03). Aquí solo se agrega.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private const PERMISOS = ['contenidos.ver', 'calificaciones.ver', 'asistencia.ver', 'progreso.ver'];

    private const ROL = 'administrador';

    public function up(): void
    {
        // role_has_permissions se audita en PostgreSQL: la escritura va como sistema.
        DbContext::system(function () {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::PERMISOS as $permiso) {
                Permission::findOrCreate($permiso, 'web');
            }
            $rol = Role::query()->where('guard_name', 'web')->where('name', self::ROL)->first();
            foreach (self::PERMISOS as $permiso) {
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
            $rol = Role::query()->where('guard_name', 'web')->where('name', self::ROL)->first();
            foreach (self::PERMISOS as $permiso) {
                if ($rol !== null && $rol->hasPermissionTo($permiso)) {
                    $rol->revokePermissionTo($permiso);
                }
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }, 'permissions.revoked');
    }
};
