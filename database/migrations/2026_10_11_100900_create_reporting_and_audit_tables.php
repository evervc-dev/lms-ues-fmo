<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría, reportes e integraciones (Modelo ER v2 · Dashboards, reportes y auditoría).
 * RF05, RF25, RNF02, RNF09, ADR-027, ADR-029.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Particionada por mes en PostgreSQL; la escriben los triggers de auditoría.
        if (Adaptive::isPgsql()) {
            DB::unprepared(<<<'SQL'
                CREATE TABLE audit_logs (
                    id bigint GENERATED ALWAYS AS IDENTITY,
                    occurred_at timestamptz NOT NULL DEFAULT now(),
                    actor_user_id bigint REFERENCES users (id) ON DELETE RESTRICT,
                    department_id bigint REFERENCES departments (id) ON DELETE RESTRICT,
                    ip_address inet,
                    request_id varchar(64),
                    event_type varchar(60),
                    entity_type varchar(60) NOT NULL,
                    entity_id bigint,
                    action varchar(10) NOT NULL CHECK (action IN ('insert', 'update', 'delete')),
                    old_values jsonb,
                    new_values jsonb,
                    reason text,
                    PRIMARY KEY (id, occurred_at)
                ) PARTITION BY RANGE (occurred_at);
                CREATE TABLE audit_logs_default PARTITION OF audit_logs DEFAULT;
                CREATE INDEX audit_logs_entity_index ON audit_logs (entity_type, entity_id);
                CREATE INDEX audit_logs_actor_index ON audit_logs (actor_user_id, occurred_at);
                CREATE INDEX audit_logs_department_index ON audit_logs (department_id, occurred_at);
            SQL);
        } else {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->timestampTz('occurred_at')->useCurrent();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
                $table->string('ip_address', 45)->nullable();
                $table->string('request_id', 64)->nullable();
                $table->string('event_type', 60)->nullable();
                $table->string('entity_type', 60);
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->string('action', 10);
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->text('reason')->nullable();
                $table->index(['entity_type', 'entity_id']);
                $table->index(['department_id', 'occurred_at']);
            });
        }

        Schema::create('scheduled_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('report_type', 60);
            $table->json('filters')->nullable();
            $table->string('frequency', 15);
            $table->timestampTz('next_run_at');
            $table->timestampsTz();
        });

        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('export_type', 60);
            $table->string('path')->nullable();
            $table->string('status', 10)->default('queued');
            $table->timestampsTz();
        });

        Adaptive::check('exports', 'exports_status', "status IN ('queued', 'ready', 'failed')");

        Schema::create('google_sync_refs', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 60);
            $table->unsignedBigInteger('entity_id');
            $table->string('provider', 30)->default('google_calendar');
            $table->string('external_id')->nullable();
            $table->string('sync_status', 10)->default('pending');
            $table->text('last_error')->nullable();
            $table->timestampsTz();
            $table->unique(['entity_type', 'entity_id', 'provider']);
        });

        Adaptive::check('google_sync_refs', 'google_sync_refs_status', "sync_status IN ('pending', 'synced', 'failed')");

        Schema::create('integrations', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->text('settings');
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
        Schema::dropIfExists('google_sync_refs');
        Schema::dropIfExists('exports');
        Schema::dropIfExists('scheduled_reports');
        Schema::dropIfExists('audit_logs');
    }
};
