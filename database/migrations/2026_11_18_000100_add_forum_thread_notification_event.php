<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aviso de tema nuevo en el foro (RF30): antes solo se avisaba de las respuestas, así que un hilo nuevo pasaba
 * inadvertido. `notifications.event_code` tiene clave foránea al catálogo, de modo que el código debe existir
 * también en las bases ya sembradas.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('notification_events')->updateOrInsert(
            ['code' => 'forum.thread'],
            ['description' => 'Tema nuevo en un foro', 'created_at' => now(), 'updated_at' => now()],
        );
    }

    public function down(): void
    {
        DB::table('notification_preferences')->where('event_code', 'forum.thread')->delete();
        DB::table('notifications')->where('event_code', 'forum.thread')->delete();
        DB::table('notification_events')->where('code', 'forum.thread')->delete();
    }
};
