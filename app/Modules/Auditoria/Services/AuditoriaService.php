<?php

namespace App\Modules\Auditoria\Services;

use App\Models\User;
use App\Support\Alcance;
use App\Support\DbContext;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Auditoría (RF05, ADR-029): el Administrador ve todo; el Jefe, solo su departamento.
 * En PostgreSQL la RLS lo garantiza (DbContext::auditScope); el filtro por departamento se aplica
 * igual en la consulta, para SQLite y como segunda barrera. Los registros nunca se modifican.
 */
class AuditoriaService
{
    public const MAXIMO_EXPORTACION = 10000;

    /** Minutos que se guarda la lista de entidades del filtro. */
    private const ENTIDADES_EN_CACHE = 30;

    public function __construct(private Alcance $alcance) {}

    /**
     * El evento en palabras. El Jefe no lee `period.created` («qué se supone que pasó en ese evento solo
     * ustedes los programadores lo entienden», informe de QA del rol Jefe). Si el evento no está en
     * `lang/es/auditoria.php` se devuelve su nombre técnico, para no esconder nada.
     */
    public static function evento(?string $tipo): string
    {
        $tipo = trim((string) $tipo);
        if ($tipo === '') {
            return __('Cambio directo en la base de datos');
        }

        return self::diccionario('eventos')[$tipo] ?? $tipo;
    }

    /** La tabla que cambió, en palabras. */
    public static function entidad(?string $tabla): string
    {
        $tabla = trim((string) $tabla);

        return $tabla === '' ? '—' : (self::diccionario('entidades')[$tabla] ?? $tabla);
    }

    /**
     * Los campos que de verdad cambiaron, en palabras: «Estado: Activa → Retirada». Se omiten los que no
     * cambiaron y las marcas de tiempo técnicas, que son ruido en cada fila. La fila cruda sigue disponible
     * en la pantalla, al desplegar el evento.
     *
     * @return array<int, array{campo: string, antes: string, despues: string}>
     */
    public static function cambios(?string $antes, ?string $despues): array
    {
        $viejos = self::fila($antes);
        $nuevos = self::fila($despues);
        $campos = self::diccionario('campos');
        $cambios = [];

        foreach (array_keys($viejos + $nuevos) as $columna) {
            if (in_array($columna, self::RUIDO, true)) {
                continue;
            }
            $antesValor = $viejos[$columna] ?? null;
            $despuesValor = $nuevos[$columna] ?? null;
            if ($antesValor === $despuesValor) {
                continue;
            }
            $cambios[] = [
                'campo' => $campos[$columna] ?? $columna,
                'antes' => self::valor($antesValor),
                'despues' => self::valor($despuesValor),
            ];
        }

        return $cambios;
    }

    /** Los cambios en una línea, para la exportación: «Estado: Activa → Retirada; Motivo: — → Se cambió de grupo». */
    public static function cambiosEnTexto(?string $antes, ?string $despues): string
    {
        $cambios = array_map(fn (array $c) => "{$c['campo']}: {$c['antes']} → {$c['despues']}", self::cambios($antes, $despues));

        return $cambios === [] ? '—' : implode('; ', $cambios);
    }

    /** Columnas que no se muestran en los cambios: son ruido técnico en cada fila. */
    private const RUIDO = ['created_at', 'updated_at', 'remember_token', 'password', 'ulid'];

    /**
     * @return array<string, string>
     */
    private static function diccionario(string $grupo): array
    {
        $diccionario = trans('auditoria.'.$grupo);

        return is_array($diccionario) ? $diccionario : [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function fila(?string $json): array
    {
        $fila = json_decode((string) $json, true);

        return is_array($fila) ? $fila : [];
    }

    /**
     * Un valor en palabras: los estados con su nombre, los booleanos como Sí o No y el vacío como guion. Las
     * columnas JSON (opciones, metadatos) salen como su JSON compacto, para no esconder nada.
     */
    private static function valor(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }
        if (is_bool($valor)) {
            return $valor ? __('Sí') : __('No');
        }
        if (is_array($valor)) {
            return (string) json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $texto = (string) $valor;

        return self::diccionario('valores')[$texto] ?? $texto;
    }

    /**
     * Paginación sin total (simplePaginate): contar las filas de una tabla de auditoría particionada y de
     * varios millones de eventos tardaba más que traer la página, y la pantalla no llegaba a cargar
     * (revisión de diseño del 25/10/2026). La tabla se lee hacia atrás por `occurred_at`, que tiene índice.
     *
     * @param  array{desde?: string, hasta?: string, accion?: string, entidad?: string, buscar?: string}  $filtros
     */
    public function listar(User $actor, array $filtros, int $porPagina = 25): Paginator
    {
        return $this->conAlcance($actor, fn (Builder $q) => $q->orderByDesc('audit_logs.occurred_at')->simplePaginate($porPagina), $filtros);
    }

    /**
     * Exportación CSV (CP-RF05-02): los mismos eventos y filtros que la consulta, hasta 10 000 filas.
     *
     * @param  array{desde?: string, hasta?: string, accion?: string, entidad?: string, buscar?: string}  $filtros
     * @return array{columnas: array<int, string>, filas: array<int, array<int, string|int|null>>}
     */
    public function exportar(User $actor, array $filtros): array
    {
        $filas = $this->conAlcance($actor, fn (Builder $q) => $q->orderByDesc('audit_logs.occurred_at')->limit(self::MAXIMO_EXPORTACION)->get(), $filtros);

        return [
            'columnas' => [__('Fecha (UTC)'), __('Usuario'), __('IP'), __('Qué pasó'), __('Sobre'), __('ID'), __('Acción'), __('Qué cambió'), __('Motivo'), __('Evento técnico')],
            'filas' => $filas->map(fn (object $e) => [(string) $e->occurred_at, $e->actor ?? __('Sistema'), $e->ip_address,
                self::evento($e->event_type), self::entidad($e->entity_type), $e->entity_id, $e->action,
                self::cambiosEnTexto($e->old_values, $e->new_values), $e->reason, $e->event_type])->all(),
        ];
    }

    /**
     * Entidades para el filtro. Es un `distinct` sobre toda la tabla, así que se guarda media hora: la
     * lista de entidades auditadas cambia cuando cambia el esquema, no cuando llega un evento.
     *
     * @return array<int, string>
     */
    public function entidades(User $actor): array
    {
        $alcance = $this->alcance->exigir($actor, 'auditoria.ver') === 'G'
            ? 'global'
            : 'dep:'.implode(',', $this->alcance->departamentos($actor));

        return Cache::remember("auditoria.entidades.{$alcance}", now()->addMinutes(self::ENTIDADES_EN_CACHE),
            fn () => $this->conAlcance($actor, fn (Builder $q) => $q->distinct()->orderBy('entity_type')->pluck('entity_type')->all(), []));
    }

    /**
     * @template T
     *
     * @param  callable(Builder): T  $consulta
     * @param  array<string, string>  $filtros
     * @return T
     */
    private function conAlcance(User $actor, callable $consulta, array $filtros): mixed
    {
        $global = $this->alcance->exigir($actor, 'auditoria.ver') === 'G';
        $departamentos = $global ? [] : $this->alcance->departamentos($actor);

        return DB::transaction(function () use ($global, $departamentos, $consulta, $filtros) {
            DbContext::auditScope($global, $departamentos);
            $buscar = mb_strtolower(trim($filtros['buscar'] ?? ''));

            $q = DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.actor_user_id')
                ->select('audit_logs.*', DB::raw("users.first_name || ' ' || users.last_name as actor"))
                ->when(! $global, fn ($q) => $q->whereIn('audit_logs.department_id', $departamentos))
                ->when($filtros['desde'] ?? null, fn ($q, $d) => $q->where('audit_logs.occurred_at', '>=', $d.' 00:00:00'))
                ->when($filtros['hasta'] ?? null, fn ($q, $h) => $q->where('audit_logs.occurred_at', '<=', $h.' 23:59:59'))
                ->when($filtros['accion'] ?? null, fn ($q, $a) => $q->where('audit_logs.action', $a))
                ->when($filtros['entidad'] ?? null, fn ($q, $e) => $q->where('audit_logs.entity_type', $e))
                ->when($buscar !== '', fn ($q) => $q->where(fn ($q) => $q->whereRaw('lower(users.institutional_email) like ?', ["%{$buscar}%"])
                    ->orWhereRaw("lower(users.first_name || ' ' || users.last_name) like ?", ["%{$buscar}%"])
                    ->orWhereRaw('lower(audit_logs.event_type) like ?', ["%{$buscar}%"])));

            return $consulta($q);
        });
    }
}
