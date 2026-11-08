<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reglas y vistas finales (ADR-027, ADR-033).
 *
 * - Ambos motores: vistas portables v_section_capacity (RF09) y v_unit_progress (RF18).
 * - Solo PostgreSQL: EXCLUDE de aulas, triggers de auditoría e inmutabilidad, RLS de auditoría,
 *   v_teacher_kpis (RF33), mv_offer_stats (RF10) y particiones mensuales.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW v_section_capacity AS
            SELECT
                s.id AS section_id,
                s.min_capacity,
                s.max_capacity,
                (SELECT count(*) FROM enrollments e WHERE e.section_id = s.id AND e.status = 'active') AS active_enrollments
            FROM sections s
        SQL);

        DB::statement(<<<'SQL'
            CREATE VIEW v_unit_progress AS
            SELECT
                e.id AS enrollment_id,
                u.id AS unit_id,
                count(r.id) AS total_resources,
                count(rp.resource_id) AS completed_resources
            FROM enrollments e
            JOIN units u ON u.section_id = e.section_id AND u.deleted_at IS NULL
            LEFT JOIN resources r ON r.unit_id = u.id AND r.deleted_at IS NULL
            LEFT JOIN resource_progress rp ON rp.resource_id = r.id AND rp.enrollment_id = e.id
            WHERE e.status = 'active'
            GROUP BY e.id, u.id
        SQL);

        foreach (['01_constraints.sql', '02_audit.sql', '03_immutability.sql', '04_rls.sql', '05_views.sql'] as $file) {
            Adaptive::pgsqlFile($file);
        }
    }

    public function down(): void
    {
        Adaptive::pgsqlFile('99_drop.sql');
        DB::statement('DROP VIEW IF EXISTS v_unit_progress');
        DB::statement('DROP VIEW IF EXISTS v_section_capacity');
    }
};
