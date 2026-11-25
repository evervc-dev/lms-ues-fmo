<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carrera a la que pertenece el grupo teórico (ADR-074). Se elige al crearlo; el Coordinador solo ve los grupos de su
 * carrera, aunque la materia esté en planes de varias. Nullable: los grupos anteriores no la tienen y el Coordinador
 * los sigue viendo si la materia está en el plan de su carrera.
 *
 * La FK va solo en PostgreSQL (ADR-033): en SQLite, agregar una FK a una tabla existente la reconstruye, y la vista
 * v_section_capacity depende de `sections`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $columna = $table->foreignId('career_id')->nullable()->after('subject_id');
            if (Adaptive::isPgsql()) {
                $columna->constrained('careers')->restrictOnDelete();
            } else {
                $table->index('career_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            if (Adaptive::isPgsql()) {
                $table->dropConstrainedForeignId('career_id');
            } else {
                $table->dropIndex(['career_id']);
                $table->dropColumn('career_id');
            }
        });
    }
};
