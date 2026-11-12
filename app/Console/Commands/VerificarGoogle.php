<?php

namespace App\Console\Commands;

use App\Modules\Identidad\Services\GoogleCredencialesService;
use Illuminate\Console\Command;

/**
 * Verifica la conexión con Google y la validez del cliente OAuth del inicio de sesión (RF01, ADR-007).
 * Termina con código distinto de cero si algún paso falla, para usarlo en el despliegue.
 */
class VerificarGoogle extends Command
{
    protected $signature = 'google:verificar';

    protected $description = 'Verifica la conexión con Google y las credenciales del cliente OAuth';

    public function handle(GoogleCredencialesService $servicio): int
    {
        $pasos = $servicio->verificar();
        $this->table(['Paso', 'Estado', 'Detalle'], array_map(fn (array $paso) => [$paso['paso'], $paso['ok'] ? 'OK' : 'FALLA', $paso['detalle']], $pasos));

        if (in_array(false, array_column($pasos, 'ok'), true)) {
            $this->error(__('La configuración de Google tiene errores.'));

            return self::FAILURE;
        }
        $this->info(__('Google y las credenciales del cliente OAuth están correctos.'));

        return self::SUCCESS;
    }
}
