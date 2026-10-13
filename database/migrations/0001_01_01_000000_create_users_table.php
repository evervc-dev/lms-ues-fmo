<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identidad base (docs/07 Datos/Modelo entidad-relación.md · Identidad y acceso).
 * Inicio de sesión solo con Google @ues.edu.sv (ADR-007): sin tabla de recuperación de contraseña.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Extensiones que usan las migraciones siguientes en PostgreSQL (citext, EXCLUDE con btree_gist).
        Adaptive::pgsqlFile('00_extensions.sql');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('institutional_email')->unique();
            $table->string('google_sub')->nullable()->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestampTz('deactivated_at')->nullable();
            $table->foreignId('deactivated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('last_login_at')->nullable();
            $table->text('google_calendar_token')->nullable();
            $table->boolean('is_emergency')->default(false);
            $table->string('password')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->rememberToken();
            $table->timestampsTz();
        });

        Adaptive::partialUnique('users', ['is_emergency'], 'is_emergency = true', 'users_single_emergency_unique');
        Adaptive::citext('users', 'institutional_email');
        Adaptive::check('users', 'users_institutional_email_domain', "institutional_email LIKE '%@ues.edu.sv'");
        Adaptive::check('users', 'users_password_only_emergency', 'password IS NULL OR is_emergency');

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
