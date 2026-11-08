<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Auditoría de las cancelaciones de clase y los cambios de modalidad (RF32): solicitud, aprobación y
 * cancelación directa, con su motivo. Solo PostgreSQL (ADR-033).
 */
return new class extends Migration
{
    public function up(): void
    {
        Adaptive::pgsqlFile('10_audit_class_cancellations.sql');
    }

    public function down(): void
    {
        if (! Adaptive::isPgsql()) {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS class_cancellations_audit ON class_cancellations');
    }
};
