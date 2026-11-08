<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Auditoría del catálogo y de la estructura institucional (RF05, RF07; hallazgo H-08 del informe de QA del rol
 * Administrador): triggers de 14_audit_catalog.sql. Solo en PostgreSQL (ADR-027, ADR-033).
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private const TABLAS = ['departments', 'careers', 'study_plans', 'subjects', 'subject_prerequisites', 'career_subjects', 'classrooms'];

    public function up(): void
    {
        Adaptive::pgsqlFile('14_audit_catalog.sql');
    }

    public function down(): void
    {
        if (Adaptive::isPgsql()) {
            foreach (self::TABLAS as $tabla) {
                DB::statement("DROP TRIGGER IF EXISTS {$tabla}_audit ON {$tabla}");
            }
        }
    }
};
