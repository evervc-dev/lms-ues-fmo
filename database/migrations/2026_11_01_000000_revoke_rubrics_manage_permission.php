<?php

use App\Support\DbContext;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Quita `rubricas.gestionar` de una base que ya tiene datos (ADR-065): la rúbrica se arma dentro de la tarea con
 * `tareas.gestionar` y la página Rúbricas ya no existe. BaseSystemSeeder solo corre con la base vacía.
 */
return new class extends Migration
{
    public function up(): void
    {
        // role_has_permissions se audita en PostgreSQL: la escritura va como sistema.
        DbContext::system(function () {
            Permission::query()->where('name', 'rubricas.gestionar')->where('guard_name', 'web')->first()?->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }, 'permissions.revoked');
    }

    public function down(): void
    {
        DbContext::system(function () {
            Permission::findOrCreate('rubricas.gestionar', 'web');
            Role::query()->where('guard_name', 'web')->where('name', 'docente')->first()?->givePermissionTo('rubricas.gestionar');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }, 'permissions.granted');
    }
};
