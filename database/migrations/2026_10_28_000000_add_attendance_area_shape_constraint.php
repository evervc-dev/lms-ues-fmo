<?php

use App\Support\Database\Adaptive;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Forma de las áreas de asistencia (RF26): un círculo lleva centro y radio; un polígono, al menos 3 vértices en
 * `polygon`. En SQLite la regla la garantiza AsistenciaService (ADR-033).
 *
 * NOT VALID: PostgreSQL la exige a toda escritura nueva sin revisar las filas que ya existen, así que una fila
 * vieja que no la cumpla no tumba el despliegue (lo que pasó con academic_periods_no_overlap el 28/10/2026).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Adaptive::isPgsql()) {
            DB::statement("ALTER TABLE attendance_areas ADD CONSTRAINT attendance_areas_shape CHECK (
                (area_type = 'circle' AND center_lat IS NOT NULL AND center_lng IS NOT NULL AND radius_m IS NOT NULL)
                OR (area_type = 'polygon' AND CASE WHEN json_typeof(polygon) = 'array' THEN json_array_length(polygon) >= 3 ELSE false END)
            ) NOT VALID");
        }
    }

    public function down(): void
    {
        if (Adaptive::isPgsql()) {
            DB::statement('ALTER TABLE attendance_areas DROP CONSTRAINT IF EXISTS attendance_areas_shape');
        }
    }
};
