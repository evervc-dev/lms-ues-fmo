<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lección con cuestionario (RF38, ADR-091): el cuestionario exige completar la lectura de un recurso y la página
 * del recurso registra cuándo el estudiante llegó al final (`event_type = 'end'`). El cuestionario de una lección es una
 * verificación de lectura (`is_reading_check`): no tiene ítem de calificación y no suma a la nota (ADR-091).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_settings', function (Blueprint $table) {
            // La papelera purga recursos con un DELETE: el cuestionario queda sin bloqueo en vez de impedir la purga.
            $table->foreignId('required_resource_id')->nullable()->constrained('resources')->nullOnDelete();
            // Verificación de lectura (ADR-091): se distingue así de una evaluación copiada sin programar (RF14), que
            // tampoco tiene ítem de calificación.
            $table->boolean('is_reading_check')->default(false);
        });

        if (Adaptive::isPgsql()) {
            DB::statement('ALTER TABLE resource_access_logs DROP CONSTRAINT IF EXISTS resource_access_logs_event_type_check');
            DB::statement("ALTER TABLE resource_access_logs ADD CONSTRAINT resource_access_logs_event_type_check CHECK (event_type IN ('open', 'download', 'end'))");
        }
    }

    public function down(): void
    {
        if (Adaptive::isPgsql()) {
            DB::statement("DELETE FROM resource_access_logs WHERE event_type = 'end'");
            DB::statement('ALTER TABLE resource_access_logs DROP CONSTRAINT IF EXISTS resource_access_logs_event_type_check');
            DB::statement("ALTER TABLE resource_access_logs ADD CONSTRAINT resource_access_logs_event_type_check CHECK (event_type IN ('open', 'download'))");
        }

        Schema::table('quiz_settings', function (Blueprint $table) {
            $table->dropColumn('is_reading_check');
            $table->dropConstrainedForeignId('required_resource_id');
        });
    }
};
