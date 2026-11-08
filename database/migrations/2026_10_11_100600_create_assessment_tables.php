<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tareas, rúbricas, bancos y cuestionarios (Modelo ER v2 · Actividades y evaluaciones).
 * RF15, RF16, ADR-008, ADR-009, ADR-032 (calificación anónima).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->timestampsTz();
        });

        Schema::create('rubric_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rubric_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('max_points', 6, 2);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();
        });

        Schema::create('rubric_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('criterion_id')->constrained('rubric_criteria')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('points', 6, 2);
            $table->timestampsTz();
        });

        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('grade_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('assessment_type', 15);
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->timestampTz('opens_at');
            $table->timestampTz('closes_at');
            $table->unsignedSmallInteger('max_attempts')->default(1);
            $table->foreignId('rubric_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_anonymous')->default(false);
            $table->timestampsTz();
            $table->unique(['id', 'section_id']);
        });

        Adaptive::check('assessments', 'assessments_type', "assessment_type IN ('assignment', 'quiz')");
        Adaptive::check('assessments', 'assessments_window', 'closes_at > opens_at');
        Adaptive::check('assessments', 'assessments_attempts', 'max_attempts >= 1');

        Schema::create('assignment_settings', function (Blueprint $table) {
            $table->foreignId('assessment_id')->primary()->constrained()->cascadeOnDelete();
            $table->boolean('is_group')->default(false);
            $table->string('late_policy', 15)->default('strict');
            $table->timestampTz('grace_until')->nullable();
            $table->decimal('late_penalty_percent', 5, 2)->default(0);
            $table->json('allowed_extensions');
            $table->unsignedInteger('max_file_mb')->default(10);
        });

        Adaptive::check('assignment_settings', 'assignment_settings_policy', "late_policy IN ('strict', 'grace', 'grace_penalty')");
        Adaptive::check('assignment_settings', 'assignment_settings_penalty', 'late_penalty_percent >= 0 AND late_penalty_percent <= 100');

        Schema::create('quiz_settings', function (Blueprint $table) {
            $table->foreignId('assessment_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->unsignedSmallInteger('autosave_seconds')->default(30);
            $table->string('navigation', 10)->default('free');
            $table->boolean('review_published')->default(false);
        });

        Adaptive::check('quiz_settings', 'quiz_settings_navigation', "navigation IN ('free', 'linear')");

        // Equipos por tarea (ADR-022/031).
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->timestampsTz();
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->primary(['team_id', 'enrollment_id']);
        });

        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('assessment_id');
            $table->foreignId('section_id');
            $table->foreignId('enrollment_id')->nullable();
            $table->foreignId('team_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->string('status', 15)->default('submitted');
            $table->boolean('is_late')->default(false);
            $table->decimal('late_penalty_applied', 5, 2)->default(0);
            $table->timestampTz('submitted_at')->useCurrent();
            $table->timestampsTz();
            $table->foreign(['assessment_id', 'section_id'])->references(['id', 'section_id'])->on('assessments')->restrictOnDelete();
            $table->foreign(['enrollment_id', 'section_id'])->references(['id', 'section_id'])->on('enrollments')->restrictOnDelete();
        });

        Adaptive::partialUnique('submissions', ['assessment_id', 'enrollment_id', 'attempt_number'], 'enrollment_id IS NOT NULL', 'submissions_individual_attempt_unique');
        Adaptive::partialUnique('submissions', ['assessment_id', 'team_id', 'attempt_number'], 'team_id IS NOT NULL', 'submissions_team_attempt_unique');
        Adaptive::check('submissions', 'submissions_owner', 'num_nonnulls(enrollment_id, team_id) = 1');
        Adaptive::check('submissions', 'submissions_status', "status IN ('submitted', 'cancelled')");

        Schema::create('submission_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->unsignedBigInteger('bytes');
            $table->timestampsTz();
        });

        Schema::create('submission_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->restrictOnDelete();
            $table->string('event_type', 15);
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('occurred_at')->useCurrent();
        });

        Schema::create('submission_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->text('comment');
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
        });

        Schema::create('submission_rubric_scores', function (Blueprint $table) {
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('criterion_id')->constrained('rubric_criteria')->restrictOnDelete();
            $table->foreignId('level_id')->nullable()->constrained('rubric_levels')->restrictOnDelete();
            $table->decimal('points', 6, 2);
            $table->primary(['submission_id', 'criterion_id']);
        });

        // Bancos del docente, compartibles (ADR-031).
        Schema::create('question_banks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->timestampsTz();
        });

        Schema::create('question_bank_shares', function (Blueprint $table) {
            $table->foreignId('bank_id')->constrained('question_banks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->primary(['bank_id', 'user_id']);
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->constrained('question_banks')->restrictOnDelete();
            $table->string('question_type', 20);
            $table->text('statement');
            $table->string('topic', 100)->nullable();
            $table->string('difficulty', 10)->nullable();
            $table->json('accepted_answers')->nullable();
            $table->text('justification')->nullable();
            $table->timestampsTz();
            $table->index(['bank_id', 'topic', 'difficulty']);
        });

        Adaptive::check('questions', 'questions_type', "question_type IN ('multiple_choice', 'true_false', 'short_answer', 'essay')");

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->text('text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();
        });

        Schema::create('quiz_question_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_id')->constrained('question_banks')->restrictOnDelete();
            $table->string('topic', 100)->nullable();
            $table->string('difficulty', 10)->nullable();
            $table->unsignedSmallInteger('quantity');
            $table->timestampsTz();
        });

        Adaptive::check('quiz_question_rules', 'quiz_question_rules_quantity', 'quantity >= 1');

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('assessment_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->timestampTz('started_at')->useCurrent();
            $table->timestampTz('deadline_at');
            $table->timestampTz('submitted_at')->nullable();
            $table->string('status', 20)->default('in_progress');
            $table->decimal('score', 5, 2)->nullable();
            $table->timestampsTz();
            $table->unique(['assessment_id', 'enrollment_id', 'attempt_number']);
        });

        Adaptive::partialUnique('quiz_attempts', ['assessment_id', 'enrollment_id'], "status = 'in_progress'", 'quiz_attempts_single_in_progress_unique');
        Adaptive::check('quiz_attempts', 'quiz_attempts_status', "status IN ('in_progress', 'submitted', 'pending_grading', 'graded')");

        Schema::create('attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->foreignId('selected_option_id')->nullable()->constrained('question_options')->restrictOnDelete();
            $table->text('answer_text')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->timestampTz('saved_at')->nullable();
            $table->unique(['attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_answers');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_question_rules');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('question_bank_shares');
        Schema::dropIfExists('question_banks');
        Schema::dropIfExists('submission_rubric_scores');
        Schema::dropIfExists('submission_feedback');
        Schema::dropIfExists('submission_events');
        Schema::dropIfExists('submission_files');
        Schema::dropIfExists('submissions');
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('quiz_settings');
        Schema::dropIfExists('assignment_settings');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('rubric_levels');
        Schema::dropIfExists('rubric_criteria');
        Schema::dropIfExists('rubrics');
    }
};
