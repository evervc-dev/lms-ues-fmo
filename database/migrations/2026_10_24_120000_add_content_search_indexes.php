<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices de la búsqueda de materiales (RF11, CP-RF11-04). La consulta real une resources con units,
 * filtra por units.section_id y busca en resources.title, así que el índice va por unidad y título.
 * Adaptativa (ADR-033): la misma sintaxis sirve en SQLite y en PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table): void {
            $table->index(['unit_id', 'title'], 'resources_unit_id_title_index');
        });

        Schema::table('units', function (Blueprint $table): void {
            $table->index(['section_id', 'position'], 'units_section_id_position_index');
        });
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table): void {
            $table->dropIndex('resources_unit_id_title_index');
        });

        Schema::table('units', function (Blueprint $table): void {
            $table->dropIndex('units_section_id_position_index');
        });
    }
};
