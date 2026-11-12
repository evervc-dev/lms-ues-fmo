<?php

namespace App\Support\Navegacion;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Contadores del menú (docs/13 Layout/Layout base.md): el número que acompaña a una opción, p. ej.
 * «Solicitudes de dispositivo (2)» en el menú de Soporte (docs/13 Layout/Layout Soporte Técnico.md).
 *
 * Se resuelven en cada carga del layout, así que cada uno debe ser una sola consulta barata. La opción ya
 * se mostró porque el usuario tiene su permiso: el contador no autoriza nada.
 */
class Contadores
{
    public function para(string $clave, User $usuario): int
    {
        return match ($clave) {
            'dispositivos-pendientes' => DB::table('device_change_requests')->where('status', 'pending')->count(),
            default => 0,
        };
    }
}
