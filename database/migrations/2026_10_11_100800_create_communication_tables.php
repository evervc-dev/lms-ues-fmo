<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notificaciones, foros y mensajería condicional (Modelo ER v2 · Comunicación y notificaciones).
 * RF23, RF30, RF31, RNF12. `notifications` es compatible con el canal database de Laravel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_events', function (Blueprint $table) {
            $table->string('code', 60)->primary();
            $table->string('description');
            $table->timestampsTz();
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_code', 60);
            $table->boolean('mail_enabled')->default(true);
            $table->primary(['user_id', 'event_code']);
            $table->foreign('event_code')->references('code')->on('notification_events')->restrictOnDelete();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->string('event_code', 60)->nullable();
            $table->string('dedup_key', 191)->nullable();
            $table->json('data');
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();
            $table->foreign('event_code')->references('code')->on('notification_events')->restrictOnDelete();
        });

        // RNF12: la misma notificación no llega dos veces al mismo usuario.
        Adaptive::partialUnique('notifications', ['notifiable_type', 'notifiable_id', 'dedup_key'], 'dedup_key IS NOT NULL', 'notifications_dedup_unique');

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('notification_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 15);
            $table->string('status', 10)->default('queued');
            $table->timestampTz('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestampsTz();
            $table->unique(['notification_id', 'channel']);
        });

        Adaptive::check('notification_deliveries', 'notification_deliveries_channel', "channel IN ('database', 'broadcast', 'mail')");
        Adaptive::check('notification_deliveries', 'notification_deliveries_status', "status IN ('queued', 'sent', 'failed')");

        Schema::create('forum_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->boolean('is_closed')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->timestampsTz();
        });

        Schema::create('forum_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('forum_threads')->cascadeOnDelete();
            $table->foreignId('parent_post_id')->nullable()->constrained('forum_posts')->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->longText('body_html');
            $table->boolean('is_hidden')->default(false);
            $table->timestampsTz();
        });

        Schema::create('mass_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('section_id')->constrained()->restrictOnDelete();
            $table->json('condition');
            $table->string('subject');
            $table->text('body');
            $table->foreignId('sent_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
        });

        Schema::create('mass_message_recipients', function (Blueprint $table) {
            $table->foreignId('mass_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestampTz('delivered_at')->nullable();
            $table->primary(['mass_message_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mass_message_recipients');
        Schema::dropIfExists('mass_messages');
        Schema::dropIfExists('forum_posts');
        Schema::dropIfExists('forum_threads');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_events');
    }
};
