<?php

namespace App\Modules\Comunicacion\Services;

use App\Models\User;
use App\Modules\Comunicacion\Events\AvisoAgrupado;
use App\Modules\Comunicacion\Notifications\Aviso;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Notificaciones en la plataforma (RF23): lo propio de cada usuario y el envío de avisos.
 *
 * Enviar un aviso decide, por destinatario, si es nuevo, si ya se envió o si se suma a uno reciente del
 * mismo tipo (CA3). La fila de la bandeja se inserta aquí, de forma síncrona, y la cola `notificaciones`
 * solo lleva el push por Reverb y el correo: así cada entrega de `notification_deliveries` apunta a una
 * fila que ya existe, sin depender del orden en que corran los trabajos.
 */
class NotificacionService
{
    /** Avisos del mismo tipo que llegan dentro de esta ventana se agrupan en uno (CA3). */
    public const VENTANA_MINUTOS = 10;

    /**
     * Avisos cuyo correo no se puede silenciar: el mensaje del docente a quienes no entregaron o van mal (RF31,
     * ADR-044) es justo el que no debe dejar de llegar.
     */
    public const CORREO_OBLIGATORIO = ['mass_message.sent'];

    /**
     * Avisos que nunca se agrupan: cada mensaje del docente dice algo distinto y «3 avisos nuevos» lo perdería;
     * lo mismo la resolución de un cambio de teléfono (RF27), la devolución de un acta (RF34), el retiro o el cambio de
     * grupo rechazado (RF09), la cancelación de una clase (RF32) y la reapertura de un período (RF06), cuyo motivo no
     * puede perderse.
     */
    public const SIN_AGRUPAR = ['mass_message.sent', 'device_request.resolved', 'grade_sheet.returned', 'enrollment.changed', 'class.cancelled', 'period.reopened'];

    /** Cuántas claves de idempotencia recuerda un aviso agrupado, para no contar dos veces el mismo evento. */
    private const MAX_AGRUPADOS = 50;

    public function listar(User $usuario, bool $soloSinLeer, int $porPagina = 20): LengthAwarePaginator
    {
        return $usuario->notifications()->when($soloSinLeer, fn ($q) => $q->whereNull('read_at'))->latest()->paginate($porPagina);
    }

    /**
     * Los últimos avisos para el panel de la campana (docs/13 Layout/Layout base.md: los últimos 10).
     *
     * @return Collection<int, DatabaseNotification>
     */
    public function recientes(User $usuario, int $limite = 10): Collection
    {
        return $usuario->notifications()->latest()->limit($limite)->get();
    }

    public function contarSinLeer(User $usuario): int
    {
        return $usuario->unreadNotifications()->count();
    }

    public function marcarLeida(User $usuario, string $id): void
    {
        $usuario->notifications()->whereKey($id)->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function marcarTodas(User $usuario): int
    {
        return $usuario->unreadNotifications()->update(['read_at' => now()]);
    }

    /**
     * Abre un aviso: lo marca leído y devuelve la pantalla del evento que avisa, o null si ese aviso no lleva a
     * ninguna (una cancelación de toda la facultad, p. ej., se explica sola en su texto). La usan la campana y
     * la bandeja, para que abrir un aviso haga lo mismo en las dos.
     */
    public function abrir(User $usuario, string $id): ?string
    {
        $aviso = $usuario->notifications()->whereKey($id)->first();
        if ($aviso === null) {
            return null;
        }

        $this->marcarLeida($usuario, $id);

        return $aviso->data['url'] ?? null;
    }

    /**
     * Si el usuario recibe por correo este tipo de aviso. Sin fila en notification_preferences se asume que sí
     * (RF23 CA2: solo deja de llegar si lo desactivó).
     */
    public function correoActivo(User $usuario, string $evento): bool
    {
        return (bool) (DB::table('notification_preferences')->where('user_id', $usuario->id)->where('event_code', $evento)->value('mail_enabled') ?? true);
    }

    /**
     * Canales por los que sale un aviso a este usuario, además de la bandeja: el push siempre y el correo
     * solo si no lo desactivó para este tipo de aviso.
     *
     * @return array<int, string>
     */
    public function canales(User $usuario, string $evento): array
    {
        $correo = in_array($evento, self::CORREO_OBLIGATORIO, true) || $this->correoActivo($usuario, $evento);

        return $correo ? ['broadcast', 'mail'] : ['broadcast'];
    }

    /**
     * Envía un aviso a los usuarios indicados.
     *
     * - Las cuentas sin rol no reciben nada (ADR-038).
     * - Un aviso con la misma clave de idempotencia no se repite (RNF12: duplicados = 0).
     * - Si el usuario ya tiene un aviso sin leer del mismo tipo de hace menos de VENTANA_MINUTOS, se suma a ese
     *   (contador en `data.count`) y no genera otro correo.
     *
     * @param  array<int, int|string>  $usuarios
     */
    public function enviar(array $usuarios, Aviso $aviso): void
    {
        $destinatarios = User::query()->whereKey($usuarios)->whereHas('roles')->get();
        if ($destinatarios->isEmpty()) {
            return;
        }

        $ids = $destinatarios->pluck('id');
        $tipo = (new User)->getMorphClass();

        $yaEnviado = $this->yaEnviado($tipo, $ids, $aviso);
        $recientes = in_array($aviso->evento, self::SIN_AGRUPAR, true) ? collect() : DB::table('notifications')
            ->where('notifiable_type', $tipo)->whereIn('notifiable_id', $ids)
            ->where('event_code', $aviso->evento)->whereNull('read_at')
            ->where('created_at', '>=', now()->subMinutes(self::VENTANA_MINUTOS))
            ->orderByDesc('created_at')->get()->unique('notifiable_id')->keyBy('notifiable_id');

        $nuevos = collect();
        foreach ($destinatarios as $usuario) {
            if ($yaEnviado->has($usuario->id)) {
                continue;
            }
            if ($fila = $recientes->get($usuario->id)) {
                $this->agrupar($fila, $aviso, $usuario);

                continue;
            }
            $nuevos->push($usuario);
        }

        $this->crear($tipo, $nuevos, $aviso);
    }

    /**
     * Usuarios a los que este aviso ya les llegó: por su clave de idempotencia, o porque está dentro de un
     * aviso agrupado reciente.
     *
     * @param  Collection<int, int>  $ids
     * @return Collection<int, true> id de usuario => true
     */
    private function yaEnviado(string $tipo, Collection $ids, Aviso $aviso): Collection
    {
        if ($aviso->dedup === null) {
            return collect();
        }

        $propios = DB::table('notifications')->where('notifiable_type', $tipo)->whereIn('notifiable_id', $ids)
            ->where('dedup_key', $aviso->dedup)->pluck('notifiable_id');

        $agrupados = DB::table('notifications')->where('notifiable_type', $tipo)->whereIn('notifiable_id', $ids)
            ->where('event_code', $aviso->evento)->where('created_at', '>=', now()->subMinutes(self::VENTANA_MINUTOS))
            ->get(['notifiable_id', 'data'])
            ->filter(fn (object $f) => in_array($aviso->dedup, json_decode($f->data, true)['agrupados'] ?? [], true))
            ->pluck('notifiable_id');

        return $propios->merge($agrupados)->mapWithKeys(fn ($id) => [$id => true]);
    }

    /**
     * Inserta las filas de la bandeja de una vez y manda por la cola el push y el correo de las que de verdad
     * se crearon: `insertOrIgnore` cubre la carrera de dos eventos idénticos a la vez (índice único parcial).
     *
     * @param  Collection<int, User>  $usuarios
     */
    private function crear(string $tipo, Collection $usuarios, Aviso $aviso): void
    {
        if ($usuarios->isEmpty()) {
            return;
        }

        $ahora = now();
        $ids = $usuarios->mapWithKeys(fn (User $u) => [$u->id => (string) Str::uuid()]);

        DB::table('notifications')->insertOrIgnore($usuarios->map(fn (User $u) => [
            'id' => $ids[$u->id], 'type' => Aviso::class, 'notifiable_type' => $tipo, 'notifiable_id' => $u->id,
            'event_code' => $aviso->evento, 'dedup_key' => $aviso->dedup,
            'data' => json_encode($aviso->toDatabase($u)), 'created_at' => $ahora, 'updated_at' => $ahora,
        ])->all());

        $creados = DB::table('notifications')->whereIn('id', $ids->values())->pluck('notifiable_id', 'id');

        DB::table('notification_deliveries')->insertOrIgnore($creados->keys()->map(fn (string $id) => [
            'notification_id' => $id, 'channel' => 'database', 'status' => 'sent', 'sent_at' => $ahora,
            'created_at' => $ahora, 'updated_at' => $ahora,
        ])->all());

        foreach ($usuarios->filter(fn (User $u) => $creados->contains($u->id)) as $usuario) {
            $envio = clone $aviso;
            $envio->id = $ids[$usuario->id];
            Notification::send($usuario, $envio);
        }
    }

    /**
     * Suma el aviso a uno reciente sin leer del mismo tipo: sube el contador, deja el último título y avisa a
     * la campana para que se refresque. No genera otra fila, ni otro correo.
     */
    private function agrupar(object $fila, Aviso $aviso, User $usuario): void
    {
        $datos = json_decode($fila->data, true);
        $cuenta = (int) ($datos['count'] ?? 1) + 1;
        $agrupados = array_slice(array_values(array_filter(array_merge(
            $datos['agrupados'] ?? [$fila->dedup_key],
            [$aviso->dedup],
        ))), -self::MAX_AGRUPADOS);

        $tipo = mb_strtolower((string) DB::table('notification_events')->where('code', $aviso->evento)->value('description'));

        DB::table('notifications')->where('id', $fila->id)->update([
            'data' => json_encode([
                'title' => __(':n avisos nuevos: :tipo', ['n' => $cuenta, 'tipo' => $tipo]),
                'body' => __('Último: :t', ['t' => $aviso->titulo]),
                'url' => $aviso->url ?? ($datos['url'] ?? null),
                'count' => $cuenta,
                'agrupados' => $agrupados,
            ]),
            'updated_at' => now(),
        ]);

        AvisoAgrupado::dispatch($usuario->id);
    }
}
