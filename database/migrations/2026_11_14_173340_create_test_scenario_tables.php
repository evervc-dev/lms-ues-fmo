<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Escenarios de prueba del entorno de staging.
 *
 * Un escenario es un estado de la base preparado para probar un flujo con personas concretas. Guarda
 * su reparto de roles, **todas las filas que creó** (para poder limpiar solo lo suyo sin tocar el demo)
 * y su checklist, que es la que los testers marcan en vivo.
 *
 * Las tablas existen en todos los entornos para que el esquema sea uno solo, pero solo se escriben
 * fuera de producción: `EscenarioService` aborta si `LMS_DEV_LOGIN` está apagado o si es producción.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_scenarios', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('flow', 40);
            $table->string('title');
            $table->string('status', 10)->default('ready');
            $table->foreignId('period_id')->nullable()->constrained('academic_periods')->nullOnDelete();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            // Enlaces por rol, datos sembrados y resumen: lo que devuelve el constructor del escenario.
            $table->json('payload')->nullable();
            $table->timestampTz('cleaned_at')->nullable();
            $table->timestampsTz();
            $table->index(['flow', 'status']);
        });
        Adaptive::check('test_scenarios', 'test_scenarios_status', "status IN ('ready', 'cleaned')");

        // Quién juega qué. `label` es el texto que ve el tester («Docente de PRN115-01»).
        Schema::create('test_scenario_actors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_id')->constrained('test_scenarios')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 30);
            $table->string('label');
            $table->boolean('is_filler')->default(false);
            $table->timestampsTz();
            $table->unique(['scenario_id', 'user_id', 'role']);
            $table->index(['scenario_id', 'role']);
        });

        /*
         * Inventario de lo sembrado, en orden de creación: limpiar es recorrerlo al revés.
         *
         * `key_json` guarda las columnas que identifican la fila, no solo un id, porque en este esquema
         * hay tablas sin columna `id`: `career_subjects` y `subject_prerequisites` tienen clave compuesta,
         * `student_profiles` y `assignment_settings` usan una FK como primaria, y el pivote de roles de
         * Spatie se identifica con tres columnas. Con la clave completa, borrar no necesita casos especiales.
         */
        Schema::create('test_scenario_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_id')->constrained('test_scenarios')->cascadeOnDelete();
            $table->string('table_name', 64);
            $table->json('key_json');
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['scenario_id', 'id']);
        });

        // Checklist: los casos de prueba (CP-RFxx-NN) que cubre el escenario, con su resultado.
        Schema::create('test_scenario_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_id')->constrained('test_scenarios')->cascadeOnDelete();
            $table->string('case_id', 20);
            $table->text('statement');
            $table->string('actor_role', 30)->nullable();
            $table->string('status', 10)->default('pending');
            $table->text('note')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('checked_at')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();
            $table->unique(['scenario_id', 'case_id']);
            $table->index(['scenario_id', 'position']);
        });
        Adaptive::check('test_scenario_checks', 'test_scenario_checks_status', "status IN ('pending', 'pass', 'fail', 'blocked')");
    }

    public function down(): void
    {
        Schema::dropIfExists('test_scenario_checks');
        Schema::dropIfExists('test_scenario_objects');
        Schema::dropIfExists('test_scenario_actors');
        Schema::dropIfExists('test_scenarios');
    }
};
