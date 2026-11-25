<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Auditoría de las justificaciones de inasistencia (RF19): la solicitud y su revisión. Antes, un rechazo no dejaba
 * rastro; la aceptación sí, porque cambia el registro. Solo PostgreSQL (ADR-033).
 */
return new class extends Migration
{
    public function up(): void
    {
        Adaptive::pgsqlFile('16_audit_attendance_justifications.sql');
    }

    public function down(): void
    {
        if (! Adaptive::isPgsql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS attendance_justifications_audit ON attendance_justifications');
    }
};
