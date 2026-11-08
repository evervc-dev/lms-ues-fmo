<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Evaluaciones sin programar (RF14, ADR-066): una evaluación copiada de un período anterior llega sin fechas ni cómputo
 * y el docente la programa después. Las fechas pasan a aceptar NULL, pero las dos o ninguna; `closes_at > opens_at`
 * sigue igual (con NULL no se evalúa). En SQLite la regla la garantiza ReutilizacionService (ADR-033).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->timestampTz('opens_at')->nullable()->change();
            $table->timestampTz('closes_at')->nullable()->change();
        });

        Adaptive::check('assessments', 'assessments_schedule', '(opens_at IS NULL) = (closes_at IS NULL)');
    }

    public function down(): void
    {
        if (Adaptive::isPgsql()) {
            DB::statement('ALTER TABLE assessments DROP CONSTRAINT IF EXISTS assessments_schedule');
        }

        // Vuelve a NOT NULL: falla si quedan evaluaciones sin programar, que hay que programar o borrar antes.
        Schema::table('assessments', function (Blueprint $table) {
            $table->timestampTz('opens_at')->nullable(false)->change();
            $table->timestampTz('closes_at')->nullable(false)->change();
        });
    }
};
