<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * RF17 (CP-RF17-01): hasta ahora crear una sección no copiaba los cómputos de la plantilla del período
 * (`grading_component_templates`), así que la sección quedaba sin cómputos y sin pesos. Desde este cambio,
 * CargaAcademicaService::crearSeccion() los copia; esta migración hace lo mismo con las secciones que ya existen y
 * no tienen ningún cómputo, salvo las de períodos cerrados: su resultado ya está congelado. Es idempotente: una
 * sección con cómputos no se toca.
 *
 * `grading_components` no se audita (database/sql/pgsql/02_audit.sql), así que no hace falta DbContext.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();
        $filas = DB::table('sections as s')->join('grading_component_templates as t', 't.period_id', '=', 's.period_id')
            ->join('academic_periods as p', 'p.id', '=', 's.period_id')->where('p.status', '!=', 'closed')
            ->whereNotExists(fn (Builder $q) => $q->select(DB::raw(1))->from('grading_components as c')->whereColumn('c.section_id', 's.id'))
            ->orderBy('s.id')->orderBy('t.number')
            ->get(['s.id as section_id', 't.number', 't.name', 't.starts_on', 't.ends_on', 't.weight_percent'])
            ->map(fn (object $f) => (array) $f + ['created_at' => $ahora, 'updated_at' => $ahora]);

        // Por tandas, con valores enlazados: así corre igual en SQLite y en PostgreSQL.
        foreach ($filas->chunk(500) as $tanda) {
            DB::table('grading_components')->insert($tanda->values()->all());
        }
    }

    /**
     * No borra nada: los cómputos copiados pueden tener ya actividades y pesos ajustados por el docente.
     */
    public function down(): void {}
};
