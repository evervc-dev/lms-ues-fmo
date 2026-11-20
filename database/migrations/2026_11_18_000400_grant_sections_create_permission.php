<?php

use App\Support\DbContext;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * `secciones.crear` (ADR-074) en una base que ya tiene datos, como 2026_11_01_000200: crear grupos teóricos se separa
 * de `secciones.gestionar` para que el Coordinador conserve el horario, el aula y los docentes de los grupos de su
 * carrera pero ya no los cree. Lo tienen el Administrador (G) y el Jefe (D).
 */
return new class extends Migration
{
    private const PERMISO = 'secciones.crear';

    /** @var array<int, string> */
    private const ROLES = ['administrador', 'jefe_departamento'];

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
