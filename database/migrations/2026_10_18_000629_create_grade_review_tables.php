<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitud de revisión de una calificación publicada (RF35, ADR-040): la solicitud y los ítems que el estudiante
 * impugna (preguntas del intento o criterios de la rúbrica; ninguno = nota general), y las prórrogas que el docente
 * da por estudiante y actividad (entrega de la tarea o plazo para pedir revisión). Auditadas en PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_review_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid()->unique();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->foreignId('assessment_id')->constrained()->restrictOnDelete();
            $table->foreignId('quiz_attempt_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->text('reason');
            $table->decimal('score_before', 5, 2);
            $table->decimal('score_after', 5, 2)->nullable();
            $table->text('response')->nullable();
            $table->timestampTz('requested_at')->useCurrent();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
            $table->index(['section_id', 'status']);
            // Una sola solicitud por actividad y matrícula; el estudiante no la retira.
            $table->unique(['enrollment_id', 'assessment_id'], 'grade_review_requests_one_per_assessment');
        });

        Adaptive::check('grade_review_requests', 'grade_review_requests_status', "status IN ('pending', 'resolved_changed', 'resolved_unchanged')");

        Schema::create('grade_review_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('grade_review_requests')->restrictOnDelete();
            // section_id para que fn_audit_department ubique el departamento (auditoría del Jefe con RLS, ADR-029).
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('attempt_answer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('rubric_criterion_id')->nullable()->constrained('rubric_criteria')->restrictOnDelete();
            $table->text('student_comment');
            $table->string('decision', 10)->nullable();
            $table->text('teacher_response')->nullable();
            $table->decimal('points_before', 5, 2)->nullable();
            $table->decimal('points_after', 5, 2)->nullable();
            $table->timestampsTz();
        });

        Adaptive::check('grade_review_items', 'grade_review_items_target', 'attempt_answer_id IS NULL OR rubric_criterion_id IS NULL');
        Adaptive::check('grade_review_items', 'grade_review_items_decision', "decision IS NULL OR decision IN ('kept', 'changed')");

        Schema::create('assessment_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('assessment_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->string('kind', 12);
            $table->timestampTz('until');
            $table->text('reason');
            $table->foreignId('granted_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['assessment_id', 'enrollment_id', 'kind']);
        });

        Adaptive::check('assessment_extensions', 'assessment_extensions_kind', "kind IN ('submission', 'review')");

        $ahora = now();
        DB::table('notification_events')->insertOrIgnore([
            ['code' => 'grade_review.requested', 'description' => 'Solicitud de revisión de una nota', 'created_at' => $ahora, 'updated_at' => $ahora],
            ['code' => 'grade_review.resolved', 'description' => 'Solicitud de revisión resuelta', 'created_at' => $ahora, 'updated_at' => $ahora],
            ['code' => 'extension.granted', 'description' => 'Prórroga concedida', 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);

        Adaptive::pgsqlFile('08_audit_grade_reviews.sql');
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_extensions');
        Schema::dropIfExists('grade_review_items');
        Schema::dropIfExists('grade_review_requests');
        // Solo los eventos sin avisos: notifications.event_code los referencia con restrictOnDelete.
        DB::table('notification_events')->whereIn('code', ['grade_review.requested', 'grade_review.resolved', 'extension.granted'])
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('notifications')->whereColumn('notifications.event_code', 'notification_events.code'))->delete();
    }
};
