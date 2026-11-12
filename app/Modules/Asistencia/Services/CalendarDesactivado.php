<?php

namespace App\Modules\Asistencia\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Calendar sin integración (RF21): no crea eventos. Es la implementación mientras Calendar no esté habilitado
 * (`GOOGLE_CALENDAR_ENABLED`); la clase virtual sigue funcionando con el enlace de Meet que escribe el docente.
 */
class CalendarDesactivado implements CalendarGateway
{
    public function crearClase(User $docente, string $titulo, Carbon $inicio, int $minutos, array $invitados): ?array
    {
        return null;
    }

    public function sincronizarEvento(User $organizador, ?string $eventoId, string $titulo, Carbon $inicio, ?Carbon $fin, array $invitados): ?string
    {
        return null;
    }

    public function borrarEvento(User $docente, string $eventoId): ?bool
    {
        return null;
    }
}
