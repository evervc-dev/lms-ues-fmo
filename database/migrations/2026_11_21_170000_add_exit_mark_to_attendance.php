<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca de salida en clase presencial (ADR-079): la ventana de salida de la sesión y la hora en que cada estudiante
 * marcó su salida. Columnas nulas en SQLite y PostgreSQL (ADR-033); las auditan los triggers que ya tienen ambas tablas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->timestampTz('exit_opened_at')->nullable()->after('closed_at');
        });
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->timestampTz('exit_recorded_at')->nullable()->after('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn('exit_recorded_at');
        });
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropColumn('exit_opened_at');
        });
    }
};
