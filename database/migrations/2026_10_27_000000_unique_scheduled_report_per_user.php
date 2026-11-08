<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un solo envío programado de cada reporte por usuario y frecuencia (RF25).
 *
 * En la revisión de QA del 27/10/2026 se pudo programar «Carga docente» siete veces con la misma fecha de
 * envío: el usuario recibía siete correos iguales. `scheduled_reports` no lleva baja lógica (cancelar borra
 * la fila), así que basta un UNIQUE normal, que SQLite y PostgreSQL soportan igual y no necesita
 * `Adaptive::partialUnique()`. Antes de crearlo se dejan los duplicados que ya existan en una sola fila,
 * la más antigua de cada combinación. La tabla no está auditada (database/sql/pgsql/02_audit.sql), así que
 * la limpieza no necesita `DbContext`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sobrantes = DB::table('scheduled_reports')
            ->select(DB::raw('min(id) as conservar'), 'user_id', 'report_type', 'frequency')
            ->groupBy('user_id', 'report_type', 'frequency')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($sobrantes as $grupo) {
            DB::table('scheduled_reports')->where('user_id', $grupo->user_id)->where('report_type', $grupo->report_type)
                ->where('frequency', $grupo->frequency)->where('id', '!=', $grupo->conservar)->delete();
        }

        Schema::table('scheduled_reports', function (Blueprint $tabla) {
            $tabla->unique(['user_id', 'report_type', 'frequency']);
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_reports', function (Blueprint $tabla) {
            $tabla->dropUnique(['user_id', 'report_type', 'frequency']);
        });
    }
};
