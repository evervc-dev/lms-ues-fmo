<?php

use App\Support\DbContext;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * `periodos.calendario.gestionar` (RF06, ADR-068) en una base que ya tiene datos, como
 * 2026_10_28_000100: BaseSystemSeeder solo corre con la base vacía y sincroniza, lo que pisaría los cambios
 * hechos desde la pantalla de roles (RF03). Aquí solo se agrega.
 *
 * Separa el calendario de evaluaciones de `periodos.gestionar`, que es G y solo del Administrador: con este
 * permiso el Jefe gestiona las fechas de su departamento (D) y el Coordinador las de su carrera (C).
 */
return new class extends Migration
{
    private const PERMISO = 'periodos.calendario.gestionar';

    /** @var array<int, string> */
    private const ROLES = ['administrador', 'jefe_departamento', 'coordinador'];

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
