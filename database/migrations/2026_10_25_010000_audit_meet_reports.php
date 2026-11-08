<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Auditoría de las clases virtuales y de los reportes de Meet (RF21, ADR-003): programación, grabación,
 * subida, confirmación y reemplazo del reporte. Solo PostgreSQL (ADR-033).
 */
return new class extends Migration
{
    public function up(): void
    {
        Adaptive::pgsqlFile('11_audit_meet.sql');
    }

    public function down(): void
    {
        if (! Adaptive::isPgsql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS meet_reports_audit ON meet_reports');
        DB::statement('DROP TRIGGER IF EXISTS video_sessions_audit ON video_sessions');
    }
};
