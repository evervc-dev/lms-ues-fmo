<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Departamentos archivables (RF07, ADR-076): un departamento archivado (`is_active = false`) deja de ofrecerse a
 * docentes, estudiantes y listas de trabajo, pero conserva su historial. El trigger `departments_audit`
 * (14_audit_catalog.sql) ya audita el UPDATE, así que no hace falta SQL propio de PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
