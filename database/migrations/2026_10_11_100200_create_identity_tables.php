<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perfiles, asignaciones con vigencia, accesos, importaciones y dispositivos
 * (Modelo ER v2 · Identidad y acceso / Gestión académica). RF01, RF04, RF27.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 30)->default('users');
            $table->string('file_name');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->string('status', 15)->default('queued');
            $table->timestampTz('finished_at')->nullable();
            $table->timestampsTz();
        });

        Adaptive::check('import_batches', 'import_batches_status', "status IN ('queued', 'processing', 'finished', 'failed')");

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('import_batch_id')->nullable()->after('last_login_at')->constrained('import_batches')->restrictOnDelete();
        });
        Adaptive::restorePartialUnique('users', ['is_emergency'], 'is_emergency = true', 'users_single_emergency_unique');

        Schema::create('import_batch_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('field', 50)->nullable();
            $table->string('message');
            $table->json('raw_data')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('student_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->restrictOnDelete();
            $table->string('student_code', 20)->unique();
            $table->timestampsTz();
        });

        Adaptive::citext('student_profiles', 'student_code');

        Schema::create('teacher_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->timestampsTz();
        });

        Schema::create('department_heads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestampsTz();
        });

        Adaptive::partialUnique('department_heads', ['department_id'], 'valid_to IS NULL', 'department_heads_single_current_unique');

        Schema::create('career_coordinators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('career_id')->constrained()->restrictOnDelete();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestampsTz();
        });

        Adaptive::partialUnique('career_coordinators', ['career_id'], 'valid_to IS NULL', 'career_coordinators_single_current_unique');

        Schema::create('student_careers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('career_id')->constrained()->restrictOnDelete();
            $table->foreignId('study_plan_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('current_cycle')->default(1);
            $table->foreignId('admission_period_id')->nullable()->constrained('academic_periods')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestampsTz();
        });

        Adaptive::partialUnique('student_careers', ['student_id'], "status = 'active'", 'student_careers_single_active_unique');
        Adaptive::check('student_careers', 'student_careers_status', "status IN ('active', 'changed', 'graduated')");

        Schema::create('login_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('attempted_email');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('result', 10);
            $table->string('failure_reason', 50)->nullable();
            $table->timestampTz('occurred_at')->useCurrent()->index();
        });

        Adaptive::check('login_history', 'login_history_result', "result IN ('success', 'failure')");

        Schema::create('student_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->string('credential_id')->unique();
            $table->string('status', 15)->default('active');
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
        });

        Adaptive::partialUnique('student_devices', ['student_id'], "status = 'active'", 'student_devices_single_active_unique');
        Adaptive::check('student_devices', 'student_devices_status', "status IN ('active', 'revoked')");

        Schema::create('device_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('student_devices')->restrictOnDelete();
            $table->string('event_type', 20);
            $table->timestampTz('occurred_at')->useCurrent();
        });

        Schema::create('device_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->string('justification_path')->nullable();
            $table->string('status', 15)->default('pending');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
        });

        Adaptive::check('device_change_requests', 'device_change_requests_status', "status IN ('pending', 'approved', 'rejected')");
    }

    public function down(): void
    {
        Schema::dropIfExists('device_change_requests');
        Schema::dropIfExists('device_events');
        Schema::dropIfExists('student_devices');
        Schema::dropIfExists('login_history');
        Schema::dropIfExists('student_careers');
        Schema::dropIfExists('career_coordinators');
        Schema::dropIfExists('department_heads');
        Schema::dropIfExists('teacher_profiles');
        Schema::dropIfExists('student_profiles');
        Schema::dropIfExists('import_batch_errors');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('import_batch_id');
        });
        Adaptive::restorePartialUnique('users', ['is_emergency'], 'is_emergency = true', 'users_single_emergency_unique');
        Schema::dropIfExists('import_batches');
    }
};
