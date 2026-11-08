<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aula virtual (Modelo ER v2 · Contenidos). RF11–RF14, RF18.
 * Soft delete solo en units y resources (papelera de 30 días, ADR-031).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('resource_type', 15);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->text('url')->nullable();
            $table->longText('html_content')->nullable();
            $table->string('pdf_mode', 10)->nullable();
            $table->string('completion_mode', 10)->default('manual');
            $table->json('completion_rule')->nullable();
            $table->foreignId('source_resource_id')->nullable()->constrained('resources')->nullOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        Adaptive::check('resources', 'resources_type', "resource_type IN ('file', 'folder', 'page', 'link', 'video')");
        Adaptive::check('resources', 'resources_completion_mode', "completion_mode IN ('manual', 'automatic')");
        Adaptive::check('resources', 'resources_pdf_mode', "pdf_mode IS NULL OR pdf_mode IN ('download', 'viewer')");

        Schema::create('resource_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('name');
            $table->unsignedBigInteger('bytes');
            $table->timestampsTz();
        });

        // Particionada por mes en PostgreSQL (RNF03, volumen ~500 mil filas por período).
        if (Adaptive::isPgsql()) {
            DB::unprepared(<<<'SQL'
                CREATE TABLE resource_access_logs (
                    id bigint GENERATED ALWAYS AS IDENTITY,
                    resource_id bigint NOT NULL REFERENCES resources (id) ON DELETE RESTRICT,
                    enrollment_id bigint NOT NULL REFERENCES enrollments (id) ON DELETE RESTRICT,
                    event_type varchar(10) NOT NULL CHECK (event_type IN ('open', 'download')),
                    duration_seconds integer NOT NULL DEFAULT 0,
                    occurred_at timestamptz NOT NULL DEFAULT now(),
                    PRIMARY KEY (id, occurred_at)
                ) PARTITION BY RANGE (occurred_at);
                CREATE TABLE resource_access_logs_default PARTITION OF resource_access_logs DEFAULT;
                CREATE INDEX resource_access_logs_resource_enrollment_index ON resource_access_logs (resource_id, enrollment_id);
            SQL);
        } else {
            Schema::create('resource_access_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('resource_id')->constrained()->restrictOnDelete();
                $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
                $table->string('event_type', 10);
                $table->unsignedInteger('duration_seconds')->default(0);
                $table->timestampTz('occurred_at')->useCurrent();
                $table->index(['resource_id', 'enrollment_id']);
            });
        }

        Schema::create('resource_progress', function (Blueprint $table) {
            $table->foreignId('resource_id')->constrained()->restrictOnDelete();
            $table->foreignId('enrollment_id')->constrained()->restrictOnDelete();
            $table->string('origin', 10);
            $table->timestampTz('completed_at')->useCurrent();
            $table->primary(['resource_id', 'enrollment_id']);
        });

        Schema::create('content_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_section_id')->constrained('sections')->restrictOnDelete();
            $table->foreignId('target_section_id')->constrained('sections')->restrictOnDelete();
            $table->foreignId('copied_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_copies');
        Schema::dropIfExists('resource_progress');
        Schema::dropIfExists('resource_access_logs');
        Schema::dropIfExists('resource_files');
        Schema::dropIfExists('resources');
        Schema::dropIfExists('units');
    }
};
