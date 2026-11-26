<?php

namespace App\Modules\Academica\Services;

use App\Models\User;
use App\Modules\Academica\Events\PeriodoReabierto;
use App\Modules\Calificaciones\Services\CalificacionService;
use App\Support\Alcance;
use App\Support\DbContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Períodos académicos (RF06, Ciclo de vida del período académico).
 * Etapas: planificación → horarios → asignación docente → listo; estados: planificado → en curso → cerrado.
 * Solo puede haber uno en curso. El cierre exige todas las autorizaciones departamentales (RF34); la reapertura la hace
 * solo el Administrador, con motivo.
 */
class PeriodoService
{
    public const ETAPAS = ['planning' => 'Planificación', 'schedule_definition' => 'Definición de horarios', 'teacher_assignment' => 'Asignación docente', 'ready' => 'Listo'];

    public const ESTADOS = ['planned' => 'Planificado', 'active' => 'En curso', 'closed' => 'Cerrado'];

    public function __construct(private Alcance $alcance, private CalificacionService $calificaciones) {}

    public static function activoId(): ?int
    {
        $id = DB::table('academic_periods')->where('status', 'active')->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Ciclo del año del período (ADR-050): 1 o 2. Toma el mes central entre el inicio y el fin (2026-II empieza el
     * 27/07 pero es ciclo II); si ese mes es igual o posterior a `ciclo_ii_mes_inicio` (8 por defecto) es el ciclo II.
     * Sin período (por defecto, el activo) devuelve 1.
     */
    /**
     * Períodos que se pueden preparar o trabajar: el que está en curso y los planificados (Ciclo de vida del período:
     * el siguiente se prepara mientras otro está activo). El activo va primero.
     *
     * @return Collection<int, \stdClass>
     */
    public static function preparables(): Collection
    {
        return DB::table('academic_periods')->whereIn('status', ['active', 'planned'])
            ->orderByRaw("case status when 'active' then 0 else 1 end")->orderBy('start_date')->get(['id', 'name', 'status'])
            ->each(function (object $p): void {
                $p->id = (int) $p->id;
            });
    }

    /**
     * El período pedido si es preparable; si no, el que está en curso o, sin él, el primero planificado.
     */
    public static function preparableOActivo(?int $periodoId): ?int
    {
        $preparables = self::preparables();

        $elegido = $preparables->firstWhere('id', $periodoId) ?? $preparables->first();

        return $elegido === null ? null : (int) $elegido->id;
    }

    public static function ciclo(?int $periodoId = null): int
    {
        $periodoId ??= self::activoId();
        $periodo = $periodoId === null ? null : DB::table('academic_periods')->where('id', $periodoId)->first(['start_date', 'end_date']);
        if ($periodo === null) {
            return 1;
        }
        $inicio = Carbon::parse($periodo->start_date)->startOfDay();
        $medio = $inicio->copy()->addDays(intdiv((int) abs($inicio->diffInDays(Carbon::parse($periodo->end_date)->startOfDay())), 2));
        $mesCicloII = (int) (json_decode((string) DB::table('system_settings')->where('key', 'ciclo_ii_mes_inicio')->value('value'), true) ?? 8);

        return $medio->month >= $mesCicloII ? 2 : 1;
    }

    /**
     * @return Collection<int, object>
     */
    public function listar(User $actor, bool $archivados = false): Collection
    {
        $this->alcance->exigir($actor, 'periodos.gestionar');

        // ADR-092: los archivados salen de la lista principal y se ven aparte.
        return DB::table('academic_periods as p')->when($archivados, fn ($q) => $q->whereNotNull('p.archived_at'), fn ($q) => $q->whereNull('p.archived_at'))
            ->select('p.*', DB::raw('(select count(*) from sections s where s.period_id = p.id) as secciones'),
                DB::raw("(select count(*) from enrollments e where e.period_id = p.id and e.status = 'active') as matriculas"))
            ->orderByDesc('start_date')->get();
    }

    /**
     * Archiva o desarchiva un período (ADR-092). Solo se archiva uno cerrado: sale de la lista principal y de los
     * selectores de informes, y su historial queda intacto.
     *
     * @throws ValidationException
     */
    public function archivar(User $actor, int $periodoId, bool $archivar): void
    {
        $this->alcance->exigir($actor, 'periodos.gestionar');
        $periodo = DB::table('academic_periods')->where('id', $periodoId)->first(['status']) ?? abort(404);
        if ($archivar && $periodo->status !== 'closed') {
            throw ValidationException::withMessages(['estado' => __('Solo se archiva un período cerrado.')]);
        }

        DbContext::run(fn () => DB::table('academic_periods')->where('id', $periodoId)->update(['archived_at' => $archivar ? now() : null, 'updated_at' => now()]),
            $archivar ? 'academic_period.archived' : 'academic_period.unarchived');
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * El histórico del período como tabla plana, para exportarlo (RF06, ADR-049): una fila por departamento
     * con sus secciones, su matrícula y su autorización de cierre.
     *
     * @return array{columnas: array<int, string>, filas: array<int, array<int, string|int|null>>, titulo: string, subtitulo: string}
     */
    public function detalleExportable(User $actor, int $periodoId): array
    {
        $detalle = $this->detalle($actor, $periodoId);
        $periodo = $detalle['periodo'];

        $matriculaPorDepartamento = DB::table('enrollments as e')->join('sections as s', 's.id', '=', 'e.section_id')
            ->join('subjects as su', 'su.id', '=', 's.subject_id')
            ->where('e.period_id', $periodoId)->where('e.status', 'active')
            ->groupBy('su.department_id')->selectRaw('su.department_id as department_id, count(*) as total')->pluck('total', 'department_id');

        $fecha = fn (?string $f) => $f === null ? null : Carbon::parse($f)->timezone('America/El_Salvador')->format('d/m/Y H:i');

        return [
            'columnas' => [__('Código'), __('Departamento'), __('Grupos'), __('Matrículas'), __('Cierre autorizado'), __('Autorizó')],
            'filas' => $detalle['cierres']->map(fn (object $c) => [
                $c->code,
                $c->name,
                $c->secciones,
                (int) ($matriculaPorDepartamento[$c->id] ?? 0),
                $fecha($c->authorized_at) ?? __('Sin autorizar'),
                $c->autorizo,
            ])->values()->all(),
            'titulo' => __('Período :nombre', ['nombre' => $periodo->name]),
            'subtitulo' => __(':inicio – :fin · :estado · :s grupos teóricos · :m matrículas', [
                'inicio' => Carbon::parse($periodo->start_date)->format('d/m/Y'),
                'fin' => Carbon::parse($periodo->end_date)->format('d/m/Y'),
                'estado' => __(self::ESTADOS[$periodo->status] ?? $periodo->status),
                's' => $detalle['secciones'],
                'm' => $detalle['matriculas'],
            ]),
        ];
    }

    public function detalle(User $actor, int $periodoId): array
    {
        $this->alcance->exigir($actor, 'periodos.gestionar');
        $periodo = DB::table('academic_periods')->where('id', $periodoId)->first() ?? abort(404);

        $cierres = DB::table('departments as d')
            ->leftJoin('department_closures as c', fn ($j) => $j->on('c.department_id', '=', 'd.id')->where('c.period_id', $periodoId))
            ->leftJoin('users as u', 'u.id', '=', 'c.authorized_by')
            ->orderBy('d.code')
            ->get(['d.id', 'd.code', 'd.name', 'c.authorized_at', DB::raw("u.first_name || ' ' || u.last_name as autorizo")]);

        $seccionesPorDepartamento = DB::table('sections')->join('subjects', 'subjects.id', '=', 'sections.subject_id')
            ->where('sections.period_id', $periodoId)->groupBy('subjects.department_id')
            ->selectRaw('subjects.department_id as department_id, count(*) as total')->pluck('total', 'department_id');
        $cierres->each(fn ($c) => $c->secciones = (int) ($seccionesPorDepartamento[$c->id] ?? 0));

        return [
            'periodo' => $periodo,
            'computos' => DB::table('grading_component_templates')->where('period_id', $periodoId)->orderBy('number')->get(),
            'cierres' => $cierres,
            'todosAutorizados' => $cierres->where('secciones', '>', 0)->every(fn ($c) => $c->authorized_at !== null),
            'secciones' => DB::table('sections')->where('period_id', $periodoId)->count(),
            'matriculas' => DB::table('enrollments')->where('period_id', $periodoId)->where('status', 'active')->count(),
        ];
    }

    /**
     * Cómputos de cada período (ADR-085): número => [peso en la nota, parte del período que dura]. Pesan igual; el primero
     * dura más y el último menos. Los pesos siguen siendo provisionales hasta el reglamento académico (ADR-030).
     *
     * @var array<int, array{0: int, 1: int}>
     */
    public const COMPUTOS = [1 => [25, 30], 2 => [25, 25], 3 => [25, 25], 4 => [25, 20]];

    /**
     * Filas de grading_component_templates para un período, sin period_id. Cada cómputo empieza el día siguiente al fin
     * del anterior y el último termina el último día del período.
     *
     * @return array<int, array{number: int, name: string, weight_percent: int, starts_on: string, ends_on: string}>
     */
    public static function plantillaComputos(string $inicio, string $fin): array
    {
        $desde = Carbon::parse($inicio)->startOfDay();
        $hasta = Carbon::parse($fin)->startOfDay();
        $dias = (int) $desde->diffInDays($hasta) + 1;
        $filas = [];
        $acumulado = 0;
        $comienzo = $desde->copy();
        foreach (self::COMPUTOS as $numero => [$peso, $parte]) {
            $acumulado += $parte;
            $termina = $numero === array_key_last(self::COMPUTOS) ? $hasta->copy() : $desde->copy()->addDays((int) round($dias * $acumulado / 100) - 1);
            $filas[] = ['number' => $numero, 'name' => "Cómputo {$numero}", 'weight_percent' => $peso, 'starts_on' => $comienzo->toDateString(), 'ends_on' => $termina->toDateString()];
            $comienzo = $termina->copy()->addDay();
        }

        return $filas;
    }

    /**
     * Crea el período en planificación con los cuatro cómputos de la plantilla (ADR-085).
     */
    public function crear(User $actor, string $nombre, string $inicio, string $fin): int
    {
        $this->alcance->exigir($actor, 'periodos.gestionar');
        if (DB::table('academic_periods')->where('name', $nombre)->exists()) {
            throw ValidationException::withMessages(['nombre' => __('Ya existe un período con ese nombre.')]);
        }
        if (Carbon::parse($fin)->lte(Carbon::parse($inicio))) {
            throw ValidationException::withMessages(['fin' => __('La fecha de fin debe ser posterior a la de inicio.')]);
        }
        // H-03 (QA 27/10): dos períodos no cruzan sus fechas, en ningún estado. Mismo criterio semiabierto
        // «[inicio, fin)» que el EXCLUDE de 13_period_no_overlap.sql: compartir el día del borde no es cruzarse.
        $choca = DB::table('academic_periods')->where('start_date', '<', $fin)->where('end_date', '>', $inicio)->orderBy('start_date')->first(['name', 'start_date', 'end_date']);
        if ($choca !== null) {
            throw ValidationException::withMessages(['inicio' => __('Las fechas se cruzan con el período :nombre (:desde – :hasta).', [
                'nombre' => $choca->name,
                'desde' => Carbon::parse($choca->start_date)->format('d/m/Y'),
                'hasta' => Carbon::parse($choca->end_date)->format('d/m/Y'),
            ])]);
        }

        return DbContext::run(function () use ($nombre, $inicio, $fin) {
            $id = DB::table('academic_periods')->insertGetId(['name' => $nombre, 'start_date' => $inicio, 'end_date' => $fin,
                'status' => 'planned', 'preparation_stage' => 'planning', 'created_at' => now(), 'updated_at' => now()]);
            foreach (self::plantillaComputos($inicio, $fin) as $computo) {
                DB::table('grading_component_templates')->insert($computo + ['period_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }

            return $id;
        }, 'period.created');
    }

    public function avanzarEtapa(User $actor, int $periodoId): string
    {
        $this->alcance->exigir($actor, 'periodos.gestionar');
        $periodo = DB::table('academic_periods')->where('id', $periodoId)->first() ?? abort(404);
        $etapas = array_keys(self::ETAPAS);
        $siguiente = $etapas[array_search($periodo->preparation_stage, $etapas, true) + 1] ?? null;

        if ($periodo->status !== 'planned' || $siguiente === null) {
            throw ValidationException::withMessages(['etapa' => __('El período ya completó su preparación.')]);
        }
        $this->exigirEtapaCumplida($periodoId, (string) $periodo->preparation_stage);
        DbContext::run(fn () => DB::table('academic_periods')->where('id', $periodoId)->update(['preparation_stage' => $siguiente, 'updated_at' => now()]), 'period.stage_changed');

        return $siguiente;
    }

    public function activar(User $actor, int $periodoId): void
    {
        $this->alcance->exigir($actor, 'periodos.gestionar');
        $periodo = DB::table('academic_periods')->where('id', $periodoId)->first() ?? abort(404);

        if ($periodo->status !== 'planned' || $periodo->preparation_stage !== 'ready') {
            throw ValidationException::withMessages(['estado' => __('Solo se activa un período planificado que terminó su preparación.')]);
        }
        // La preparación pudo cambiar después de llegar a «Listo» (un grupo nuevo sin docente): se vuelve a comprobar.
        $this->exigirEtapaCumplida($periodoId, 'teacher_assignment');

        // Dos activaciones a la vez: el índice parcial de un solo período activo rechaza la segunda; se traduce.
        try {
            DbContext::run(function () use ($periodoId): void {
                if (DB::table('academic_periods')->where('status', 'active')->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages(['estado' => __('Ya hay un período en curso: solo puede haber uno activo.')]);
                }
                DB::table('academic_periods')->where('id', $periodoId)->update(['status' => 'active', 'updated_at' => now()]);
            }, 'period.activated');
        } catch (QueryException) {
            throw ValidationException::withMessages(['estado' => __('Ya hay un período en curso: solo puede haber uno activo.')]);
        }
    }

    /**
     * Condiciones para dejar una etapa de la preparación (Ciclo de vida del período académico):
     * planificación → hay grupos teóricos (la oferta); horarios → cada grupo tiene horario con aula;
     * asignación docente → cada grupo tiene docente. Antes se avanzaba y se activaba un período sin grupos.
     *
     * @throws ValidationException
     */
    private function exigirEtapaCumplida(int $periodoId, string $etapa): void
    {
        $grupos = DB::table('sections')->where('period_id', $periodoId);
        if (! (clone $grupos)->exists()) {
            throw ValidationException::withMessages(['etapa' => __('El período aún no tiene grupos teóricos: defina la oferta en la carga académica.')]);
        }
        if (in_array($etapa, ['schedule_definition', 'teacher_assignment'], true)) {
            $sinHorario = (clone $grupos)->whereNotExists(fn ($q) => $q->from('section_schedules as h')->whereColumn('h.section_id', 'sections.id')->whereNotNull('h.classroom_id'))->count();
            if ($sinHorario > 0) {
                throw ValidationException::withMessages(['etapa' => trans_choice('{1} Falta 1 grupo teórico sin horario con aula.|[2,*] Faltan :count grupos teóricos sin horario con aula.', $sinHorario)]);
            }
        }
        if ($etapa === 'teacher_assignment') {
            $sinDocente = (clone $grupos)->whereNotExists(fn ($q) => $q->from('teaching_assignments as t')->whereColumn('t.section_id', 'sections.id')->whereNull('t.ended_at'))->count();
            if ($sinDocente > 0) {
                throw ValidationException::withMessages(['etapa' => trans_choice('{1} Falta 1 grupo teórico sin docente.|[2,*] Faltan :count grupos teóricos sin docente.', $sinDocente)]);
            }
        }
    }

    /**
     * Cierra el período en curso (RF34, RF17): exige que todos los departamentos con secciones hayan autorizado,
     * congela las notas finales en `enrollment_results` y lo pasa a cerrado, todo en una sola transacción.
     *
     * @return int Cantidad de resultados congelados.
     */
    public function cerrar(User $actor, int $periodoId): int
    {
        $this->alcance->exigir($actor, 'periodos.gestionar');

        // La lectura y las validaciones van dentro de la transacción, con el período bloqueado: dos cierres a la vez
        // esperan en fila y el segundo ya lo encuentra cerrado.
        return DbContext::run(function () use ($periodoId) {
            $periodo = DB::table('academic_periods')->where('id', $periodoId)->lockForUpdate()->first() ?? abort(404);
            if ($periodo->status !== 'active') {
                throw ValidationException::withMessages(['estado' => __('Solo se cierra un período en curso.')]);
            }

            $pendientes = DB::table('departments as d')
                ->whereIn('d.id', DB::table('sections')->join('subjects', 'subjects.id', '=', 'sections.subject_id')->where('sections.period_id', $periodoId)->select('subjects.department_id'))
                ->whereNotIn('d.id', DB::table('department_closures')->where('period_id', $periodoId)->select('department_id'))
                ->orderBy('d.code')->pluck('d.name');
            if ($pendientes->isNotEmpty()) {
                throw ValidationException::withMessages(['cierre' => __('Faltan departamentos por autorizar: :lista.', ['lista' => $pendientes->implode(', ')])]);
            }
            // Además de las autorizaciones, cada acta del período debe estar validada (RF34): una autorización no
            // garantiza que después no se haya devuelto un acta.
            $sinValidar = DB::table('sections')->join('subjects', 'subjects.id', '=', 'sections.subject_id')
                ->leftJoin('grade_sheets as g', 'g.section_id', '=', 'sections.id')
                ->where('sections.period_id', $periodoId)->where(fn ($q) => $q->whereNull('g.status')->orWhere('g.status', '!=', 'validated'))
                ->orderBy('subjects.code')->get(['subjects.code', 'sections.code as grupo'])->map(fn ($s) => $s->code.'-'.$s->grupo);
            if ($sinValidar->isNotEmpty()) {
                throw ValidationException::withMessages(['cierre' => __('Hay actas sin validar: :lista.', ['lista' => $sinValidar->implode(', ')])]);
            }

            $congelados = $this->calificaciones->congelarResultados($periodoId);
            DB::table('academic_periods')->where('id', $periodoId)->update(['status' => 'closed', 'updated_at' => now()]);

            return $congelados;
        }, 'period.closed');
    }

    /**
     * Reabre un período cerrado (RF06, Ciclo de vida del período académico): solo el Administrador
     * (`periodos.gestionar` G), con motivo obligatorio, que queda en la auditoría, y sin otro período en curso.
     * Conserva las autorizaciones de cierre y los resultados congelados: al volver a cerrar, congelarResultados()
     * los recalcula. Avisa a los Coordinadores.
     */
    public function reabrir(User $actor, int $periodoId, string $motivo): void
    {
        $this->alcance->exigir($actor, 'periodos.gestionar');
        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 5) {
            throw ValidationException::withMessages(['motivo' => __('Escriba el motivo de la reapertura (al menos 5 caracteres).')]);
        }

        // Mismo candado que cerrar(): una reapertura y un cierre simultáneos no se pisan.
        DbContext::run(function () use ($periodoId): void {
            $periodo = DB::table('academic_periods')->where('id', $periodoId)->lockForUpdate()->first() ?? abort(404);
            if ($periodo->status !== 'closed') {
                throw ValidationException::withMessages(['estado' => __('Solo se reabre un período cerrado.')]);
            }
            if (DB::table('academic_periods')->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['estado' => __('Ya hay un período en curso: solo puede haber uno activo.')]);
            }
            DB::table('academic_periods')->where('id', $periodoId)->update(['status' => 'active', 'updated_at' => now()]);
        }, 'period.reopened', $motivo);

        PeriodoReabierto::dispatch($periodoId, $motivo, (string) now()->timestamp);
    }
}
