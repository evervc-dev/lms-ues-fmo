<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calificaciones, actas, cierre y parámetros (Modelo ER v2 · Calificaciones y progreso).
 * RF17, RF18, RF22, RF34, ADR-030, ADR-032.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('name', 100);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('weight_percent', 5, 2);
            $table->timestampsTz();
            $table->unique(['section_id', 'number']);
        });

        Adaptive::check('grading_components', 'grading_components_weight', 'weight_percent > 0 AND weight_percent <= 100');
        Adaptive::check('grading_components', 'grading_components_dates', 'ends_on >= starts_on');

        Schema::create('grade_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained('grading_components')->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_type', 15);
            $table->string('title');
            $table->decimal('weight_percent', 5, 2);
            $table->boolean('is_published')->default(false);
            $table->timestampsTz();
            $table->unique(['id', 'section_id']);
        });

        Adaptive::check('grade_items', 'grade_items_type', "item_type IN ('assignment', 'quiz', 'manual')");
        Adaptive::check('grade_items', 'grade_items_weight', 'weight_percent > 0 AND weight_percent <= 100');

        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_item_id');
            $table->foreignId('enrollment_id');
            $table->foreignId('section_id');
            $table->decimal('score', 5, 2)->nullable();
            $table->decimal('score_before_penalty', 5, 2)->nullable();
            $table->string('status', 15)->default('draft');
            $table->text('comment')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
            $table->unique(['grade_item_id', 'enrollment_id']);
            // La nota, su actividad y la matrícula son de la misma sección.
            $table->foreign(['grade_item_id', 'section_id'])->references(['id', 'section_id'])->on('grade_items')->restrictOnDelete();
            $table->foreign(['enrollment_id', 'section_id'])->references(['id', 'section_id'])->on('enrollments')->restrictOnDelete();
        });

        Adaptive::check('grades', 'grades_score_range', 'score IS NULL OR (score >= 0 AND score <= 10)');
        Adaptive::check('grades', 'grades_status', "status IN ('draft', 'published')");

        // Acta de notas por sección (RF34).
        Schema::create('grade_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 15)->default('draft');
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('validated_at')->nullable();
            $table->text('return_reason')->nullable();
            $table->timestampsTz();
        });

        Adaptive::check('grade_sheets', 'grade_sheets_status', "status IN ('draft', 'published', 'returned', 'validated')");

        // Autorización departamental del cierre (RF34).
        Schema::create('department_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('academic_periods')->restrictOnDelete();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('authorized_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('authorized_at')->useCurrent();
            $table->timestampsTz();
            $table->unique(['period_id', 'department_id']);
        });

        Schema::create('enrollment_results', function (Blueprint $table) {
            $table->foreignId('enrollment_id')->primary()->constrained()->restrictOnDelete();
            $table->decimal('final_score', 4, 1);
            $table->string('result', 10);
            $table->json('policy_snapshot');
            $table->timestampTz('frozen_at')->useCurrent();
        });

        Adaptive::check('enrollment_results', 'enrollment_results_result', "result IN ('passed', 'failed')");
        Adaptive::check('enrollment_results', 'enrollment_results_score', 'final_score >= 0 AND final_score <= 10');

        Schema::create('academic_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->string('alert_type', 20);
            $table->foreignId('grading_component_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestampTz('opened_at')->useCurrent();
            $table->timestampTz('notified_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
        });

        Adaptive::partialUnique('academic_alerts', ['enrollment_id', 'alert_type', 'COALESCE(grading_component_id, 0)'], 'closed_at IS NULL', 'academic_alerts_single_open_unique');
        Adaptive::check('academic_alerts', 'academic_alerts_type', "alert_type IN ('grade_risk', 'attendance_85', 'attendance_78', 'attendance_75')");

        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->json('value');
            $table->boolean('is_confirmed')->default(false);
            $table->text('description')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('academic_alerts');
        Schema::dropIfExists('enrollment_results');
        Schema::dropIfExists('department_closures');
        Schema::dropIfExists('grade_sheets');
        Schema::dropIfExists('grades');
        Schema::dropIfExists('grade_items');
        Schema::dropIfExists('grading_components');
    }
};
