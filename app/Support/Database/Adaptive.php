<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Migraciones adaptativas (ADR-033).
 *
 * Todo lo que soportan ambos motores se aplica siempre (SQLite por defecto).
 * Lo propio de PostgreSQL (citext, CHECK, EXCLUDE, triggers, RLS, particiones,
 * vistas materializadas) solo se aplica cuando la conexión es pgsql.
 */
final class Adaptive
{
    public static function isPgsql(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    public static function isSqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    /**
     * Índice único parcial. SQLite y PostgreSQL usan la misma sintaxis.
     *
     * @param  array<int, string>  $columns
     */
    public static function partialUnique(string $table, array $columns, string $where, string $name): void
    {
        DB::statement(sprintf(
            'CREATE UNIQUE INDEX %s ON %s (%s) WHERE %s',
            $name,
            $table,
            implode(', ', $columns),
            $where,
        ));
    }

    /**
     * En SQLite, Laravel reconstruye la tabla al alterarla (p. ej. al agregar una FK) y recrea los
     * índices sin su condición WHERE. Llámelo después de cada Schema::table() sobre una tabla con
     * índices parciales para volver a crearlos.
     *
     * @param  array<int, string>  $columns
     */
    public static function restorePartialUnique(string $table, array $columns, string $where, string $name): void
    {
        if (self::isSqlite()) {
            DB::statement("DROP INDEX IF EXISTS {$name}");
            self::partialUnique($table, $columns, $where, $name);
        }
    }

    /**
     * Restricción CHECK. SQLite no permite agregarla con ALTER TABLE, así que solo aplica en PostgreSQL;
     * en SQLite la regla la garantiza la validación del Service.
     */
    public static function check(string $table, string $name, string $expression): void
    {
        if (self::isPgsql()) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
        }
    }

    /**
     * Cambia la columna a citext (comparación sin mayúsculas) en PostgreSQL.
     */
    public static function citext(string $table, string $column): void
    {
        if (self::isPgsql()) {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE citext");
        }
    }

    /**
     * Ejecuta un archivo de database/sql/pgsql/ solo en PostgreSQL.
     */
    public static function pgsqlFile(string $file): void
    {
        if (! self::isPgsql()) {
            return;
        }

        $path = database_path("sql/pgsql/{$file}");

        if (! is_file($path)) {
            throw new RuntimeException("No existe el archivo SQL {$path}.");
        }

        DB::unprepared((string) file_get_contents($path));
    }
}
