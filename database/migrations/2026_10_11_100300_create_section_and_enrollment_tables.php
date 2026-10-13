<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Oferta, horarios, asignación docente y matrícula (Modelo ER v2 · Gestión académica).
 * RF08, RF09, RF29, RF32, ADR-030, ADR-032.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('period_id')->constrained('academic_periods')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->string('code', 20);
            $table->unsignedSmallInteger('min_capacity')->default(0);
            $table->unsignedSmallInteger('max_capacity');
            $table->string('status', 15)->default('open');
            $table->timestampsTz();
            $table->unique(['period_id', 'subject_id', 'code']);
        });

        Adaptive::check('sections', 'sections_capacity', 'max_capacity > 0 AND min_capacity <= max_capacity');
        Adaptive::check('sections', 'sections_status', "status IN ('open', 'closed', 'cancelled')");

        // Clave de matrícula por asignatura y período (ADR-032).
        Schema::create('enrollment_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('period_id')->constrained('academic_periods')->restrictOnDelete();
            $table->string('key_hash');
            $table->timestampTz('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Adaptive::partialUnique('enrollment_keys', ['subject_id', 'period_id'], 'is_active = true', 'enrollment_keys_single_active_unique');

        Schema::create('section_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('academic_periods')->restrictOnDelete();
            $table->foreignId('classroom_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestampsTz();
            $table->index(['period_id', 'weekday']);
        });

        Adaptive::check('section_schedules', 'section_schedules_weekday', 'weekday BETWEEN 1 AND 7');
        Adaptive::check('section_schedules', 'section_schedules_times', 'end_time > start_time');

        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->restrictOnDelete();
            $table->boolean('is_responsible')->default(false);
            $table->timestampTz('started_at')->useCurrent();
            $table->timestampTz('ended_at')->nullable();
            $table->timestampsTz();
            $table->index(['teacher_id', 'ended_at']);
        });

        Adaptive::partialUnique('teaching_assignments', ['section_id'], 'is_responsible = true AND ended_at IS NULL', 'teaching_assignments_single_responsible_unique');
        Adaptive::partialUnique('teaching_assignments', ['section_id', 'teacher_id'], 'ended_at IS NULL', 'teaching_assignments_current_unique');

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('period_id')->constrained('academic_periods')->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->string('status', 15)->default('active');
            $table->string('enrolled_via', 20);
            $table->foreignId('enrolled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('transferred_to_id')->nullable()->constrained('enrollments')->restrictOnDelete();
            $table->timestampsTz();
            // Destino de las FK compuestas (notas, asistencia y entregas de la misma sección).
            $table->unique(['id', 'section_id']);
            $table->index(['section_id', 'status']);
        });

        Adaptive::partialUnique('enrollments', ['student_id', 'period_id', 'subject_id'], "status = 'active'", 'enrollments_single_active_unique');
        Adaptive::check('enrollments', 'enrollments_status', "status IN ('active', 'withdrawn', 'transferred')");
        Adaptive::check('enrollments', 'enrollments_via', "enrolled_via IN ('key', 'teacher', 'coordinator', 'head', 'admin', 'import', 'group_change')");

        if (Adaptive::isPgsql()) {
            // La sección debe ser de la misma materia y período que la matrícula.
            Schema::table('sections', fn (Blueprint $table) => $table->unique(['id', 'period_id', 'subject_id']));
            DB::statement('ALTER TABLE enrollments ADD CONSTRAINT enrollments_section_context_fk FOREIGN KEY (section_id, period_id, subject_id) REFERENCES sections (id, period_id, subject_id) ON DELETE RESTRICT');
        }

        Schema::create('enrollment_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->string('change_type', 20);
            $table->text('reason')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('occurred_at')->useCurrent();
        });

        Adaptive::check('enrollment_changes', 'enrollment_changes_type', "change_type IN ('enrolled', 'withdrawn', 'transferred')");

        Schema::create('section_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('target_section_id')->constrained('sections')->restrictOnDelete();
            $table->text('justification');
            $table->string('status', 15)->default('pending');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
        });

        Adaptive::check('section_change_requests', 'section_change_requests_status', "status IN ('pending', 'approved', 'rejected')");

        Schema::create('class_cancellations', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 15);
            $table->foreignId('section_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('career_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('kind', 20);
            $table->string('new_modality', 20)->nullable();
            $table->text('reason');
            $table->string('status', 15)->default('requested');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
        });

        Adaptive::check('class_cancellations', 'class_cancellations_scope', "scope IN ('section', 'career', 'faculty')");
        Adaptive::check('class_cancellations', 'class_cancellations_kind', "kind IN ('cancellation', 'modality_change')");
        Adaptive::check('class_cancellations', 'class_cancellations_status', "status IN ('requested', 'approved', 'rejected')");
    }

    public function down(): void
    {
        Schema::dropIfExists('class_cancellations');
        Schema::dropIfExists('section_change_requests');
        Schema::dropIfExists('enrollment_changes');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('teaching_assignments');
        Schema::dropIfExists('section_schedules');
        Schema::dropIfExists('enrollment_keys');
        Schema::dropIfExists('sections');
    }
};
