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
     * Aplica el contexto y lo limpia al terminar: `set_config(..., true)` vive hasta el fin de la
     * transacción exterior, así que sin limpiar se filtraría a escrituras posteriores de la misma transacción.
     *
     * @template T
     *
     * @param  array<string, string>  $settings
     * @param  callable(): T  $work
     * @return T
     */
    private static function within(array $settings, callable $work): mixed
    {
        self::apply($settings);

        // Solo se limpia si el trabajo terminó bien. Si falla, la transacción (o su savepoint) hace rollback y
        // PostgreSQL deshace los set_config locales; limpiar aquí, con la transacción ya abortada, fallaría
        // (25P02) y ocultaría la excepción original.
        $resultado = $work();
        self::apply(array_fill_keys(array_keys($settings), ''));

        return $resultado;
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
