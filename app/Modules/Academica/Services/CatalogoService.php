<?php

namespace App\Modules\Academica\Services;

use App\Models\User;
use App\Support\Alcance;
use App\Support\Busqueda;
use App\Support\DbContext;
use App\Support\DbError;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Catálogo (RF07, ADR-034): asignaturas con prerrequisitos, y la estructura institucional
 * (departamentos, carreras, planes y aulas). Lo mantiene el Administrador; el Jefe mantiene las asignaturas
 * de su departamento (asignaturas.gestionar D, ADR-072), pero no la estructura.
 * Los prerrequisitos se exigen al matricular (ADR-052, en MatriculaService).
 */
class CatalogoService
{
    public function __construct(private Alcance $alcance) {}

    /**
     * Filtra por nombre o código, por carrera (las asignaturas de alguno de sus planes de estudio) y por créditos.
     * Con alcance D solo salen las del departamento del Jefe, aunque se pida otra carrera.
     *
     * @param  array{buscar?: string, carrera?: int|string|null, creditos?: int|string|null}  $filtros
     */
    public function asignaturas(User $actor, array $filtros): LengthAwarePaginator
    {
        $alcance = $this->alcance->exigir($actor, 'asignaturas.gestionar');
        $buscar = trim($filtros['buscar'] ?? '');
        $patron = Busqueda::fragmentoSinTildes($buscar);

        return DB::table('subjects')->join('departments', 'departments.id', '=', 'subjects.department_id')
            ->select('subjects.*', 'departments.code as departamento',
                DB::raw("(select count(*) from sections s join academic_periods p on p.id = s.period_id where s.subject_id = subjects.id and p.status = 'active') as secciones"))
            ->when($buscar !== '', fn ($q) => $q->where(fn ($q) => $q->whereRaw("lower(subjects.code) like ? escape '\\'", [$patron])
                ->orWhereRaw(Busqueda::sinTildes('subjects.name')." like ? escape '\\'", [$patron])))
            ->when($alcance !== 'G', fn ($q) => $q->whereIn('subjects.department_id', $this->alcance->departamentos($actor)))
            ->when($filtros['carrera'] ?? null, fn ($q, $c) => $q->whereIn('subjects.id', DB::table('career_subjects')
                ->join('study_plans', 'study_plans.id', '=', 'career_subjects.study_plan_id')->where('study_plans.career_id', (int) $c)->select('career_subjects.subject_id')))
            ->when($filtros['creditos'] ?? null, fn ($q, $c) => $q->where('subjects.credits', (int) $c))
            ->orderBy('subjects.code')
            ->paginate(25);
    }

    /**
     * Prerrequisitos por asignatura (código => [códigos]).
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<int, string>>
     */
    public function prerrequisitos(array $ids): array
    {
        return DB::table('subject_prerequisites')->join('subjects', 'subjects.id', '=', 'subject_prerequisites.prerequisite_id')
            ->whereIn('subject_prerequisites.subject_id', $ids)->get(['subject_prerequisites.subject_id', 'subjects.code'])
            ->groupBy('subject_id')->map(fn ($g) => $g->pluck('code')->all())->all();
    }

    /**
     * Carreras donde se lleva cada asignatura, con sus planes de estudio («ISI · Plan 98, Plan 25»), por código. El Jefe
     * (alcance D) solo ve las carreras de su departamento; el Administrador, todas.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<int, string>>
     */
    public function carreras(User $actor, array $ids): array
    {
        $alcance = $this->alcance->exigir($actor, 'asignaturas.gestionar');

        return DB::table('career_subjects')->join('study_plans', 'study_plans.id', '=', 'career_subjects.study_plan_id')
            ->join('careers', 'careers.id', '=', 'study_plans.career_id')
            ->whereIn('career_subjects.subject_id', $ids)
            ->when($alcance !== 'G', fn ($q) => $q->whereIn('careers.department_id', $this->alcance->departamentos($actor)))
            ->orderBy('careers.code')->orderBy('study_plans.id')->get(['career_subjects.subject_id', 'careers.code', 'study_plans.name as plan'])
            // «ISI · Plan 98, Plan 25»: la carrera con los pensum donde se lleva.
            ->groupBy('subject_id')->map(fn ($g) => $g->groupBy('code')->map(fn ($planes, $carrera) => $carrera.' · '.$planes->pluck('plan')->unique()->implode(', '))->values()->all())->all();
    }

    /**
     * ¿Ve la columna del departamento? Solo con alcance global: el Jefe ya sabe que todas son de su departamento.
     */
    public function veDepartamento(User $actor): bool
    {
        return $this->alcance->exigir($actor, 'asignaturas.gestionar') === 'G';
    }

    /**
     * Crea o edita una asignatura con sus prerrequisitos y sus planes de estudio (`career_subjects`: plan y ciclo).
     * Al crearla se exige al menos un plan, para que no quede fuera de toda carrera. Con alcance D (el Jefe), los planes
     * deben ser de carreras de su departamento; los de otros departamentos que ya tenga se conservan sin tocarlos.
     * Si `planes` no se envía al editar, los planes no cambian.
     *
     * @param  array{codigo: string, nombre: string, creditos: int, departamento: int, prerrequisitos?: array<int, int>, planes?: array<int, array{plan?: int|string|null, ciclo?: int|string|null}>}  $datos
     */
    public function guardarAsignatura(User $actor, ?int $id, array $datos): int
    {
        $this->exigirAsignatura($actor, $id, (int) $datos['departamento']);
        $codigo = mb_strtoupper(trim($datos['codigo']));

        if (DB::table('subjects')->whereRaw('upper(code) = ?', [$codigo])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
            throw ValidationException::withMessages(['codigo' => __('Ya existe una asignatura con ese código.')]);
        }
        $prerrequisitos = array_values(array_unique(array_map('intval', $datos['prerrequisitos'] ?? [])));
        if ($id !== null && $this->creaCiclo($id, $prerrequisitos)) {
            throw ValidationException::withMessages(['prerrequisitos' => __('Ese prerrequisito crearía un ciclo (A requiere B y B requiere A).')]);
        }
        $planes = array_key_exists('planes', $datos) || $id === null ? $this->planesValidados($actor, $datos['planes'] ?? [], $id === null) : null;
        $this->exigirPrerrequisitosDelPlan($id, $prerrequisitos, $planes);

        // QA sesión 4 (H-08 parcial): cada fila se escribe solo si cambia, para que la auditoría no se duplique. Antes
        // el alta hacía insert y luego update, y cada guardado borraba y reinsertaba todos los prerrequisitos.
        return DbContext::run(function () use ($actor, $id, $codigo, $datos, $prerrequisitos, $planes) {
            $fila = ['code' => $codigo, 'name' => trim($datos['nombre']), 'credits' => (int) $datos['creditos'], 'department_id' => (int) $datos['departamento']];
            if ($id === null) {
                $id = DB::table('subjects')->insertGetId($fila + ['created_at' => now(), 'updated_at' => now()]);
            } else {
                $actual = (array) (DB::table('subjects')->where('id', $id)->first(array_keys($fila)) ?? abort(404));
                if ($actual != $fila) {
                    DB::table('subjects')->where('id', $id)->update($fila + ['updated_at' => now()]);
                }
            }

            $actuales = DB::table('subject_prerequisites')->where('subject_id', $id)->pluck('prerequisite_id')->map(fn ($pre) => (int) $pre)->all();
            $nuevos = array_values(array_diff($prerrequisitos, [$id]));
            if ($quitar = array_diff($actuales, $nuevos)) {
                DB::table('subject_prerequisites')->where('subject_id', $id)->whereIn('prerequisite_id', $quitar)->delete();
            }
            foreach (array_diff($nuevos, $actuales) as $pre) {
                DB::table('subject_prerequisites')->insert(['subject_id' => $id, 'prerequisite_id' => $pre]);
            }

            if ($planes !== null) {
                $this->sincronizarPlanes($actor, $id, $planes);
            }

            return $id;
        }, 'subject.saved');
    }

    /**
     * @return array{departamentos: Collection<int, object>, carreras: Collection<int, object>, aulas: Collection<int, object>}
     */
    public function estructura(User $actor): array
    {
        $this->exigirGlobal($actor);

        return [
            'departamentos' => DB::table('departments as d')->select('d.*', DB::raw('(select count(*) from subjects s where s.department_id = d.id) as asignaturas'))->orderBy('code')->get(),
            'carreras' => DB::table('careers as c')->join('departments as d', 'd.id', '=', 'c.department_id')
                ->select('c.*', 'd.code as departamento', DB::raw('(select count(*) from student_careers sc where sc.career_id = c.id and sc.status = \'active\') as estudiantes'))
                ->orderBy('c.code')->get()
                ->each(fn ($c) => $c->planes = DB::table('study_plans')->where('career_id', $c->id)->orderBy('name')->pluck('name')->all()),
            'aulas' => DB::table('classrooms')->orderBy('code')->get(),
        ];
    }

    /**
     * Elimina una asignatura que nada usa (RF07). Si tiene secciones en algún período, está en un plan de estudios,
     * es prerrequisito de otra o tiene claves o bancos de preguntas, se rechaza: solo se puede desactivar.
     * Sus propios prerrequisitos se borran con ella.
     *
     * @throws ValidationException
     */
    public function eliminarAsignatura(User $actor, int $id): void
    {
        $this->exigirAsignatura($actor, $id);

        $usos = array_keys(array_filter([
            __('secciones') => DB::table('sections')->where('subject_id', $id)->exists(),
            __('planes de estudio') => DB::table('career_subjects')->where('subject_id', $id)->exists(),
            __('prerrequisito de otra asignatura') => DB::table('subject_prerequisites')->where('prerequisite_id', $id)->exists(),
            __('claves de matrícula') => DB::table('enrollment_keys')->where('subject_id', $id)->exists(),
            __('bancos de preguntas') => DB::table('question_banks')->where('subject_id', $id)->exists(),
        ]));
        if ($usos !== []) {
            throw ValidationException::withMessages(['asignatura' => __('No se puede eliminar: tiene :usos. Desactívela para que no se programe en nuevos períodos.', ['usos' => implode(', ', $usos)])]);
        }

        DbContext::run(function () use ($id): void {
            DB::table('subject_prerequisites')->where('subject_id', $id)->delete();
            DB::table('subjects')->where('id', $id)->delete();
        }, 'subject.deleted');
    }

    /**
     * Desactiva o reactiva una asignatura (RF07). Inactiva, no se ofrece al crear secciones; las que ya tiene siguen igual.
     *
     * Al desactivarla se desactivan también, en la misma transacción, las asignaturas activas que dependen de ella por
     * prerrequisito, directa o indirectamente (CP-RF07-23). Si alguna es de un departamento fuera del alcance del actor
     * (el Jefe), no se desactiva nada. Al reactivarla solo cambia ella: las dependientes se reactivan a mano.
     *
     * @return array<int, string> códigos de las dependientes que se desactivaron en cascada
     *
     * @throws ValidationException si una dependiente activa es de otro departamento
     */
    public function cambiarEstadoAsignatura(User $actor, int $id, bool $activa): array
    {
        $this->exigirAsignatura($actor, $id);
        $dependientes = $activa ? collect() : $this->dependientesActivas([$id])[$id];

        if ($dependientes->isNotEmpty() && $this->alcance->exigir($actor, 'asignaturas.gestionar') !== 'G') {
            $ajenas = $dependientes->reject(fn (object $d) => in_array((int) $d->department_id, $this->alcance->departamentos($actor), true));
            if ($ajenas->isNotEmpty()) {
                throw ValidationException::withMessages(['asignatura' => __('No se puede desactivar: :codigos depende de ella y es de otro departamento. Pida a su jefatura que la desactive primero.', ['codigos' => $ajenas->pluck('code')->implode(', ')])]);
            }
        }

        DbContext::run(fn () => DB::table('subjects')->whereIn('id', [$id, ...$dependientes->pluck('id')->all()])
            ->update(['is_active' => $activa, 'updated_at' => now()]), $activa ? 'subject.activated' : 'subject.deactivated');

        return $dependientes->pluck('code')->all();
    }

    /**
     * Asignaturas activas que dependen de cada una de las dadas por prerrequisito, directa o indirectamente (la cadena
     * entera), ordenadas por código. Se recorre el grafo en PHP con una sola lectura de los prerrequisitos.
     *
     * @param  array<int, int>  $ids
     * @return array<int, Collection<int, \stdClass>> id => [{id, code, department_id}]
     */
    public function dependientesActivas(array $ids): array
    {
        $dependenDe = DB::table('subject_prerequisites')->get(['subject_id', 'prerequisite_id'])
            ->groupBy(fn (object $p) => (int) $p->prerequisite_id)
            ->map(fn (Collection $g) => $g->map(fn (object $p) => (int) $p->subject_id)->all());
        $activas = DB::table('subjects')->where('is_active', true)->get(['id', 'code', 'department_id'])->keyBy(fn (object $a) => (int) $a->id);

        $resultado = [];
        foreach ($ids as $id) {
            $vistos = [];
            $pendientes = $dependenDe[(int) $id] ?? [];
            while ($pendientes !== []) {
                $actual = array_pop($pendientes);
                if ($actual === (int) $id || isset($vistos[$actual])) {
                    continue;
                }
                $vistos[$actual] = true;
                array_push($pendientes, ...($dependenDe[$actual] ?? []));
            }
            $resultado[(int) $id] = collect(array_keys($vistos))->map(fn (int $d) => $activas[$d] ?? null)->filter()->sortBy('code')->values();
        }

        return $resultado;
    }

    /**
     * Descripción de la materia que ve el estudiante al inscribirse (RF29, ADR-050), en texto plano.
     * La edita el Administrador (asignaturas.gestionar, G), el Jefe en su departamento (D, ADR-072) o el docente (contenidos.gestionar, S) que tiene un grupo
     * vigente de la asignatura en el período activo. Si varios docentes la dictan, la comparten.
     *
     * @throws AuthorizationException
     */
    public function editarDescripcion(User $actor, int $asignaturaId, ?string $descripcion): void
    {
        $esAdministrador = $this->puedeGestionarAsignatura($actor, $asignaturaId);
        $esSuDocente = $this->alcance->de($actor, 'contenidos.gestionar') !== null
            && DB::table('teaching_assignments')->join('sections', 'sections.id', '=', 'teaching_assignments.section_id')
                ->where('teaching_assignments.teacher_id', $actor->id)->whereNull('teaching_assignments.ended_at')
                ->where('sections.subject_id', $asignaturaId)->where('sections.period_id', PeriodoService::activoId())->exists();
        if (! $esAdministrador && ! $esSuDocente) {
            throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        }
        DB::table('subjects')->where('id', $asignaturaId)->exists() || abort(404);

        $descripcion = trim((string) $descripcion);
        if (mb_strlen($descripcion) > 2000) {
            throw ValidationException::withMessages(['descripcion' => __('La descripción admite como máximo 2000 caracteres.')]);
        }

        DbContext::run(fn () => DB::table('subjects')->where('id', $asignaturaId)
            ->update(['description' => $descripcion === '' ? null : $descripcion, 'updated_at' => now()]), 'subject.description_updated');
    }

    public function guardarAula(User $actor, string $codigo, int $capacidad): void
    {
        $this->exigirGlobal($actor);
        $codigo = mb_strtoupper(trim($codigo));
        if (DB::table('classrooms')->whereRaw('upper(code) = ?', [$codigo])->exists()) {
            throw ValidationException::withMessages(['aulaCodigo' => __('Ya existe un aula con ese código.')]);
        }
        DbContext::run(fn () => DB::table('classrooms')->insert(['code' => $codigo, 'capacity' => $capacidad, 'created_at' => now(), 'updated_at' => now()]), 'classroom.created');
    }

    /**
     * Crea un departamento o edita su código y nombre (RF07). El código es único sin distinguir mayúsculas
     * y se guarda en mayúsculas. Guardar sin cambios no escribe, para no dejar en la auditoría una edición vacía.
     *
     * @return int El id del departamento.
     *
     * @throws ValidationException
     */
    public function guardarDepartamento(User $actor, ?int $id, string $codigo, string $nombre): int
    {
        $this->exigirGlobal($actor);
        $actual = $id === null ? null : (DB::table('departments')->where('id', $id)->first() ?? abort(404));
        $codigo = mb_strtoupper(trim($codigo));
        $nombre = trim($nombre);

        $repetido = DB::table('departments')->whereRaw('upper(code) = ?', [$codigo])
            ->when($id !== null, fn ($q) => $q->where('id', '!=', $id))->exists();
        if ($repetido) {
            throw ValidationException::withMessages(['departamentoCodigo' => __('Ya existe un departamento con ese código.')]);
        }

        if ($actual === null) {
            return DbContext::run(fn () => DB::table('departments')->insertGetId([
                'code' => $codigo, 'name' => $nombre, 'created_at' => now(), 'updated_at' => now(),
            ]), 'department.created');
        }

        if ($actual->code !== $codigo || $actual->name !== $nombre) {
            DbContext::run(fn () => DB::table('departments')->where('id', $id)
                ->update(['code' => $codigo, 'name' => $nombre, 'updated_at' => now()]), 'department.updated');
        }

        return (int) $id;
    }

    /**
     * Asignaturas de esos planes de estudios, con el menor ciclo en que se llevan: las opciones de prerrequisito de una
     * asignatura son las de su propio pensum, no las de cualquier carrera.
     *
     * @param  array<int, int>  $planes  ids de study_plans
     * @return Collection<int, \stdClass> con id, code, name y ciclo
     */
    public function asignaturasDePlanes(array $planes): Collection
    {
        if ($planes === []) {
            return collect();
        }

        return DB::table('career_subjects')->join('subjects', 'subjects.id', '=', 'career_subjects.subject_id')
            ->whereIn('career_subjects.study_plan_id', $planes)
            ->groupBy('subjects.id', 'subjects.code', 'subjects.name')
            ->orderByRaw('min(career_subjects.cycle)')->orderBy('subjects.code')
            ->get(['subjects.id', 'subjects.code', 'subjects.name', DB::raw('min(career_subjects.cycle) as ciclo')])
            ->each(function (object $a): void {
                $a->id = (int) $a->id;
                $a->ciclo = (int) $a->ciclo;
            });
    }

    /**
     * Un prerrequisito nuevo debe llevarse en alguno de los planes de la asignatura (los que se guardan y los que ya
     * tiene). Los que ya estaban se conservan aunque no cumplan, para no romper datos anteriores.
     *
     * @param  array<int, int>  $prerrequisitos
     * @param  array<int, int>|null  $planes  plan => ciclo, o null si no cambian
     *
     * @throws ValidationException
     */
    private function exigirPrerrequisitosDelPlan(?int $id, array $prerrequisitos, ?array $planes): void
    {
        $actuales = $id === null ? [] : DB::table('subject_prerequisites')->where('subject_id', $id)->pluck('prerequisite_id')->map(fn ($p) => (int) $p)->all();
        $nuevos = array_diff($prerrequisitos, $actuales, $id === null ? [] : [$id]);
        if ($nuevos === []) {
            return;
        }
        $planIds = array_keys($planes ?? []);
        if ($id !== null) {
            $planIds = array_merge($planIds, DB::table('career_subjects')->where('subject_id', $id)->pluck('study_plan_id')->map(fn ($p) => (int) $p)->all());
        }
        $fuera = array_diff($nuevos, $this->asignaturasDePlanes(array_values(array_unique($planIds)))->pluck('id')->all());
        if ($fuera !== []) {
            $codigos = DB::table('subjects')->whereIn('id', $fuera)->orderBy('code')->pluck('code')->implode(', ');
            throw ValidationException::withMessages(['prerrequisitos' => __('Los prerrequisitos deben ser materias del mismo plan de estudios. No están en él: :c.', ['c' => $codigos])]);
        }
    }

    /**
     * Crea una carrera en un departamento con su primer plan de estudios (RF07, ADR-083). Solo el Administrador. El
     * código es único sin distinguir mayúsculas y se guarda en mayúsculas; sin plan la carrera no tendría dónde ubicar
     * sus materias, así que se crean juntos, en la misma transacción.
     *
     * @return int El id de la carrera.
     *
     * @throws ValidationException
     */
    public function crearCarrera(User $actor, int $departamentoId, string $codigo, string $nombre, string $plan): int
    {
        $this->exigirGlobal($actor);
        DB::table('departments')->where('id', $departamentoId)->exists()
            || throw ValidationException::withMessages(['carreraDepartamento' => __('Elija un departamento válido.')]);
        $codigo = mb_strtoupper(trim($codigo));
        if (DB::table('careers')->whereRaw('upper(code) = ?', [$codigo])->exists()) {
            throw ValidationException::withMessages(['carreraCodigo' => __('Ya existe una carrera con ese código.')]);
        }

        return DbContext::run(function () use ($departamentoId, $codigo, $nombre, $plan): int {
            $id = DB::table('careers')->insertGetId([
                'department_id' => $departamentoId, 'code' => $codigo, 'name' => trim($nombre), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('study_plans')->insert(['career_id' => $id, 'name' => trim($plan), 'created_at' => now(), 'updated_at' => now()]);

            return $id;
        }, 'career.created');
    }

    /**
     * Agrega un plan de estudios (pensum) a una carrera, como «Plan 25» junto al «Plan 98» (RF07). Solo el
     * Administrador. El nombre no se repite dentro de la carrera.
     *
     * @throws ValidationException
     */
    public function agregarPlan(User $actor, int $carreraId, string $plan): void
    {
        $this->exigirGlobal($actor);
        DB::table('careers')->where('id', $carreraId)->exists() || abort(404);
        $plan = trim($plan);
        if (DB::table('study_plans')->where('career_id', $carreraId)->whereRaw('lower(name) = ?', [mb_strtolower($plan)])->exists()) {
            throw ValidationException::withMessages(['planNombre' => __('La carrera ya tiene un plan con ese nombre.')]);
        }

        DbContext::run(fn () => DB::table('study_plans')->insert(['career_id' => $carreraId, 'name' => $plan, 'created_at' => now(), 'updated_at' => now()]), 'study_plan.created');
    }

    /**
     * Elimina un departamento que nada usa (RF07). No hay desactivación: si tiene carreras, asignaturas, docentes,
     * jefaturas (vigentes o pasadas), cierres, plazos de matrícula o fechas del calendario, se rechaza nombrándolas.
     * La auditoría es inmutable (ADR-027) y su FK a `departments` es RESTRICT: si el departamento ya figura en ella,
     * tampoco se puede borrar.
     *
     * @throws ValidationException
     */
    public function eliminarDepartamento(User $actor, int $id): void
    {
        $this->exigirGlobal($actor);
        DB::table('departments')->where('id', $id)->exists() || abort(404);

        $usos = array_keys(array_filter([
            __('carreras') => DB::table('careers')->where('department_id', $id)->exists(),
            __('asignaturas') => DB::table('subjects')->where('department_id', $id)->exists(),
            __('docentes') => DB::table('teacher_profiles')->where('department_id', $id)->exists(),
            __('jefaturas') => DB::table('department_heads')->where('department_id', $id)->exists(),
            __('cierres de período') => DB::table('department_closures')->where('department_id', $id)->exists(),
            __('plazos de matrícula') => DB::table('enrollment_deadlines')->where('department_id', $id)->exists(),
            __('fechas del calendario') => DB::table('period_calendar_events')->where('department_id', $id)->exists(),
        ]));
        if ($usos !== []) {
            throw ValidationException::withMessages(['departamento' => __('No se puede eliminar el departamento: tiene :usos.', ['usos' => implode(', ', $usos)])]);
        }

        try {
            DbContext::run(fn () => DB::table('departments')->where('id', $id)->delete(), 'department.deleted');
        } catch (QueryException $e) {
            if ((string) ($e->errorInfo[0] ?? $e->getCode()) !== '23503') {
                throw DbError::toDomain($e);
            }
            throw ValidationException::withMessages(['departamento' => __('No se puede eliminar el departamento: tiene historial en la auditoría, que no se puede borrar.')]);
        }
    }

    /**
     * Carreras en que el actor puede incluir asignaturas, con sus planes: todas (G) o las de su departamento (D).
     *
     * @return Collection<int, \stdClass>
     */
    public function carrerasConPlanes(User $actor): Collection
    {
        $alcance = $this->alcance->exigir($actor, 'asignaturas.gestionar');
        $carreras = DB::table('careers')->when($alcance !== 'G', fn ($q) => $q->whereIn('department_id', $this->alcance->departamentos($actor)))
            ->orderBy('code')->get(['id', 'code', 'name', 'department_id']);
        $planes = DB::table('study_plans')->whereIn('career_id', $carreras->pluck('id'))->orderBy('name')->get(['id', 'career_id', 'name'])->groupBy('career_id');

        return $carreras->each(fn ($carrera) => $carrera->planes = ($planes[$carrera->id] ?? collect())->values()->all());
    }

    /**
     * Planes de la asignatura que el actor puede editar, como filas del formulario (carrera, plan y ciclo).
     *
     * @return array<int, array{carrera: int, plan: int, ciclo: int}>
     */
    public function planesDeAsignatura(User $actor, int $asignaturaId): array
    {
        return DB::table('career_subjects')->join('study_plans', 'study_plans.id', '=', 'career_subjects.study_plan_id')
            ->where('career_subjects.subject_id', $asignaturaId)
            ->whereIn('study_plans.career_id', $this->carrerasConPlanes($actor)->pluck('id'))
            ->orderBy('study_plans.career_id')->orderBy('study_plans.name')
            ->get(['study_plans.career_id', 'career_subjects.study_plan_id', 'career_subjects.cycle'])
            ->map(fn ($fila) => ['carrera' => (int) $fila->career_id, 'plan' => (int) $fila->study_plan_id, 'ciclo' => (int) $fila->cycle])->all();
    }

    /**
     * Valida los planes del formulario: existen, no se repiten, el ciclo va de 1 a 12 y, con alcance D, son de una
     * carrera de su departamento (si no, 403). Al crear, exige al menos uno.
     *
     * @param  array<int, array{plan?: int|string|null, ciclo?: int|string|null}>  $planes
     * @return array<int, int> plan => ciclo
     *
     * @throws ValidationException|AuthorizationException
     */
    private function planesValidados(User $actor, array $planes, bool $alCrear): array
    {
        $porPlan = [];
        foreach ($planes as $fila) {
            $plan = (int) ($fila['plan'] ?? 0);
            $ciclo = (int) ($fila['ciclo'] ?? 0);
            if ($plan === 0) {
                throw ValidationException::withMessages(['planes' => __('Elija la carrera y el plan de estudios de cada fila.')]);
            }
            if ($ciclo < 1 || $ciclo > 12) {
                throw ValidationException::withMessages(['planes' => __('El ciclo de cada plan va de 1 a 12.')]);
            }
            if (isset($porPlan[$plan])) {
                throw ValidationException::withMessages(['planes' => __('Un mismo plan de estudios está dos veces.')]);
            }
            $porPlan[$plan] = $ciclo;
        }
        if ($alCrear && $porPlan === []) {
            throw ValidationException::withMessages(['planes' => __('Elija al menos una carrera y su plan de estudios.')]);
        }

        $permitidos = DB::table('study_plans')->whereIn('career_id', $this->carrerasConPlanes($actor)->pluck('id'))->pluck('id')->map(fn ($p) => (int) $p)->all();
        if (array_diff(array_keys($porPlan), $permitidos) !== []) {
            throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        }

        return $porPlan;
    }

    /**
     * Deja la asignatura en los planes indicados que el actor gestiona; escribe solo lo que cambia (auditoría sin
     * duplicados) y no toca los planes de carreras fuera de su alcance.
     *
     * @param  array<int, int>  $planes  plan => ciclo
     */
    private function sincronizarPlanes(User $actor, int $asignaturaId, array $planes): void
    {
        $gestionables = DB::table('study_plans')->whereIn('career_id', $this->carrerasConPlanes($actor)->pluck('id'))->pluck('id');
        $actuales = DB::table('career_subjects')->where('subject_id', $asignaturaId)->whereIn('study_plan_id', $gestionables)
            ->pluck('cycle', 'study_plan_id')->mapWithKeys(fn ($ciclo, $plan) => [(int) $plan => (int) $ciclo])->all();

        if ($quitar = array_diff(array_keys($actuales), array_keys($planes))) {
            DB::table('career_subjects')->where('subject_id', $asignaturaId)->whereIn('study_plan_id', $quitar)->delete();
        }
        foreach ($planes as $plan => $ciclo) {
            if (! isset($actuales[$plan])) {
                DB::table('career_subjects')->insert(['study_plan_id' => $plan, 'subject_id' => $asignaturaId, 'cycle' => $ciclo]);
            } elseif ($actuales[$plan] !== $ciclo) {
                DB::table('career_subjects')->where('study_plan_id', $plan)->where('subject_id', $asignaturaId)->update(['cycle' => $ciclo]);
            }
        }
    }

    /**
     * Departamentos en que el actor puede crear o mover asignaturas: todos (G) o los que dirige hoy (D, ADR-072).
     *
     * @return Collection<int, \stdClass>
     */
    public function departamentosGestionables(User $actor): Collection
    {
        $alcance = $this->alcance->exigir($actor, 'asignaturas.gestionar');

        return DB::table('departments')->when($alcance !== 'G', fn ($q) => $q->whereIn('id', $this->alcance->departamentos($actor)))
            ->orderBy('code')->get(['id', 'code', 'name']);
    }

    /**
     * ¿Mantiene el actor la estructura institucional (departamentos, carreras, planes y aulas)? Solo con alcance G.
     */
    public function gestionaEstructura(User $actor): bool
    {
        return $this->alcance->de($actor, 'asignaturas.gestionar') === 'G';
    }

    /**
     * Exige asignaturas.gestionar sobre la asignatura (si se indica) y sobre el departamento de destino (si se indica).
     * Con alcance D (el Jefe, ADR-072) ambos deben ser de un departamento que dirige hoy; si no, 403.
     *
     * @throws AuthorizationException
     */
    /**
     * Asignatura para editarla, por su código, dentro del alcance del actor (Administrador G, Jefe D).
     *
     * @throws AuthorizationException
     */
    public function asignaturaParaEditar(User $actor, string $codigo): object
    {
        $asignatura = DB::table('subjects')->whereRaw('upper(code) = ?', [mb_strtoupper($codigo)])->first() ?? abort(404);
        $this->exigirAsignatura($actor, (int) $asignatura->id);

        return $asignatura;
    }

    private function exigirAsignatura(User $actor, ?int $asignaturaId, ?int $departamentoDestino = null): void
    {
        $alcance = $this->alcance->exigir($actor, 'asignaturas.gestionar');
        $departamentoActual = $asignaturaId === null ? null
            : (int) (DB::table('subjects')->where('id', $asignaturaId)->value('department_id') ?? abort(404));
        if ($alcance === 'G') {
            return;
        }

        $propios = $this->alcance->departamentos($actor);
        foreach (array_filter([$departamentoActual, $departamentoDestino]) as $departamento) {
            if (! in_array($departamento, $propios, true)) {
                throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
            }
        }
    }

    private function puedeGestionarAsignatura(User $actor, int $asignaturaId): bool
    {
        $alcance = $this->alcance->de($actor, 'asignaturas.gestionar');

        return $alcance === 'G' || ($alcance === 'D' && DB::table('subjects')->where('id', $asignaturaId)
            ->whereIn('department_id', $this->alcance->departamentos($actor))->exists());
    }

    /**
     * La estructura institucional es solo del Administrador (asignaturas.gestionar con alcance G).
     *
     * @throws AuthorizationException
     */
    private function exigirGlobal(User $actor): void
    {
        if ($this->alcance->exigir($actor, 'asignaturas.gestionar') !== 'G') {
            throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        }
    }

    /**
     * @param  array<int, int>  $prerrequisitos
     */
    private function creaCiclo(int $asignatura, array $prerrequisitos): bool
    {
        $pendientes = $prerrequisitos;
        $vistos = [];
        while ($pendientes) {
            $actual = array_pop($pendientes);
            if ($actual === $asignatura) {
                return true;
            }
            if (isset($vistos[$actual])) {
                continue;
            }
            $vistos[$actual] = true;
            array_push($pendientes, ...DB::table('subject_prerequisites')->where('subject_id', $actual)->pluck('prerequisite_id')->map(fn ($id) => (int) $id)->all());
        }

        return false;
    }
}
