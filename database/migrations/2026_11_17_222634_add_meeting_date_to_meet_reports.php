<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha de la reunión detectada en el reporte de Meet (RF21).
 *
 * El informe que genera Google Meet no siempre trae la fecha en el contenido: la exportación en español solo da la
 * hora («9:51 p. m.») y la fecha vive en el nombre del archivo. Se guarda la que se haya podido detectar, o null si
 * no se encontró ninguna, para que la vista previa lo advierta al docente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meet_reports', function (Blueprint $table) {
            $table->date('meeting_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('meet_reports', function (Blueprint $table) {
            $table->dropColumn('meeting_date');
        });
    }
};
