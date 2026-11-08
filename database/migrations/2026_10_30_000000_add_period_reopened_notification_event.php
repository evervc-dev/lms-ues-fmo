<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reapertura de un período cerrado (RF06): el aviso `period.reopened` a los Coordinadores, para que puedan
 * configurarlo en sus preferencias.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();
        DB::table('notification_events')->insertOrIgnore([
            ['code' => 'period.reopened', 'description' => 'Período reabierto', 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);
    }

    public function down(): void
    {
        // Solo si no quedan avisos: notifications.event_code lo referencia con restrictOnDelete.
        DB::table('notification_events')->where('code', 'period.reopened')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('notifications')->whereColumn('notifications.event_code', 'notification_events.code'))->delete();
    }
};
