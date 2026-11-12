<?php

namespace App\Modules\Asistencia\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Calendar falso para las pruebas (RF21, Estrategia de pruebas): guarda lo que se le pidió y devuelve un evento con
 * un enlace de Meet de mentira, sin llamar a Google.
 */
class CalendarFalso implements CalendarGateway
{
    /** @var list<array{docente: int, titulo: string, inicio: string, minutos: int, invitados: list<string>}> */
    public array $creados = [];

    /** @var list<array{docente: int, evento: string}> */
    public array $cancelados = [];

    /** @var list<array{organizador: int, evento: string|null, titulo: string, inicio: string, fin: string|null, invitados: list<string>}> */
    public array $sincronizados = [];

    public function crearClase(User $docente, string $titulo, Carbon $inicio, int $minutos, array $invitados): ?array
    {
        $this->creados[] = ['docente' => $docente->id, 'titulo' => $titulo, 'inicio' => $inicio->toIso8601String(), 'minutos' => $minutos, 'invitados' => $invitados];

        return ['evento' => 'evento-'.Str::lower(Str::random(10)), 'meet' => 'https://meet.google.com/abc-defg-hij'];
    }

    public function sincronizarEvento(User $organizador, ?string $eventoId, string $titulo, Carbon $inicio, ?Carbon $fin, array $invitados): ?string
    {
        $this->sincronizados[] = ['organizador' => $organizador->id, 'evento' => $eventoId, 'titulo' => $titulo, 'inicio' => $inicio->toIso8601String(),
            'fin' => $fin?->toIso8601String(), 'invitados' => $invitados];

        return $eventoId ?? 'periodo-'.Str::lower(Str::random(10));
    }

    public function borrarEvento(User $docente, string $eventoId): ?bool
    {
        $this->cancelados[] = ['docente' => $docente->id, 'evento' => $eventoId];

        return true;
    }
}
