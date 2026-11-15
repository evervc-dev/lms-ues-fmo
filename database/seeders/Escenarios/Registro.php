<?php

namespace Database\Seeders\Escenarios;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Libreta de lo que sembró un escenario.
 *
 * Todo lo que un escenario crea pasa por aquí y queda anotado en `test_scenario_objects` en orden de
 * creación, porque limpiar el escenario es recorrer esa lista al revés. Lo que **no** se anota se
 * considera compartido y sobrevive a la limpieza: los perfiles de las personas reales del equipo y sus
 * roles, que otros escenarios vivos pueden estar usando.
 */
final class Registro
{
    public function __construct(private readonly int $escenarioId) {}

    /**
     * Inserta una fila y la anota.
     *
     * @param  array<string, mixed>  $fila
     */
    public function insertar(string $tabla, array $fila): int
    {
        $id = (int) DB::table($tabla)->insertGetId($fila);
        $this->anotar($tabla, ['id' => $id]);

        return $id;
    }

    /**
     * Inserta varias filas de golpe y anota las que quedaron. Las columnas que falten en una fila se
     * completan con null, igual que en `InsertsRows`.
     *
     * @param  array<int, array<string, mixed>>  $filas
     * @return array<int, int> los id insertados, en orden
     */
    public function insertarVarias(string $tabla, array $filas, int $lote = 200): array
    {
        if ($filas === []) {
            return [];
        }

        $desde = (int) DB::table($tabla)->max('id');
        foreach (array_chunk($filas, $lote) as $parte) {
            $columnas = array_fill_keys(array_keys(array_merge(...$parte)), null);
            DB::table($tabla)->insert(array_map(fn (array $fila) => array_merge($columnas, $fila), $parte));
        }

        $ids = DB::table($tabla)->where('id', '>', $desde)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($ids as $id) {
            $this->anotar($tabla, ['id' => $id]);
        }

        return $ids;
    }

    /**
     * Inserta en una tabla sin columna `id` y la anota con las columnas que identifican la fila.
     *
     * @param  array<string, mixed>  $fila
     * @param  array<int, string>  $columnasClave  las columnas de $fila que forman la clave
     */
    public function insertarCon(string $tabla, array $fila, array $columnasClave): void
    {
        DB::table($tabla)->insert($fila);
        $this->anotar($tabla, array_intersect_key($fila, array_flip($columnasClave)));
    }

    /**
     * Anota una fila, con la clave que la identifica. También sirve para una fila que creó un servicio
     * de la aplicación en vez del escenario.
     *
     * @param  array<string, mixed>  $clave
     */
    public function anotar(string $tabla, array $clave): void
    {
        DB::table('test_scenario_objects')->insert([
            'scenario_id' => $this->escenarioId,
            'table_name' => $tabla,
            'key_json' => json_encode($clave),
            'created_at' => Carbon::now(),
        ]);
    }

    public function escenario(): int
    {
        return $this->escenarioId;
    }
}
