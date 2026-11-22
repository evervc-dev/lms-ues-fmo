<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-087: la clave de matrícula se guarda también cifrada con la APP_KEY, para que quien la gestiona la pueda volver
 * a consultar. key_hash sigue siendo con lo que se verifica al inscribirse. Las claves anteriores quedan sin cifrar
 * (null): no se pueden recuperar y se cambian si hace falta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollment_keys', function (Blueprint $table) {
            $table->text('key_encrypted')->nullable()->after('key_hash');
        });
    }

    public function down(): void
    {
        Schema::table('enrollment_keys', function (Blueprint $table) {
            $table->dropColumn('key_encrypted');
        });
    }
};
