<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Una sola sesión de asistencia abierta por grupo teórico (ADR-076). El Service ya lo comprueba; el índice cierra la
 * carrera de dos clics seguidos en «Abrir asistencia». Índice único parcial en SQLite y PostgreSQL (ADR-033).
 */
return new class extends Migration
{
    public function up(): void
    {
        Adaptive::partialUnique('attendance_sessions', ['section_id'], "status = 'open'", 'attendance_sessions_single_open_unique');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS attendance_sessions_single_open_unique');
    }
};
