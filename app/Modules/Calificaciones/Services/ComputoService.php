<?php

namespace App\Modules\Calificaciones\Services;

use App\Models\User;
use App\Support\Alcance;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pesos de la sección (RF17, Flujo Cálculo de nota final): los cómputos deben sumar 100 % y, dentro de cada
 * cómputo, sus actividades también. La sección recibe los cómputos de la plantilla del período al crearse
 * (`grading_component_templates`); el docente ajusta los pesos mientras no haya notas publicadas ni acta publicada.
 */
class ComputoService
{
    public function __construct(private Alcance $alcance) {}

    /**
     * Copia la plantilla de cómputos del período a la sección, si la sección aún no tiene cómputos. No abre su
     * propio contexto: corre bajo el `DbContext::run()` de quien la llama. Es idempotente.
     *
     * @return int Cómputos copiados.
     */
    public function copiarPlantilla(int $seccionId): int
    {
        if (DB::table('grading_components')->where('section_id', $seccionId)->exists()) {
            return 0;
        }
        $periodo = DB::table('sections')->where('id', $seccionId)->value('period_id');
        if ($periodo === null) {
            return 0;
        }
        $ahora = now();
        $plantillas = DB::table('grading_component_templates')->where('period_id', $periodo)->orderBy('number')
            ->get(['number', 'name', 'starts_on', 'ends_on', 'weight_percent']);

        foreach ($plantillas as $plantilla) {
            DB::table('grading_components')->insert([
                'section_id' => $seccionId, 'number' => $plantilla->number, 'name' => $plantilla->name, 'starts_on' => $plantilla->starts_on,
                'ends_on' => $plantilla->ends_on, 'weight_percent' => $plantilla->weight_percent, 'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        }

        return $plantillas->count();
    }

    /**
     * Cómputos de la sección con sus actividades y pesos, para la pantalla de pesos (docente S).
     *
     * @return array{computos: Collection<int, \stdClass>, bloqueo: string|null, problemas: array<int, string>}
     *
     * @throws AuthorizationException
     */
    public function pesos(User $usuario, int $seccionId): array
    {
        $this->exigirSeccion($usuario, $seccionId);

        return ['computos' => $this->computos($seccionId), 'bloqueo' => $this->bloqueo($seccionId), 'problemas' => $this->problemas($seccionId)];
    }

    /**
     * Guarda los pesos de los cómputos y de las actividades de la sección (docente S). Los que no se envían
     * conservan su peso actual; la regla se verifica sobre el resultado.
     *
     * Se rechaza si la sección ya tiene notas publicadas o el acta está publicada o validada, si algún peso no es
     * mayor que 0, si los cómputos no suman exactamente 100 % o si las actividades de un cómputo pasan del 100 %.
     *
     * @param  array<int|string, int|float|string>  $computos  grading_component_id => peso
     * @param  array<int|string, int|float|string>  $actividades  grade_item_id => peso
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function guardarPesos(User $docente, int $seccionId, array $computos, array $actividades): void
    {
        $this->exigirSeccion($docente, $seccionId);
        foreach ([...array_values($computos), ...array_values($actividades)] as $peso) {
            if (! is_numeric($peso) || (float) $peso <= 0 || (float) $peso > 100) {
                throw ValidationException::withMessages(['computos' => __('Cada peso debe ser mayor que 0 % y no pasar de 100 %.')]);
            }
        }

        // El bloqueo y las sumas se verifican dentro de la transacción, con la sección bloqueada: así no se cruzan con
        // otra edición de pesos ni con la publicación del acta (que bloquea la misma fila). En SQLite no hay bloqueo
        // de filas: lockForUpdate no tiene efecto.
        DbContext::run(function () use ($seccionId, $computos, $actividades): void {
            DB::table('sections')->where('id', $seccionId)->lockForUpdate()->value('id');
            if ($motivo = $this->bloqueo($seccionId)) {
                throw ValidationException::withMessages(['computos' => $motivo]);
            }
            $this->exigirSumas($seccionId, $computos, $actividades);

            foreach ($computos as $id => $peso) {
                DB::table('grading_components')->where('id', (int) $id)->where('section_id', $seccionId)->update(['weight_percent' => round((float) $peso, 2), 'updated_at' => now()]);
            }
            foreach ($actividades as $id => $peso) {
                DB::table('grade_items')->where('id', (int) $id)->where('section_id', $seccionId)->update(['weight_percent' => round((float) $peso, 2), 'updated_at' => now()]);
            }
        }, 'grading_weights.updated');
    }

    /**
     * Rechaza ids que no son de la sección, cómputos que no suman exactamente 100 % y actividades de un cómputo que
     * pasan del 100 %, sobre el resultado de aplicar los pesos enviados a los actuales.
     *
     * @param  array<int|string, int|float|string>  $computos
     * @param  array<int|string, int|float|string>  $actividades
     *
     * @throws ValidationException
     */
    private function exigirSumas(int $seccionId, array $computos, array $actividades): void
    {
        $estructura = $this->computos($seccionId);
        $items = $estructura->flatMap(fn (object $c) => $c->items);
        foreach (array_keys($computos) as $id) {
            if (! $estructura->contains('id', (int) $id)) {
                throw ValidationException::withMessages(['computos' => __('Uno de los cómputos no pertenece a esta sección.')]);
            }
        }
        foreach (array_keys($actividades) as $id) {
            if (! $items->contains('id', (int) $id)) {
                throw ValidationException::withMessages(['actividades' => __('Una de las actividades no pertenece a esta sección.')]);
            }
        }

        $pesoComputo = fn (object $c): float => round((float) ($computos[$c->id] ?? $c->weight_percent), 2);
        $pesoItem = fn (object $i): float => round((float) ($actividades[$i->id] ?? $i->weight_percent), 2);

        $total = round($estructura->sum($pesoComputo), 2);
        if (abs($total - 100) > 0.001) {
            throw ValidationException::withMessages(['computos' => __('Los cómputos suman :s %: deben sumar exactamente 100 %.', ['s' => self::porcentaje($total)])]);
        }
        foreach ($estructura as $c) {
            $suma = round(collect($c->items)->sum($pesoItem), 2);
            if ($suma > 100.001) {
                throw ValidationException::withMessages(['actividades' => __('Las actividades de :c suman :s %: no pueden pasar del 100 %.', ['c' => $c->name, 's' => self::porcentaje($suma)])]);
            }
        }
    }

    /**
     * Problemas de pesos que impiden publicar el acta (RF17, RF34): los cómputos no suman 100 % o las actividades
     * de algún cómputo no suman 100 %. Lista vacía si todo cuadra.
     *
     * @return array<int, string>
     */
    public function problemas(int $seccionId): array
    {
        $estructura = $this->computos($seccionId);
        $problemas = [];

        $total = round($estructura->sum(fn (object $c) => (float) $c->weight_percent), 2);
        if (abs($total - 100) > 0.001) {
            $problemas[] = __('Los cómputos suman :s %: deben sumar 100 %.', ['s' => self::porcentaje($total)]);
        }
        foreach ($estructura as $c) {
            $suma = round(collect($c->items)->sum(fn (object $i) => (float) $i->weight_percent), 2);
            if (abs($suma - 100) > 0.001) {
                $problemas[] = __('Las actividades de :c suman :s %: deben sumar 100 %.', ['c' => $c->name, 's' => self::porcentaje($suma)]);
            }
        }

        return $problemas;
    }

    /**
     * Por qué no se pueden cambiar los pesos, o null si se puede: hay notas publicadas o el acta está publicada o
     * validada.
     */
    public function bloqueo(int $seccionId): ?string
    {
        if (DB::table('grade_sheets')->where('section_id', $seccionId)->whereIn('status', ['published', 'validated'])->exists()) {
            return __('El acta está publicada: no se pueden cambiar los pesos.');
        }
        $publicadas = DB::table('grade_items')->where('section_id', $seccionId)->where('is_published', true)->exists()
            || DB::table('grades')->where('section_id', $seccionId)->where('status', 'published')->exists();

        return $publicadas ? __('La sección ya tiene notas publicadas: no se pueden cambiar los pesos.') : null;
    }

    public static function porcentaje(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.');
    }

    /**
     * @return Collection<int, \stdClass>
     */
    private function computos(int $seccionId): Collection
    {
        $items = DB::table('grade_items')->where('section_id', $seccionId)->orderBy('id')->get(['id', 'component_id', 'title', 'weight_percent', 'is_published']);

        return DB::table('grading_components')->where('section_id', $seccionId)->orderBy('number')->get(['id', 'number', 'name', 'weight_percent'])
            ->each(fn (object $c) => $c->items = $items->where('component_id', $c->id)->values());
    }

    /**
     * @throws AuthorizationException
     */
    private function exigirSeccion(User $usuario, int $seccionId): void
    {
        $this->alcance->exigir($usuario, 'calificaciones.gestionar');
        if (! $this->alcance->puedeEnSeccion($usuario, 'calificaciones.gestionar', $seccionId)) {
            throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        }
    }
}
