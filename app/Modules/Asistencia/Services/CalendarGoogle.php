<?php

namespace App\Modules\Asistencia\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Google Calendar real (RF21, ADR-018): `events.insert` en el calendario principal del docente, con enlace de Meet
 * (`conferenceDataVersion=1`, `hangoutsMeet`) e invitación a los matriculados (`sendUpdates=all`), y `events.delete`
 * cuando se cancela la clase; y los eventos del calendario de evaluaciones del período (RF06) en el calendario del
 * Administrador. Se llama desde jobs de la cola «google»; un error de Google se lanza para que el job reintente.
 *
 * Solo se invita a correos @ues.edu.sv. Si el docente no conectó su cuenta o Google revocó el permiso, devuelve
 * null y la clase queda programada sin evento.
 */
class CalendarGoogle implements CalendarGateway
{
    private const EVENTOS = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

    public function __construct(private CalendarConexionService $conexion) {}

    public function crearClase(User $docente, string $titulo, Carbon $inicio, int $minutos, array $invitados): ?array
    {
        if (! $this->conexion->delDominio($docente->institutional_email)) {
            return null;
        }
        $token = $this->conexion->tokenDeAcceso($docente);
        if ($token === null) {
            return null;
        }

        $zona = (string) config('app.timezone');
        $asistentes = $this->asistentes($invitados);

        $evento = Http::withToken($token)->timeout(20)
            ->withQueryParameters(['conferenceDataVersion' => 1, 'sendUpdates' => 'all'])
            ->post(self::EVENTOS, [
                'summary' => $titulo,
                'start' => ['dateTime' => $inicio->copy()->timezone($zona)->toIso8601String(), 'timeZone' => $zona],
                'end' => ['dateTime' => $inicio->copy()->timezone($zona)->addMinutes($minutos)->toIso8601String(), 'timeZone' => $zona],
                'attendees' => $asistentes,
                'conferenceData' => ['createRequest' => ['requestId' => (string) Str::uuid(), 'conferenceSolutionKey' => ['type' => 'hangoutsMeet']]],
                'guestsCanInviteOthers' => false,
            ])->throw()->json();

        return ['evento' => (string) $evento['id'], 'meet' => $evento['hangoutLink'] ?? null];
    }

    /**
     * `events.patch` del evento ya sincronizado o, si no existe (nunca se creó, o se borró en Google), `events.insert`.
     * La lista de invitados queda oculta: son los docentes y estudiantes de todo el período.
     */
    public function sincronizarEvento(User $organizador, ?string $eventoId, string $titulo, Carbon $inicio, ?Carbon $fin, array $invitados): ?string
    {
        if (! $this->conexion->delDominio($organizador->institutional_email)) {
            return null;
        }
        $token = $this->conexion->tokenDeAcceso($organizador);
        if ($token === null) {
            return null;
        }

        $zona = (string) config('app.timezone');
        $inicio = $inicio->copy()->timezone($zona);
        [$desde, $hasta] = $fin === null
            ? [['date' => $inicio->toDateString()], ['date' => $inicio->copy()->addDay()->toDateString()]]
            : [['dateTime' => $inicio->toIso8601String(), 'timeZone' => $zona], ['dateTime' => $fin->copy()->timezone($zona)->toIso8601String(), 'timeZone' => $zona]];
        $cuerpo = ['summary' => $titulo, 'start' => $desde, 'end' => $hasta, 'attendees' => $this->asistentes($invitados),
            'guestsCanInviteOthers' => false, 'guestsCanSeeOtherGuests' => false];
        $cliente = fn () => Http::withToken($token)->timeout(30)->withQueryParameters(['sendUpdates' => 'all']);

        if ($eventoId !== null) {
            $respuesta = $cliente()->patch(self::EVENTOS.'/'.rawurlencode($eventoId), $cuerpo);
            if (! in_array($respuesta->status(), [404, 410], true)) {
                return (string) $respuesta->throw()->json('id');
            }
        }

        return (string) $cliente()->post(self::EVENTOS, $cuerpo)->throw()->json('id');
    }

    /**
     * Correos únicos del dominio, en minúsculas, como invitados de Google.
     *
     * @param  list<string>  $invitados
     * @return list<array{email: string}>
     */
    private function asistentes(array $invitados): array
    {
        return collect($invitados)->map(fn (string $correo) => mb_strtolower(trim($correo)))
            ->filter(fn (string $correo) => $this->conexion->delDominio($correo))->unique()->values()
            ->map(fn (string $correo) => ['email' => $correo])->all();
    }

    /**
     * `events.delete` con `sendUpdates=all`: Google avisa a los invitados que la clase se canceló. Un 404 indica que el
     * evento no está en el calendario de este docente; un 410, que ya se había borrado.
     */
    public function borrarEvento(User $docente, string $eventoId): ?bool
    {
        $token = $this->conexion->tokenDeAcceso($docente);
        if ($token === null) {
            return null;
        }

        $respuesta = Http::withToken($token)->timeout(20)->withQueryParameters(['sendUpdates' => 'all'])
            ->delete(self::EVENTOS.'/'.rawurlencode($eventoId));

        return match (true) {
            $respuesta->status() === 404 => false,
            $respuesta->successful(), $respuesta->status() === 410 => true,
            default => (bool) $respuesta->throw(),
        };
    }
}
