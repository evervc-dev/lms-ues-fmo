<?php

namespace Database\Seeders\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

trait InsertsRows
{
    /**
     * Inserta por lotes (SQLite limita los parámetros por sentencia). Las filas de un lote pueden
     * traer columnas distintas: las que falten se completan con null.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function insertRows(string $table, array $rows, int $chunk = 200): void
    {
        foreach (array_chunk($rows, $chunk) as $batch) {
            $columns = array_fill_keys(array_keys(array_merge(...$batch)), null);
            DB::table($table)->insert(array_map(fn (array $row) => array_merge($columns, $row), $batch));
        }
    }

    /**
     * @return array{created_at: Carbon, updated_at: Carbon}
     */
    protected function stamps(?Carbon $at = null): array
    {
        $at ??= now();

        return ['created_at' => $at, 'updated_at' => $at];
    }
}
