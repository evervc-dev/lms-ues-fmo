<?php

namespace App\Modules\Calificaciones\Services;

use App\Models\User;
use App\Modules\Calificaciones\Events\ActaDevuelta;
use App\Modules\Calificaciones\Events\ActaPublicada;
use App\Modules\Calificaciones\Events\NotaCorregida;
use App\Modules\Calificaciones\Events\NotasPublicadas;
use App\Modules\Calificaciones\Jobs\EvaluarRiesgoAcademico;
use App\Support\Alcance;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Calificaciones (RF17, RF18, RF34; Flujo Cálculo de nota final, Flujo Validación de actas y cierre).
 *
 * Nota final = Σ (peso del cómputo × nota del cómputo); nota del cómputo = promedio ponderado de sus
 * actividades. Parámetros provisionales (system_settings, ADR-030): nota faltante = 0, 1 decimal mitad
 * hacia arriba, se aprueba con 6.0.
 */
class CalificacionService
{
    public function __construct(private Alcance $alcance, private ComputoService $computos) {}

    public static function notaMinima(): float
    {
        return (float) (json_decode((string) DB::table('system_settings')->where('key', 'passing_score')->value('value'), true) ?? 6.0);
    }

    public static function redondear(float $nota): float
    {
        return round($nota, 1, PHP_ROUND_HALF_UP);
    }

    /** Tope de saltos al caminar la cadena de traslados: corta una cadena cíclica si los datos vinieran mal. */
    private const MAX_CADENA = 20;

    /**
     * Matrículas anteriores de un cambio de grupo (RF09, [[ADR-062]]), de la más reciente a la más vieja.
     * Se camina `enrollments.transferred_to_id` hacia atrás: la matrícula transferida apunta a la que la
     * reemplazó. Sin traslados devuelve una colección vacía, que es el caso normal.
     *
     * @return Collection<int, object> {id, section_id, grupo}
     */
    public function cadena(int $matriculaId): Collection
    {
        $anteriores = collect();
        $actual = $matriculaId;

        while (count($anteriores) < self::MAX_CADENA) {
            $previa = DB::table('enrollments as e')->join('sections as s', 's.id', '=', 'e.section_id')
                ->where('e.transferred_to_id', $actual)->first(['e.id', 'e.section_id', 's.code as grupo']);
            if ($previa === null) {
                break;
            }
            $anteriores->push($previa);
            $actual = (int) $previa->id;
        }

        return $anteriores;
    }

    /**
     * Notas de cómputo heredadas de la cadena, por `number` del cómputo ([[ADR-062]]).
     *
     * Las actividades (`grade_items`) llevan `section_id` y `grades` tiene dos claves foráneas compuestas que
     * obligan a que la nota, la actividad y la matrícula sean de la misma sección: por eso **no se encadenan
     * por actividad**. Lo comparable entre los dos grupos es el **cómputo**, porque las dos secciones copian
     * la misma plantilla del período (`ComputoService::copiarPlantilla()`), que fija su `number`. Para un mismo
     * `number` gana la matrícula más reciente que tenga notas.
     *
     * @return Collection<int, object> number => {nota, grupo, seccion_id, matricula_id}
     */
    public function notasDeCadena(int $matriculaId): Collection
    {
        $heredadas = collect();

        foreach ($this->cadena($matriculaId) as $previa) {
            $notas = DB::table('grades')->where('enrollment_id', $previa->id)->where('status', 'published')
                ->get(['grade_item_id', 'score'])->keyBy('grade_item_id');

            foreach ($this->estructura((int) $previa->section_id) as $computo) {
                $number = (int) $computo->number;
                if ($heredadas->has($number) || ! self::tieneNotasDelComputo($computo, $notas)) {
                    continue;
                }
                $heredadas->put($number, (object) [
                    'nota' => $this->notaComputo($computo, $notas, soloPublicadas: true),
                    'grupo' => $previa->grupo,
                    'seccion_id' => (int) $previa->section_id,
                    'matricula_id' => (int) $previa->id,
                ]);
            }
        }

        return $heredadas;
    }

    /**
     * Los cómputos que de verdad se heredan: de la cadena se quedan solo aquellos en los que el estudiante
     * **no tiene ninguna nota** en el grupo nuevo ([[ADR-062]], punto 3). Es el guardia de la herencia,
     * resuelto una sola vez y en el Service, para que la pantalla no tenga que repetirlo y para que
     * `inherited_from` del congelado no declare una herencia que no ocurrió.
     *
     * @template TNota of object
     *
     * @param  Collection<int, object>  $computos  de estructura()
     * @param  Collection<int|string, TNota>  $notas  del grupo nuevo, por grade_item_id
     * @param  Collection<int, object>  $cadena  de notasDeCadena()
     * @return Collection<int, object> number => heredada
     */
    public function heredadasEfectivas(Collection $computos, Collection $notas, Collection $cadena): Collection
    {
        if ($cadena->isEmpty()) {
            return $cadena;
        }

        $efectivas = collect();
        foreach ($computos as $computo) {
            $heredada = $cadena->get((int) $computo->number);
            if ($heredada !== null && ! self::tieneNotasDelComputo($computo, $notas)) {
                $efectivas->put((int) $computo->number, $heredada);
            }
        }

        return $efectivas;
    }

    /**
     * Las cadenas de varias matrículas en bloque, para el libro y el congelado. Una sola consulta averigua
     * cuáles tienen una matrícula anterior; sin traslados —el caso normal— no hay más trabajo.
     *
     * @param  iterable<int, int|string>  $matriculas
     * @return Collection<int, Collection<int, object>> matricula_id => heredadas por number
     */
    public function cadenasDeSeccion(iterable $matriculas): Collection
    {
        $ids = collect($matriculas)->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            return collect();
        }

        return DB::table('enrollments')->whereIn('transferred_to_id', $ids)->pluck('transferred_to_id')
            ->unique()->mapWithKeys(fn ($id) => [(int) $id => $this->notasDeCadena((int) $id)]);
    }

    /**
     * ¿La matrícula tiene al menos una nota en alguna actividad de este cómputo? Es el disparador de la
     * herencia de [[ADR-062]]: el cómputo heredado solo entra cuando en el grupo nuevo no hay ninguna nota.
     *
     * @template TNota of object
     *
     * @param  Collection<int|string, TNota>  $notas  por grade_item_id
     */
    public static function tieneNotasDelComputo(object $computo, Collection $notas): bool
    {
        return collect($computo->items)->contains(fn (object $item) => $notas->has($item->id));
    }

    /**
     * Estructura de la sección: cómputos con sus actividades.
     *
     * @return Collection<int, object>
     */
    public function estructura(int $seccionId): Collection
    {
        $items = DB::table('grade_items')->leftJoin('units', 'units.id', '=', 'grade_items.unit_id')->where('grade_items.section_id', $seccionId)
            ->orderBy('grade_items.id')->get(['grade_items.*', 'units.title as unidad']);

        return DB::table('grading_components')->where('section_id', $seccionId)->orderBy('number')->get()
            ->each(fn ($c) => $c->items = $items->where('component_id', $c->id)->values());
    }

    /**
     * Libro de calificaciones (docente S, coordinador C en lectura): estudiantes × actividades y nota final.
     *
     * @return array{computos: Collection<int, object>, filas: Collection<int, object>, cerrado: bool}
     */
    public function libro(User $usuario, int $seccionId): array
    {
        // El estudiante también tiene `calificaciones.ver`, pero con alcance P: su vista es boleta(), no el libro
        // de toda la sección. Sin este filtro, un matriculado leería las notas de sus compañeros.
        abort_unless(in_array($this->alcance->de($usuario, 'calificaciones.ver'), ['G', 'D', 'C', 'S'], true)
            && $this->alcance->puedeEnSeccion($usuario, 'calificaciones.ver', $seccionId), 403);
        $computos = $this->estructura($seccionId);
        $notas = DB::table('grades')->where('section_id', $seccionId)->get(['id', 'grade_item_id', 'enrollment_id', 'score', 'status'])->groupBy('enrollment_id');

        $matriculas = DB::table('enrollments as e')->join('users as u', 'u.id', '=', 'e.student_id')->leftJoin('student_profiles as p', 'p.user_id', '=', 'u.id')
            ->where('e.section_id', $seccionId)->where('e.status', 'active')->orderBy('u.last_name')
            ->get(['e.id', 'e.student_id', 'u.first_name', 'u.last_name', 'p.student_code']);
        $cadenas = $this->cadenasDeSeccion($matriculas->pluck('id'));

        $filas = $matriculas->map(function ($f) use ($computos, $notas, $cadenas) {
            $f->notas = ($notas[$f->id] ?? collect())->keyBy('grade_item_id');
            $f->heredadas = $this->heredadasEfectivas($computos, $f->notas, $cadenas->get((int) $f->id) ?? collect());
            $f->final = $this->notaFinal($computos, $f->notas, soloPublicadas: false, cadena: $f->heredadas);

            return $f;
        });

        return ['computos' => $computos, 'filas' => $filas, 'cerrado' => $this->periodoCerrado($seccionId)];
    }

    /**
     * El libro como tabla plana, para exportarlo a Excel, PDF o CSV (RF17, ADR-049). Una columna por
     * actividad, agrupadas por cómputo, más la nota final; el mismo contenido que la pantalla.
     *
     * @return array{columnas: array<int, string>, filas: array<int, array<int, string|float|null>>, titulo: string}
     */
    public function libroExportable(User $usuario, int $seccionId): array
    {
        $libro = $this->libro($usuario, $seccionId);
        $seccion = DB::table('sections')->join('subjects', 'subjects.id', '=', 'sections.subject_id')
            ->where('sections.id', $seccionId)->first(['subjects.code', 'subjects.name', 'sections.code as grupo']) ?? abort(404);

        $items = $libro['computos']->flatMap(fn (object $computo) => $computo->items->map(fn (object $item) => [
            'id' => (int) $item->id,
            'encabezado' => $computo->name.' · '.$item->title,
        ]));

        $columnas = array_merge(
            [__('Carné'), __('Estudiante')],
            $items->pluck('encabezado')->all(),
            [__('Nota final')],
        );

        $filas = $libro['filas']->map(fn (object $f) => array_merge(
            [$f->student_code ?? '', trim($f->last_name.', '.$f->first_name)],
            $items->map(fn (array $item) => ($nota = $f->notas->get($item['id'])) ? (float) $nota->score : null)->all(),
            [$f->final],
        ))->values()->all();

        return [
            'columnas' => $columnas,
            'filas' => $filas,
            'titulo' => $seccion->code.' · '.$seccion->grupo.' — '.$seccion->name,
        ];
    }

    /**
     * Boleta del estudiante en una sección (RF17): por cómputo y por unidad, solo con notas publicadas.
     *
     * @return array{computos: Collection<int, object>, notas: Collection<int|string, \stdClass>, heredadas: Collection<int, object>, final: float, porUnidad: Collection<int|string, float>}
     */
    public function boleta(User $estudiante, int $seccionId): array
    {
        $matricula = DB::table('enrollments')->where('student_id', $estudiante->id)->where('section_id', $seccionId)->where('status', 'active')->value('id') ?? abort(403);
        $computos = $this->estructura($seccionId);
        $notas = DB::table('grades')->where('enrollment_id', $matricula)->where('status', 'published')->get(['grade_item_id', 'score', 'comment'])->keyBy('grade_item_id');
        $heredadas = $this->heredadasEfectivas($computos, $notas, $this->notasDeCadena((int) $matricula));

        // El avance por unidad no se encadena: las unidades son propias de cada sección (ADR-062, RF18).
        $porUnidad = $computos->flatMap(fn ($c) => $c->items)->filter(fn ($i) => $i->is_published && $i->unidad)
            ->groupBy('unidad')->map(fn ($items) => self::redondear($items->avg(fn ($i) => (float) ($notas[$i->id]->score ?? 0))));

        return ['computos' => $computos, 'notas' => $notas, 'heredadas' => $heredadas,
            'final' => $this->notaFinal($computos, $notas, soloPublicadas: true, cadena: $heredadas), 'porUnidad' => $porUnidad];
    }

    /**
     * Notas del estudiante en todas sus materias del período y su historial (Mis notas).
     *
     * @return array{materias: Collection<int, object>, historial: Collection<int, object>}
     */
    public function misNotas(User $estudiante): array
    {
        abort_unless($estudiante->checkPermissionTo('calificaciones.ver'), 403);
        $materias = DB::table('enrollments as e')->join('sections as s', 's.id', '=', 'e.section_id')->join('subjects as a', 'a.id', '=', 's.subject_id')
            ->join('academic_periods as p', 'p.id', '=', 'e.period_id')->where('p.status', 'active')
            ->where('e.student_id', $estudiante->id)->where('e.status', 'active')->orderBy('a.code')
            ->get(['s.id as seccion_id', 's.ulid', 's.code as grupo', 'a.code', 'a.name'])
            ->map(function ($m) use ($estudiante) {
                $b = $this->boleta($estudiante, (int) $m->seccion_id);
                $m->final = $b['final'];
                $m->computos = $b['computos']->map(fn ($c) => (object) ['nombre' => $c->name, 'peso' => (float) $c->weight_percent,
                    'nota' => $this->notaComputo($c, $b['notas'], true, $b['heredadas']),
                    'heredadoDe' => $b['heredadas']->get((int) $c->number)?->grupo]);

                return $m;
            });

        $historial = DB::table('enrollment_results as r')->join('enrollments as e', 'e.id', '=', 'r.enrollment_id')->join('subjects as a', 'a.id', '=', 'e.subject_id')
            ->join('academic_periods as p', 'p.id', '=', 'e.period_id')->where('e.student_id', $estudiante->id)
            ->orderByDesc('p.start_date')->orderBy('a.code')->get(['p.name as periodo', 'a.code', 'a.name', 'r.final_score', 'r.result']);

        return ['materias' => $materias, 'historial' => $historial];
    }

    /**
     * Calificación directa de una actividad (borrador hasta publicar).
     */
    public function calificar(User $docente, int $itemId, int $matriculaId, float $nota, ?string $comentario = null): void
    {
        $item = DB::table('grade_items')->where('id', $itemId)->first() ?? abort(404);
        abort_unless($this->alcance->puedeEnSeccion($docente, 'calificaciones.gestionar', (int) $item->section_id), 403);
        if ($nota < 0 || $nota > 10) {
            throw ValidationException::withMessages(['nota' => __('La nota debe estar entre 0 y 10.')]);
        }
        if ($this->periodoCerrado((int) $item->section_id)) {
            throw ValidationException::withMessages(['nota' => __('El período está cerrado: pida la corrección al Coordinador.')]);
        }
        $this->exigirActaAbierta((int) $item->section_id);

        DbContext::run(fn () => DB::table('grades')->updateOrInsert(
            ['grade_item_id' => $itemId, 'enrollment_id' => $matriculaId],
            ['section_id' => $item->section_id, 'score' => $nota, 'score_before_penalty' => $nota, 'comment' => $comentario, 'graded_by' => $docente->id,
                'status' => $item->is_published ? 'published' : 'draft', 'published_at' => $item->is_published ? now() : null, 'updated_at' => now(), 'created_at' => now()],
        ), 'grade.saved');

        // Sobre una actividad ya publicada la nota queda visible: se reevalúa el riesgo académico de la sección.
        if ($item->is_published) {
            EvaluarRiesgoAcademico::dispatch((int) $item->section_id);
        }
    }

    /**
     * Publica las notas de una actividad: el estudiante las ve en su boleta.
     */
    public function publicar(User $docente, int $itemId): int
    {
        $item = DB::table('grade_items')->where('id', $itemId)->first() ?? abort(404);
        abort_unless($this->alcance->puedeEnSeccion($docente, 'calificaciones.gestionar', (int) $item->section_id), 403);
        $this->exigirActaAbierta((int) $item->section_id);

        $publicadas = DbContext::run(function () use ($itemId) {
            DB::table('grade_items')->where('id', $itemId)->update(['is_published' => true, 'updated_at' => now()]);

            return DB::table('grades')->where('grade_item_id', $itemId)->where('status', 'draft')->update(['status' => 'published', 'published_at' => now(), 'updated_at' => now()]);
        }, 'grades.published');

        NotasPublicadas::dispatch($itemId);

        return $publicadas;
    }

    /**
     * Corrección de una nota con el período cerrado (RF17, Flujo Cálculo de nota final): solo el Coordinador de la
     * carrera (`calificaciones.corregir`, alcance C), con motivo obligatorio y auditada. Si la matrícula ya tiene su
     * resultado congelado (`enrollment_results`), lo recalcula con la política guardada. Avisa al estudiante si la
     * nota está publicada y reevalúa el riesgo académico de la sección.
     *
     * Con el período abierto la nota la cambia el docente desde la actividad: la corrección se rechaza como regla de
     * negocio (ValidationException), igual que calificar() con el período cerrado.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function corregir(User $coordinador, int $notaId, float $nueva, string $motivo): void
    {
        $nota = DB::table('grades')->where('id', $notaId)->first() ?? abort(404);
        if ($this->alcance->de($coordinador, 'calificaciones.corregir') !== 'C'
            || ! $this->alcance->puedeEnSeccion($coordinador, 'calificaciones.corregir', (int) $nota->section_id)) {
            throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        }
        if ($nueva < 0 || $nueva > 10) {
            throw ValidationException::withMessages(['nota' => __('La nota debe estar entre 0 y 10.')]);
        }
        if (mb_strlen(trim($motivo)) < 5) {
            throw ValidationException::withMessages(['motivo' => __('Escriba el motivo de la corrección (al menos 5 caracteres).')]);
        }
        if (! $this->periodoCerrado((int) $nota->section_id)) {
            throw ValidationException::withMessages(['nota' => __('El período está abierto: la nota la cambia el docente de la sección.')]);
        }
        $anterior = (float) $nota->score;
        $motivo = trim($motivo);

        DbContext::run(function () use ($nota, $nueva): void {
            DB::table('grades')->where('id', $nota->id)->update(['score' => $nueva, 'updated_at' => now()]);
            $this->recalcularResultado((int) $nota->enrollment_id, (int) $nota->section_id);
        }, 'grade.corrected', $motivo, allowClosedPeriod: true);

        if ($nota->status === 'published') {
            NotaCorregida::dispatch($notaId, $anterior, $nueva, $motivo);
        }
        EvaluarRiesgoAcademico::dispatch((int) $nota->section_id);
    }

    /**
     * Acta de la sección (RF34): nota final de cada estudiante y estado del acta.
     *
     * `faltantes` cuenta los pares (matrícula activa × actividad de la sección) sin nota: contarán como 0 al publicar.
     *
     * @return array{acta: object|null, filas: Collection<int, object>, minima: float, faltantes: int}
     */
    public function acta(User $usuario, int $seccionId): array
    {
        $libro = $this->libro($usuario, $seccionId);
        $minima = self::notaMinima();

        return [
            'acta' => DB::table('grade_sheets')->where('section_id', $seccionId)->first(),
            'filas' => $libro['filas']->each(function ($f) use ($minima): void {
                $f->aprobado = $f->final >= $minima;
            }),
            'minima' => $minima,
            'faltantes' => $this->notasFaltantes($seccionId),
        ];
    }

    /**
     * ¿El acta de la sección está publicada o validada? Mientras lo esté, el docente no cambia ni publica notas:
     * debe pedir al Coordinador que la devuelva (RF34).
     */
    public function actaBloqueada(int $seccionId): bool
    {
        return DB::table('grade_sheets')->where('section_id', $seccionId)->whereIn('status', ['published', 'validated'])->exists();
    }

    public function publicarActa(User $docente, int $seccionId): void
    {
        abort_unless($this->alcance->puedeEnSeccion($docente, 'actas.publicar', $seccionId), 403);
        $acta = DB::table('grade_sheets')->where('section_id', $seccionId)->first();
        if ($acta && in_array($acta->status, ['published', 'validated'], true)) {
            throw ValidationException::withMessages(['acta' => __('El acta ya fue publicada.')]);
        }

        // Los pesos se verifican dentro de la transacción, con la sección bloqueada como en ComputoService::guardarPesos():
        // una edición de pesos simultánea no deja publicar un acta con pesos que no suman 100 % (sin efecto en SQLite).
        DbContext::run(function () use ($docente, $seccionId, $acta): void {
            DB::table('sections')->where('id', $seccionId)->lockForUpdate()->value('id');
            if ($problemas = $this->computos->problemas($seccionId)) {
                throw ValidationException::withMessages(['acta' => __('Corrija los pesos antes de publicar el acta. :problemas', ['problemas' => implode(' ', $problemas)])]);
            }
            DB::table('grade_sheets')->updateOrInsert(['section_id' => $seccionId], [
                'status' => 'published', 'published_by' => $docente->id, 'published_at' => now(), 'return_reason' => null, 'validated_by' => null, 'validated_at' => null, 'updated_at' => now(), 'created_at' => $acta->created_at ?? now(),
            ]);
        }, 'grade_sheet.published');

        ActaPublicada::dispatch($seccionId);
    }

    /**
     * Actas publicadas de la carrera, pendientes de validar (Coordinador).
     *
     * @return Collection<int, object>
     */
    public function actasPorValidar(User $coordinador, string $estado = 'published'): Collection
    {
        $consulta = DB::table('grade_sheets as g')->join('sections', 'sections.id', '=', 'g.section_id')->join('subjects', 'subjects.id', '=', 'sections.subject_id')
            ->leftJoin('users as u', 'u.id', '=', 'g.published_by')
            ->where('g.status', $estado)->orderBy('g.published_at')
            ->select('g.*', 'sections.ulid', 'sections.code as grupo', 'subjects.code', 'subjects.name', DB::raw("u.first_name || ' ' || u.last_name as docente"),
                DB::raw("(select count(*) from enrollments e where e.section_id = sections.id and e.status = 'active') as estudiantes"));

        return $this->alcance->secciones($consulta, $coordinador, 'actas.validar')->get();
    }

    public function validarActa(User $coordinador, int $seccionId): void
    {
        $this->resolverActa($coordinador, $seccionId, ['status' => 'validated', 'validated_by' => $coordinador->id, 'validated_at' => now()], 'grade_sheet.validated');
    }

    public function devolverActa(User $coordinador, int $seccionId, string $motivo): void
    {
        $this->resolverActa($coordinador, $seccionId, ['status' => 'returned', 'return_reason' => $motivo, 'validated_by' => null, 'validated_at' => null], 'grade_sheet.returned', $motivo, ['published', 'validated']);

        ActaDevuelta::dispatch($seccionId, $motivo);
    }

    /**
     * Estado de las actas del departamento del Jefe para el período en curso (RF34).
     *
     * `pendientes` lista las secciones sin acta validada como «CÓDIGO-grupo».
     *
     * @return array{carreras: Collection<string, Collection<int, object>>, total: int, validadas: int, pendientes: Collection<int, string>, autorizado: object|null, periodo: object|null}
     */
    public function estadoCierre(User $jefe): array
    {
        $this->alcance->exigir($jefe, 'cierre.autorizar');
        $periodo = DB::table('academic_periods')->where('status', 'active')->first(['id', 'name']);
        $departamentos = $this->alcance->departamentos($jefe);

        $secciones = DB::table('sections')->join('subjects', 'subjects.id', '=', 'sections.subject_id')
            ->leftJoin('grade_sheets as g', 'g.section_id', '=', 'sections.id')
            ->leftJoin('career_subjects', 'career_subjects.subject_id', '=', 'subjects.id')
            ->leftJoin('study_plans', 'study_plans.id', '=', 'career_subjects.study_plan_id')
            ->leftJoin('careers', 'careers.id', '=', 'study_plans.career_id')
            ->where('sections.period_id', $periodo?->id)->whereIn('subjects.department_id', $departamentos)
            ->select('sections.id', 'sections.code as grupo', 'subjects.code', 'subjects.name', DB::raw("coalesce(g.status, 'draft') as estado"), DB::raw("coalesce(min(careers.code), '—') as carrera"))
            ->groupBy('sections.id', 'sections.code', 'subjects.code', 'subjects.name', 'g.status')
            ->orderBy('subjects.code')->get();

        return [
            'carreras' => $secciones->groupBy('carrera'),
            'total' => $secciones->count(),
            'validadas' => $secciones->where('estado', 'validated')->count(),
            'pendientes' => $secciones->where('estado', '!=', 'validated')->map(fn ($s) => $s->code.'-'.$s->grupo)->values(),
            'autorizado' => $periodo ? DB::table('department_closures')->where('period_id', $periodo->id)->whereIn('department_id', $departamentos)->first() : null,
            'periodo' => $periodo,
        ];
    }

    public function autorizarCierre(User $jefe): void
    {
        $estado = $this->estadoCierre($jefe);
        if ($estado['periodo'] === null || $estado['autorizado']) {
            throw ValidationException::withMessages(['cierre' => __('No hay un cierre pendiente de autorizar.')]);
        }
        if ($estado['total'] === 0) {
            throw ValidationException::withMessages(['cierre' => __('Su departamento no tiene secciones en este período.')]);
        }
        if ($estado['validadas'] < $estado['total']) {
            throw ValidationException::withMessages(['cierre' => __('Faltan actas por validar (:n de :t): :secciones.', [
                'n' => $estado['total'] - $estado['validadas'], 't' => $estado['total'], 'secciones' => $estado['pendientes']->implode(', '),
            ])]);
        }

        DbContext::run(function () use ($jefe, $estado) {
            foreach ($this->alcance->departamentos($jefe) as $departamento) {
                DB::table('department_closures')->insert(['period_id' => $estado['periodo']->id, 'department_id' => $departamento, 'authorized_by' => $jefe->id,
                    'authorized_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            }
        }, 'department_closure.authorized');
    }

    /**
     * Congela la nota final de cada matrícula activa del período en `enrollment_results` (RF17, RF34).
     * Cuenta como 0 la nota faltante y guarda en `policy_snapshot` los parámetros aplicados. No abre su propio
     * contexto: corre bajo el `DbContext::run()` de quien la llama. Es idempotente (una fila por matrícula).
     *
     * @return int Cantidad de resultados guardados.
     */
    public function congelarResultados(int $periodoId): int
    {
        $minima = self::notaMinima();
        $redondeo = json_decode((string) DB::table('system_settings')->where('key', 'grade_rounding')->value('value'), true) ?? ['decimals' => 1, 'mode' => 'half_up'];
        $faltante = json_decode((string) DB::table('system_settings')->where('key', 'missing_grade_policy')->value('value'), true) ?? 'zero';
        $confirmada = (bool) DB::table('system_settings')->where('key', 'passing_score')->value('is_confirmed');
        $matriculas = DB::table('enrollments')->where('period_id', $periodoId)->where('status', 'active')->orderBy('id')->get(['id', 'section_id'])->groupBy('section_id');
        $guardados = 0;

        foreach ($matriculas as $seccionId => $deLaSeccion) {
            $computos = $this->estructura((int) $seccionId);
            $base = [
                'passing_score' => $minima,
                'grade_rounding' => $redondeo,
                'missing_grade_policy' => $faltante,
                'confirmed' => $confirmada,
                'components' => $computos->map(fn ($c) => ['number' => (int) $c->number, 'name' => $c->name, 'weight_percent' => (float) $c->weight_percent])->values()->all(),
            ];
            $politica = json_encode($base, JSON_UNESCAPED_UNICODE);
            $notas = DB::table('grades')->whereIn('enrollment_id', $deLaSeccion->pluck('id'))->get(['enrollment_id', 'grade_item_id', 'score'])->groupBy('enrollment_id');
            $cadenas = $this->cadenasDeSeccion($deLaSeccion->pluck('id'));

            foreach ($deLaSeccion as $matricula) {
                $deLaMatricula = ($notas[$matricula->id] ?? collect())->keyBy('grade_item_id');
                $heredadas = $this->heredadasEfectivas($computos, $deLaMatricula, $cadenas->get((int) $matricula->id) ?? collect());
                $final = $this->notaFinal($computos, $deLaMatricula, soloPublicadas: false, cadena: $heredadas);

                DB::table('enrollment_results')->updateOrInsert(['enrollment_id' => $matricula->id], [
                    'final_score' => $final,
                    'result' => $final >= $minima ? 'passed' : 'failed',
                    'policy_snapshot' => $heredadas->isEmpty() ? $politica : self::politicaConHerencia($base, $heredadas),
                    'frozen_at' => now(),
                ]);
                $guardados++;
            }
        }

        return $guardados;
    }

    /**
     * El `policy_snapshot` de una matrícula que heredó cómputos de un grupo anterior ([[ADR-062]]): añade
     * `inherited_from` para que el resultado congelado diga de dónde salió cada cómputo heredado. Sin
     * traslado el snapshot no lleva la clave, así que los resultados de siempre no cambian de formato.
     *
     * @param  array<string, mixed>  $base
     * @param  Collection<int, object>  $heredadas
     */
    private static function politicaConHerencia(array $base, Collection $heredadas): string
    {
        $heredadoDe = [];
        foreach ($heredadas as $number => $heredada) {
            $heredadoDe[] = [
                'component_number' => (int) $number,
                'group' => $heredada->grupo,
                'section_id' => $heredada->seccion_id,
                'enrollment_id' => $heredada->matricula_id,
                'score' => self::redondear((float) $heredada->nota),
            ];
        }
        $base['inherited_from'] = $heredadoDe;

        return (string) json_encode($base, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Recalcula el resultado congelado de una matrícula tras una corrección (RF17), con la misma fórmula de
     * congelarResultados(). Del `policy_snapshot` solo toma `passing_score` (o notaMinima() si falta); los pesos son
     * los actuales de la sección, que no cambian desde que hay notas publicadas (ComputoService::bloqueo()). No toca
     * la política ni la fecha de congelado. Si la matrícula aún no tiene resultado, no hace nada. No abre su propio
     * contexto: corre bajo el DbContext::run() de corregir().
     */
    private function recalcularResultado(int $matriculaId, int $seccionId): void
    {
        $resultado = DB::table('enrollment_results')->where('enrollment_id', $matriculaId)->first(['policy_snapshot']);
        if ($resultado === null) {
            return;
        }
        $politica = json_decode((string) $resultado->policy_snapshot, true);
        $minima = (float) ($politica['passing_score'] ?? self::notaMinima());
        $notas = DB::table('grades')->where('enrollment_id', $matriculaId)->get(['grade_item_id', 'score'])->keyBy('grade_item_id');
        $computos = $this->estructura($seccionId);
        $final = $this->notaFinal($computos, $notas, soloPublicadas: false,
            cadena: $this->heredadasEfectivas($computos, $notas, $this->notasDeCadena($matriculaId)));

        DB::table('enrollment_results')->where('enrollment_id', $matriculaId)->update([
            'final_score' => $final,
            'result' => $final >= $minima ? 'passed' : 'failed',
        ]);
    }

    /**
     * @template TNota of object
     *
     * @param  Collection<int, object>  $computos
     * @param  Collection<int|string, TNota>  $notas  por grade_item_id
     */
    public function notaFinal(Collection $computos, Collection $notas, bool $soloPublicadas, ?Collection $cadena = null): float
    {
        $pesos = $computos->sum(fn ($c) => (float) $c->weight_percent) ?: 100;

        return self::redondear($computos->sum(fn ($c) => (float) $c->weight_percent * $this->notaComputo($c, $notas, $soloPublicadas, $cadena)) / $pesos);
    }

    /**
     * @template TNota of object
     *
     * @param  Collection<int|string, TNota>  $notas
     * @param  Collection<int, object>|null  $cadena  heredadas por number, de notasDeCadena()
     */
    public function notaComputo(object $computo, Collection $notas, bool $soloPublicadas, ?Collection $cadena = null): float
    {
        // Cómputo heredado de un cambio de grupo ([[ADR-062]]): solo si en este grupo no hay ninguna nota suya.
        $heredada = $cadena?->get((int) $computo->number);
        if ($heredada !== null && ! self::tieneNotasDelComputo($computo, $notas)) {
            return (float) $heredada->nota;
        }

        $items = collect($computo->items)->when($soloPublicadas, fn ($c) => $c->filter(fn ($i) => (bool) $i->is_published));
        $pesos = $items->sum(fn ($i) => (float) $i->weight_percent);

        return $pesos > 0 ? $items->sum(fn ($i) => (float) $i->weight_percent * (float) ($notas[$i->id]->score ?? 0)) / $pesos : 0.0;
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @param  array<int, string>  $estadosPermitidos  estados desde los que se puede resolver el acta
     */
    private function resolverActa(User $coordinador, int $seccionId, array $cambios, string $evento, ?string $motivo = null, array $estadosPermitidos = ['published']): void
    {
        abort_unless($this->alcance->puedeEnSeccion($coordinador, 'actas.validar', $seccionId), 403);
        $estado = DB::table('grade_sheets')->where('section_id', $seccionId)->value('status');
        if (! in_array($estado, $estadosPermitidos, true)) {
            throw ValidationException::withMessages(['acta' => __('El acta no está publicada.')]);
        }
        if ($estado === 'validated' && $this->periodoCerrado($seccionId)) {
            throw ValidationException::withMessages(['acta' => __('El período está cerrado: use la corrección del Coordinador.')]);
        }
        DbContext::run(fn () => DB::table('grade_sheets')->where('section_id', $seccionId)->update($cambios + ['updated_at' => now()]), $evento, $motivo);
    }

    /**
     * Rechaza el cambio de notas del docente si el acta de la sección está publicada o validada.
     *
     * @throws ValidationException
     */
    public function exigirActaAbierta(int $seccionId): void
    {
        if ($this->actaBloqueada($seccionId)) {
            throw ValidationException::withMessages(['acta' => __('El acta está publicada: pida al Coordinador que la devuelva para cambiar notas.')]);
        }
    }

    private function notasFaltantes(int $seccionId): int
    {
        $matriculas = DB::table('enrollments')->where('section_id', $seccionId)->where('status', 'active')->count();
        $actividades = DB::table('grade_items')->where('section_id', $seccionId)->count();
        $conNota = DB::table('grades')->where('section_id', $seccionId)->whereNotNull('score')
            ->whereIn('enrollment_id', DB::table('enrollments')->where('section_id', $seccionId)->where('status', 'active')->select('id'))->count();

        return max(0, $matriculas * $actividades - $conNota);
    }

    public function periodoCerrado(int $seccionId): bool
    {
        return DB::table('sections')->join('academic_periods', 'academic_periods.id', '=', 'sections.period_id')->where('sections.id', $seccionId)->value('academic_periods.status') === 'closed';
    }
}
