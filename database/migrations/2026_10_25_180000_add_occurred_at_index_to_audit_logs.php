<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Índice por fecha en la auditoría (RF05).
 *
 * La pantalla de Auditoría ordena siempre por `occurred_at` descendente, pero los índices de la tabla
 * empiezan por `entity_type`, `actor_user_id` o `department_id`: sin filtro, la consulta recorría y
 * ordenaba toda la tabla particionada y la pantalla no llegaba a cargar (revisión de diseño del
 * 25/10/2026). En PostgreSQL el índice se crea en la tabla particionada y PostgreSQL lo propaga a cada
 * partición.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Adaptive::isPgsql()) {
            DB::unprepared('CREATE INDEX IF NOT EXISTS audit_logs_occurred_at_index ON audit_logs (occurred_at DESC);');

            return;
        }

        Schema::table('audit_logs', function ($tabla) {
            $tabla->index('occurred_at', 'audit_logs_occurred_at_index');
        });
    }

    public function down(): void
    {
        if (Adaptive::isPgsql()) {
            DB::unprepared('DROP INDEX IF EXISTS audit_logs_occurred_at_index;');

            return;
        }

        Schema::table('audit_logs', function ($tabla) {
            $tabla->dropIndex('audit_logs_occurred_at_index');
        });
    }
};
