<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Auditoría de los permisos directos por usuario (RF04, ADR-037): conceder o retirar `usuarios.crear-docente`
 * queda en audit_logs. Solo PostgreSQL (ADR-033).
 */
return new class extends Migration
{
    public function up(): void
    {
        Adaptive::pgsqlFile('07_audit_permissions.sql');
    }

    public function down(): void
    {
        if (! Adaptive::isPgsql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS model_has_permissions_audit ON model_has_permissions');
    }
};
