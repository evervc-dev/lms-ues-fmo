<?php

namespace Database\Seeders;

use App\Support\DbContext;
use Illuminate\Console\Command;
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

        $this->imprimirResumen();
    }

    /**
     * Lo último que se ve: cómo quedó la distribución de cuentas, roles y grupos, leída de la base.
     */
    private function imprimirResumen(): void
    {
        /** @var Command|null $consola */
        $consola = $this->command;
        if ($consola === null || app()->isProduction()) {
            return;
        }
        $datos = ResumenSiembra::datos();
        $equipo = config('lms.seed_perfil') === 'equipo';

        $consola->newLine();
        $consola->info($equipo
            ? __('Distribución final · perfil equipo · semilla :s (cambie LMS_SEED_SEMILLA para barajar)', ['s' => config('lms.seed_semilla')])
            : __('Distribución final · perfil demo'));
        $consola->table([__('Rol'), __('Cuentas')], $datos['porRol']);
        $consola->line(trans_choice('{1} La cuenta:|[2,*] Las :count cuentas:', count($datos['cuentas'])));
        $consola->table([__('Rol'), __('Correo'), __('Nombre'), __('Asignación')], $datos['cuentas']);
        $consola->table([__('Período'), ''], $datos['periodo']);
    }
}
