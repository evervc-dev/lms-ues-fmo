<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cambio de teléfono (RF27), dos huecos de la bandeja de Soporte:
 * - `resolution_note`: el motivo del rechazo, que hasta ahora solo viajaba al aviso del estudiante y a la
 *   auditoría, así que «Resueltas» no podía mostrarlo.
 * - `device_request.pending`: el aviso de que llegó una solicitud, la entrada del flujo «Atender un cambio de
 *   teléfono» (docs/13 Layout/Layout Soporte Técnico.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_change_requests', function (Blueprint $table) {
            $table->text('resolution_note')->nullable()->after('resolved_at');
        });

        $ahora = now();
        DB::table('notification_events')->insertOrIgnore([
            ['code' => 'device_request.pending', 'description' => 'Nueva solicitud de cambio de teléfono', 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);
    }

    public function down(): void
    {
        Schema::table('device_change_requests', function (Blueprint $table) {
            $table->dropColumn('resolution_note');
        });

        // Solo si no quedan avisos: notifications.event_code lo referencia con restrictOnDelete.
        DB::table('notification_events')->where('code', 'device_request.pending')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('notifications')->whereColumn('notifications.event_code', 'notification_events.code'))->delete();
    }
};
