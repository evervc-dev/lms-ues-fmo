<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Auditoría de las sesiones de asistencia (RF19, ADR-075): apertura y cierre, con el motivo cuando el docente
 * abre fuera del horario del grupo teórico. Solo PostgreSQL (ADR-033).
 */
return new class extends Migration
{
    public function up(): void
    {
        Adaptive::pgsqlFile('15_audit_attendance_sessions.sql');
    }

    public function down(): void
    {
        if (! Adaptive::isPgsql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS attendance_sessions_audit ON attendance_sessions');
    }
};
