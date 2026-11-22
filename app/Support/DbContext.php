<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Punto único de escritura en tablas auditadas (ADR-027).
 *
 * Abre la transacción y, en PostgreSQL, fija el actor y el contexto que leen los triggers
 * de auditoría (`fn_audit_row`). En SQLite no hay triggers: solo abre la transacción.
 */
final class DbContext
{
    /**
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    public static function run(callable $work, ?string $event = null, ?string $reason = null, bool $allowClosedPeriod = false, int $attempts = 3): mixed
    {
        return DB::transaction(fn () => self::within([
            'app.user_id' => (string) (auth()->id() ?? ''),
            'app.ip' => (string) (request()?->ip() ?? ''),
            'app.request_id' => (string) (request()?->attributes->get('request_id') ?? ''),
            'app.event_type' => $event ?? '',
            'app.reason' => $reason ?? '',
            'app.allow_closed_period' => $allowClosedPeriod ? 'on' : '',
        ], $work), $attempts);
    }

    /**
     * Escrituras de seeders, migraciones de datos y jobs del programador, sin usuario.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    public static function system(callable $work, ?string $event = null): mixed
    {
        return DB::transaction(fn () => self::within(['app.system_job' => 'on', 'app.event_type' => $event ?? 'system'], $work));
    }

    /**
     * Alcance de lectura de la auditoría para la RLS de PostgreSQL (ADR-029).
     *
     * @param  array<int, int>  $departmentIds
     */
    public static function auditScope(bool $all, array $departmentIds = []): void
    {
        self::apply([
            'app.audit_scope' => $all ? 'all' : '',
            'app.department_ids' => implode(',', $departmentIds),
        ]);
    }

    /**
     * Aplica el contexto y, al terminar, deja el que había antes: `set_config(..., true)` vive hasta el fin de la
     * transacción exterior, así que sin restaurarlo se filtraría a escrituras posteriores de la misma transacción.
     * Se restaura el valor anterior, no se vacía: con un DbContext dentro de otro (un Service que lo usa llamado
     * desde un seeder o desde otro Service), vaciar dejaba al de afuera sin actor y su siguiente escritura en una
     * tabla auditada fallaba con AC900.
     *
     * @template T
     *
     * @param  array<string, string>  $settings
     * @param  callable(): T  $work
     * @return T
     */
    private static function within(array $settings, callable $work): mixed
    {
        $anteriores = self::leer(array_keys($settings));
        self::apply($settings);

        // Solo se restaura si el trabajo terminó bien. Si falla, la transacción (o su savepoint) hace rollback y
        // PostgreSQL deshace los set_config locales; limpiar aquí, con la transacción ya abortada, fallaría
        // (25P02) y ocultaría la excepción original.
        $resultado = $work();
        self::apply($anteriores);

        return $resultado;
    }

    /**
     * Valores actuales del contexto ('' si no están fijados), para restaurarlos al salir.
     *
     * @param  array<int, string>  $keys
     * @return array<string, string>
     */
    private static function leer(array $keys): array
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return array_fill_keys($keys, '');
        }

        $valores = [];
        foreach ($keys as $key) {
            $valores[$key] = (string) (DB::selectOne('select current_setting(?, true) as valor', [$key])->valor ?? '');
        }

        return $valores;
    }

    /**
     * @param  array<string, string>  $settings
     */
    private static function apply(array $settings): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($settings as $key => $value) {
            DB::select('select set_config(?, ?, true)', [$key, $value]);
        }
    }
}
