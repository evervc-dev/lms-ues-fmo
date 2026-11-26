<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fin de la carga masiva de usuarios (RF04): el aviso `users.import_finished` a quien subió el archivo, para que
 * pueda configurarlo en sus preferencias.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();
        DB::table('notification_events')->insertOrIgnore([
            ['code' => 'users.import_finished', 'description' => 'Carga masiva terminada', 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);
    }

    public function down(): void
    {
        // Solo si no quedan avisos: notifications.event_code lo referencia con restrictOnDelete.
        DB::table('notification_events')->where('code', 'users.import_finished')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('notifications')->whereColumn('notifications.event_code', 'notification_events.code'))->delete();
    }
};
