<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha límite de inscripción (RF29, ADR-051): por período y departamento, con la prórroga del Jefe, y las prórrogas
 * por estudiante. Sin fila del departamento rige «inicio del período + enrollment_deadline_weeks_default».
 * Auditadas en PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_deadlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('academic_periods')->restrictOnDelete();
            // department_id también deja que fn_audit_department ubique el departamento (auditoría del Jefe con RLS, ADR-029).
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->date('deadline');
            $table->date('extended_until')->nullable();
            $table->text('extension_reason')->nullable();
            $table->foreignId('set_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('extended_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['period_id', 'department_id']);
        });

        Adaptive::check('enrollment_deadlines', 'enrollment_deadlines_extension', 'extended_until IS NULL OR extended_until > deadline');

        Schema::create('enrollment_extensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('academic_periods')->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->date('until');
            $table->text('reason');
            $table->foreignId('granted_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->unique(['period_id', 'student_id']);
        });

        Adaptive::pgsqlFile('12_audit_enrollment_deadlines.sql');
    }

    public function down(): void
    {
        if (Adaptive::isPgsql()) {
            DB::statement('DROP TRIGGER IF EXISTS enrollment_extensions_audit ON enrollment_extensions');
            DB::statement('DROP TRIGGER IF EXISTS enrollment_deadlines_audit ON enrollment_deadlines');
        }

        Schema::dropIfExists('enrollment_extensions');
        Schema::dropIfExists('enrollment_deadlines');
    }
};
