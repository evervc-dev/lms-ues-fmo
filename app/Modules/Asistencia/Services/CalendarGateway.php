<?php

namespace App\Modules\Asistencia\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Google Calendar detrás de una interfaz (RF21, RF06, ADR-018, docs/06 Arquitectura/Integraciones con Google.md):
 * crea el evento de una clase virtual en el calendario del docente, con su enlace de Meet, e invita a los
 * matriculados; y sincroniza las fechas del calendario de evaluaciones del período en el del Administrador.
 *
 * Con `GOOGLE_CALENDAR_ENABLED` y el cliente OAuth configurados, la implementación es `CalendarGoogle`, con el
 * permiso `calendar.events` del docente; si no, `CalendarDesactivado`, que no hace nada. Las pruebas usan `CalendarFalso`.
 */
interface CalendarGateway
{
    /**
     * @param  list<string>  $invitados  Correos institucionales de los matriculados.
     * @return array{evento: string, meet: string|null}|null El evento y su enlace de Meet, o null si la integración no está disponible.
     */
    public function crearClase(User $docente, string $titulo, Carbon $inicio, int $minutos, array $invitados): ?array;

    /**
     * Crea o actualiza un evento general en el calendario del organizador (RF06-04, el calendario de evaluaciones
     * del período), sin Meet y con la lista de invitados oculta. Sin `$fin`, es un evento de todo el día.
     *
     * @param  list<string>  $invitados
     * @return string|null El id del evento en Google, o null si la integración no está disponible para el organizador.
     */
    public function sincronizarEvento(User $organizador, ?string $eventoId, string $titulo, Carbon $inicio, ?Carbon $fin, array $invitados): ?string;

    /**
     * Borra un evento (la clase cancelada o la fecha del período eliminada) y avisa a los invitados.
     *
     * @return bool|null true si se borró (o Google ya lo había borrado), false si no está en el calendario de ese
     *                   docente, o null si la integración no está disponible para él.
     */
    public function borrarEvento(User $docente, string $eventoId): ?bool;
}
