<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Auditoría de las asignaciones atadas a los roles (RF04, ADR-036 pendiente): perfiles docente y de
 * estudiante, carreras del estudiante, coordinaciones y jefaturas. Solo PostgreSQL (ADR-033).
 */
return new class extends Migration
{
    private const TABLAS = ['teacher_profiles', 'student_profiles', 'student_careers', 'career_coordinators', 'department_heads'];

    public function up(): void
    {
        Adaptive::pgsqlFile('06_audit_assignments.sql');
    }

    public function down(): void
    {
        if (! Adaptive::isPgsql()) {
            return;
        }

        foreach (self::TABLAS as $tabla) {
            DB::statement("DROP TRIGGER IF EXISTS {$tabla}_audit ON {$tabla}");
        }
    }
};
