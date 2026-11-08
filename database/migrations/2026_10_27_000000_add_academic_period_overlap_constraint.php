<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Traslape de períodos (RF06, hallazgo H-03 del informe de QA del rol Administrador): en PostgreSQL un EXCLUDE
 * con daterange y btree_gist impide que dos períodos crucen sus fechas en cualquier estado. En SQLite la regla
 * la garantiza la validación de PeriodoService (ADR-033), con el mismo criterio semiabierto «[inicio, fin)».
 * Si la base ya tiene períodos cruzados, el SQL no crea la restricción y lo avisa en vez de fallar el despliegue
 * (staging, 28/10/2026): se corrigen esas fechas y se vuelve a correr el archivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Adaptive::pgsqlFile('13_period_no_overlap.sql');
    }

    public function down(): void
    {
        if (Adaptive::isPgsql()) {
            DB::statement('ALTER TABLE academic_periods DROP CONSTRAINT IF EXISTS academic_periods_no_overlap');
        }
    }
};
