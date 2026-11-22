<?php

namespace Database\Seeders;

use App\Support\DbContext;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Datos base en todos los entornos; estructura y personas solo fuera de producción. Las personas dependen del
     * perfil (LMS_SEED_PERFIL): «demo», inventadas y con datos de uso; «equipo», las cuentas reales del equipo.
     * En PostgreSQL las escrituras auditadas exigen contexto: DbContext::system().
     */
    public function run(): void
    {
        DbContext::system(function (): void {
            $this->call(BaseSystemSeeder::class);

            if (app()->isProduction()) {
                return;
            }

            $this->call(InstitutionSeeder::class);
            $this->call(config('lms.seed_perfil') === 'equipo'
                ? [EquipoSeeder::class]
                : [DemoPeopleSeeder::class, DemoAcademicSeeder::class]);
        }, 'seed');
    }
}
