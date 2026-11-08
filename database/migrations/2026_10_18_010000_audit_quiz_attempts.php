<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Auditoría de los intentos de cuestionario (RF16, ADR-042): inicio, envío, cierre automático o por el docente y
 * minutos extra. Solo PostgreSQL (ADR-033).
 */
return new class extends Migration
{
    public function up(): void
    {
        Adaptive::pgsqlFile('09_audit_quiz_attempts.sql');
    }

    public function down(): void
    {
        if (! Adaptive::isPgsql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS quiz_attempts_audit ON quiz_attempts');
    }
};
