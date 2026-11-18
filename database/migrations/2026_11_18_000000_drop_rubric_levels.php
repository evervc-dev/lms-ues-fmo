<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La rúbrica deja de tener niveles (ADR-065, enmienda del 18/11/2026): el docente escribe los puntos de cada
 * criterio, de 0 a su máximo, en vez de elegir Excelente, Bueno o Regular.
 *
 * Las calificaciones ya puestas no se pierden: `submission_rubric_scores.points` siempre guardó los puntos y
 * `level_id` era solo la etiqueta del nivel elegido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submission_rubric_scores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('level_id');
        });

        Schema::dropIfExists('rubric_levels');
    }

    public function down(): void
    {
        Schema::create('rubric_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('criterion_id')->constrained('rubric_criteria')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('points', 6, 2);
            $table->timestampsTz();
        });

        Schema::table('submission_rubric_scores', function (Blueprint $table) {
            $table->foreignId('level_id')->nullable()->constrained('rubric_levels')->restrictOnDelete();
        });
    }
};
