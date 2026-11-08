<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estructura institucional y catálogo (Modelo ER v2 · Gestión académica). RF06, RF07.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->timestampsTz();
        });

        Schema::create('careers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->timestampsTz();
        });

        Schema::create('study_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('career_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->timestampsTz();
            $table->unique(['career_id', 'name']);
        });

        Schema::create('academic_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('planned');
            $table->string('preparation_stage', 30)->default('planning');
            $table->unsignedSmallInteger('group_change_weeks')->default(3);
            $table->timestampsTz();
        });

        Adaptive::partialUnique('academic_periods', ['status'], "status = 'active'", 'academic_periods_single_active_unique');
        Adaptive::check('academic_periods', 'academic_periods_dates', 'end_date > start_date');
        Adaptive::check('academic_periods', 'academic_periods_status', "status IN ('planned', 'active', 'closed')");
        Adaptive::check('academic_periods', 'academic_periods_stage', "preparation_stage IN ('planning', 'schedule_definition', 'teacher_assignment', 'ready')");

        Schema::create('period_calendar_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('academic_periods')->restrictOnDelete();
            $table->string('title');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('grading_component_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('academic_periods')->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('name', 100);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('weight_percent', 5, 2);
            $table->timestampsTz();
            $table->unique(['period_id', 'number']);
        });

        Adaptive::check('grading_component_templates', 'grading_component_templates_weight', 'weight_percent > 0 AND weight_percent <= 100');

        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('credits');
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Adaptive::citext('subjects', 'code');
        Adaptive::check('subjects', 'subjects_credits', 'credits > 0');

        Schema::create('subject_prerequisites', function (Blueprint $table) {
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->foreignId('prerequisite_id')->constrained('subjects')->restrictOnDelete();
            $table->primary(['subject_id', 'prerequisite_id']);
        });

        Adaptive::check('subject_prerequisites', 'subject_prerequisites_not_self', 'subject_id <> prerequisite_id');

        Schema::create('career_subjects', function (Blueprint $table) {
            $table->foreignId('study_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('subject_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('cycle');
            $table->primary(['study_plan_id', 'subject_id']);
        });

        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->unsignedSmallInteger('capacity');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classrooms');
        Schema::dropIfExists('career_subjects');
        Schema::dropIfExists('subject_prerequisites');
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('grading_component_templates');
        Schema::dropIfExists('period_calendar_events');
        Schema::dropIfExists('academic_periods');
        Schema::dropIfExists('study_plans');
        Schema::dropIfExists('careers');
        Schema::dropIfExists('departments');
    }
};
