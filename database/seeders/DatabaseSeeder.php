<?php

namespace Database\Seeders;

use App\Support\DbContext;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Datos base en todos los entornos; estructura y demo solo fuera de producción.
     * En PostgreSQL las escrituras auditadas exigen contexto: DbContext::system().
     */
    public function run(): void
    {
        DbContext::system(function (): void {
            $this->call(BaseSystemSeeder::class);

            if (app()->isProduction()) {
                return;
            }

            $this->call([
                InstitutionSeeder::class,
                DemoPeopleSeeder::class,
                DemoAcademicSeeder::class,
            ]);
        }, 'seed');
    }
}
