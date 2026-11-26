<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Archivar períodos, departamentos y carreras (RF06, RF07, ADR-092): `archived_at` vacío = activo. Lo archivado sale de
 * las listas y los selectores y no admite cambios, pero su historial (notas, actas, asistencia, auditoría) se conserva.
 * Solo se archiva un período cerrado.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['academic_periods', 'departments', 'careers'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->timestampTz('archived_at')->nullable();
            });
        }
        Adaptive::check('academic_periods', 'academic_periods_archived_closed', "archived_at IS NULL OR status = 'closed'");
    }

    public function down(): void
    {
        if (Adaptive::isPgsql()) {
            DB::statement('ALTER TABLE academic_periods DROP CONSTRAINT IF EXISTS academic_periods_archived_closed');
        }
        foreach (['academic_periods', 'departments', 'careers'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropColumn('archived_at');
            });
        }
    }
};
