<?php

namespace App\Console\Commands;

use App\Modules\Identidad\Services\AccesoLocalService;
use Illuminate\Console\Command;

/**
 * Crea o actualiza la cuenta superadministradora (ADR-082) con SUPERADMIN_EMAIL y SUPERADMIN_PASSWORD. Idempotente.
 */
class CrearSuperadmin extends Command
{
    protected $signature = 'superadmin:crear';

    protected $description = 'Crea o actualiza la cuenta superadministradora con la contraseña de SUPERADMIN_PASSWORD';

    public function handle(AccesoLocalService $acceso): int
    {
        try {
            $usuario = $acceso->crearCuentaSuperadmin();
        } catch (\RuntimeException $excepcion) {
            $this->error($excepcion->getMessage());

            return self::FAILURE;
        }
        $this->info(__('Cuenta superadministradora lista: :correo', ['correo' => $usuario->institutional_email]));

        return self::SUCCESS;
    }
}
