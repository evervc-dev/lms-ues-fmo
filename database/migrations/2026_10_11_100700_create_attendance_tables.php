<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asistencia presencial y virtual (Modelo ER v2 · Asistencia).
 * RF19–RF22, RF26, RF28, ADR-003 (reporte de Meet), ADR-028 (QR rotativo).
 * Sin coordenadas del estudiante: solo distancia, precisión y si estaba dentro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('area_type', 10)->default('circle');
            $table->decimal('center_lat', 10, 7)->nullable();
            $table->decimal('center_lng', 10, 7)->nullable();
            $table->unsignedInteger('radius_m')->nullable();
            $table->json('polygon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Adaptive::check('attendance_areas', 'attendance_areas_type', "area_type IN ('circle', 'polygon')");

        Schema::create('video_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->string('google_event_id')->nullable();
            $table->text('meet_url')->nullable();
            $table->string('topic');
            $table->timestampTz('starts_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('status', 15)->default('scheduled');
            $table->text('recording_url')->nullable();
            $table->timestampsTz();
        });

        Adaptive::check('video_sessions', 'video_sessions_status', "status IN ('scheduled', 'in_progress', 'finished')");
        Adaptive::check('video_sessions', 'video_sessions_duration', 'duration_minutes > 0');

        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('grading_component_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('attendance_areas')->restrictOnDelete();
            $table->foreignId('video_session_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('session_date');
            $table->string('modality', 10)->default('in_person');
            $table->string('status', 10)->default('open');
            $table->timestampTz('opened_at')->useCurrent();
            $table->unsignedSmallInteger('qr_validity_minutes')->default(15);
            $table->unsignedSmallInteger('qr_rotation_seconds')->default(10);
            $table->unsignedSmallInteger('late_after_minutes')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['id', 'section_id']);
        });

        Adaptive::check('attendance_sessions', 'attendance_sessions_validity', 'qr_validity_minutes BETWEEN 5 AND 15');
        Adaptive::check('attendance_sessions', 'attendance_sessions_rotation', 'qr_rotation_seconds BETWEEN 5 AND 30');
        Adaptive::check('attendance_sessions', 'attendance_sessions_modality', "modality IN ('in_person', 'virtual')");
        Adaptive::check('attendance_sessions', 'attendance_sessions_status', "status IN ('open', 'closed')");

        Schema::create('qr_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id');
            $table->foreignId('section_id');
            $table->foreignId('enrollment_id');
            $table->string('status', 10);
            $table->string('method', 10);
            $table->timestampTz('recorded_at')->useCurrent();
            $table->decimal('meet_percent', 5, 2)->nullable();
            $table->unsignedInteger('distance_m')->nullable();
            $table->unsignedInteger('accuracy_m')->nullable();
            $table->boolean('inside_area')->nullable();
            $table->foreignId('device_id')->nullable()->constrained('student_devices')->restrictOnDelete();
            $table->foreignId('corrected_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('correction_reason')->nullable();
            $table->timestampsTz();
            $table->unique(['session_id', 'enrollment_id']);
            // La asistencia, su sesión y la matrícula son de la misma sección.
            $table->foreign(['session_id', 'section_id'])->references(['id', 'section_id'])->on('attendance_sessions')->restrictOnDelete();
            $table->foreign(['enrollment_id', 'section_id'])->references(['id', 'section_id'])->on('enrollments')->restrictOnDelete();
        });

        Adaptive::check('attendance_records', 'attendance_records_status', "status IN ('present', 'late', 'absent', 'justified')");
        Adaptive::check('attendance_records', 'attendance_records_method', "method IN ('qr', 'code', 'manual', 'import', 'meet')");
        Adaptive::check('attendance_records', 'attendance_records_correction', 'corrected_by IS NULL OR correction_reason IS NOT NULL');

        Schema::create('attendance_scan_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('attendance_sessions')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->string('result', 20);
            $table->unsignedInteger('distance_m')->nullable();
            $table->timestampTz('occurred_at')->useCurrent();
            $table->index(['session_id', 'student_id']);
        });

        Adaptive::check('attendance_scan_attempts', 'attendance_scan_attempts_result', "result IN ('ok', 'no_location', 'token_expired', 'session_closed', 'not_enrolled', 'already_recorded', 'out_of_range', 'invalid_device', 'scan_limit')");

        Schema::create('temporary_attendance_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('attendance_sessions')->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->string('code_hash', 64);
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
        });

        Adaptive::partialUnique('temporary_attendance_codes', ['session_id', 'enrollment_id'], 'used_at IS NULL', 'temporary_attendance_codes_single_unused_unique');

        Schema::create('attendance_justifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_id')->constrained('attendance_records')->restrictOnDelete();
            $table->text('reason');
            $table->string('file_path')->nullable();
            $table->string('status', 10)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();
        });

        Adaptive::check('attendance_justifications', 'attendance_justifications_status', "status IN ('pending', 'accepted', 'rejected')");

        Schema::create('meet_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_session_id')->constrained()->restrictOnDelete();
            $table->string('file_path');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('confirmed_at')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestampsTz();
        });

        Adaptive::partialUnique('meet_reports', ['video_session_id'], 'is_current = true', 'meet_reports_single_current_unique');

        Schema::create('meet_report_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meet_report_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('minutes');
            $table->foreignId('enrollment_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 15)->default('unmatched');
        });

        Adaptive::citext('meet_report_rows', 'email');
        Adaptive::check('meet_report_rows', 'meet_report_rows_status', "status IN ('matched', 'unmatched', 'discarded')");
    }

    public function down(): void
    {
        Schema::dropIfExists('meet_report_rows');
        Schema::dropIfExists('meet_reports');
        Schema::dropIfExists('attendance_justifications');
        Schema::dropIfExists('temporary_attendance_codes');
        Schema::dropIfExists('attendance_scan_attempts');
        Schema::dropIfExists('attendance_records');
        Schema::dropIfExists('qr_tokens');
        Schema::dropIfExists('attendance_sessions');
        Schema::dropIfExists('video_sessions');
        Schema::dropIfExists('attendance_areas');
    }
};
