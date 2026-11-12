<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ámbito de una fecha del calendario de evaluaciones (RF06, ADR-068): además del Administrador, el Jefe fija las
 * fechas de su departamento y el Coordinador las de su carrera.
 *
 * Las dos columnas son anulables y excluyentes: ambas nulas es una fecha **global** del período (lo que hay hoy),
 * `department_id` una fecha del departamento y `career_id` una de la carrera. Nunca las dos a la vez, porque el
 * ámbito decide a quién se invita y quién puede tocarla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('period_calendar_events', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('period_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('career_id')->nullable()->after('department_id')->constrained('careers')->restrictOnDelete();
            $table->index(['period_id', 'department_id', 'career_id'], 'period_calendar_events_scope_index');
        });

        Adaptive::check('period_calendar_events', 'period_calendar_events_scope', 'department_id IS NULL OR career_id IS NULL');
    }

    public function down(): void
    {
        Schema::table('period_calendar_events', function (Blueprint $table) {
            $table->dropIndex('period_calendar_events_scope_index');
            $table->dropConstrainedForeignId('career_id');
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
