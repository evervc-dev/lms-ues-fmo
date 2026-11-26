<?php

namespace App\Modules\Academica\Services;

use App\Models\User;
use App\Modules\Academica\Events\HorarioCambiado;
use App\Modules\Calificaciones\Services\ComputoService;
use App\Support\Alcance;
use App\Support\Busqueda;
use App\Support\DbContext;
use App\Support\DbError;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Carga académica (RF08, maquetas 23 y 24): secciones del período con horario, aula y docentes.
 * Coordinador (C, su carrera) y Administrador (G). Se rechazan los choques de aula y de docente, y cada cambio de
 * horario o aula avisa a los estudiantes matriculados y a los docentes de la sección (evento HorarioCambiado).
 */
class CargaAcademicaService
{
    private const DIAS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

    /** Permisos que dan acceso a la carga académica (ADR-037). */
    public const PERMISOS = ['secciones.crear', 'secciones.gestionar', 'secciones.asignar-docente', 'secciones.cupo'];

    /** Cupo máximo de una sección. */
    public const CUPO_MAXIMO = 200;

    /** Topes por omisión si faltan en system_settings (ADR-054, provisionales hasta que los fije la Jefatura). */
    public const MAX_DOCENTES_POR_SECCION = 2;

    public const MAX_HORAS_SEMANALES_POR_SECCION = 10;

    public function __construct(private Alcance $alcance, private HorarioService $horarios, private ComputoService $computos) {}

    /**
     * @param  array{buscar?: string, ciclo?: string, estado?: string}  $filtros
     * @return Collection<int, object>
     */
    public function secciones(User $actor, ?int $periodoId, array $filtros = []): Collection
    {
        $consulta = DB::table('sections')->join('subjects', 'subjects.id', '=', 'sections.subject_id')
            ->leftJoin('career_subjects', 'career_subjects.subject_id', '=', 'subjects.id')
            ->where('sections.period_id', $periodoId)
            ->select('sections.id', 'sections.ulid', 'sections.code as grupo', 'sections.max_capacity', 'sections.min_capacity', 'subjects.id as subject_id',
                'subjects.code', 'subjects.name', DB::raw('min(career_subjects.cycle) as ciclo'),
                DB::raw("(select count(*) from enrollments e where e.section_id = sections.id and e.status = 'active') as inscritos"))
            ->groupBy('sections.id', 'sections.ulid', 'sections.code', 'sections.max_capacity', 'sections.min_capacity', 'subjects.id', 'subjects.code', 'subjects.name')
            ->orderBy('subjects.code')->orderBy('sections.code');
        $buscar = trim($filtros['buscar'] ?? '');
        $consulta->when($buscar !== '', fn ($q) => $q->where(Busqueda::contiene($buscar, ['subjects.code', 'subjects.name'])));
        $consulta->when($filtros['ciclo'] ?? null, fn ($q, $c) => $q->havingRaw('min(career_subjects.cycle) = ?', [(int) $c]));

        $secciones = $this->visibles($consulta, $actor)->get();
        $ids = $secciones->pluck('id')->all();

        $docentes = DB::table('teaching_assignments')->join('users', 'users.id', '=', 'teaching_assignments.teacher_id')
            ->whereIn('section_id', $ids)->whereNull('ended_at')->orderByDesc('is_responsible')
            ->get(['section_id', 'users.id', 'users.first_name', 'users.last_name', 'is_responsible'])->groupBy('section_id');
        $bloques = DB::table('section_schedules')->leftJoin('classrooms', 'classrooms.id', '=', 'section_schedules.classroom_id')
            ->whereIn('section_id', $ids)->orderBy('weekday')->orderBy('start_time')
            ->get(['section_schedules.id', 'section_id', 'weekday', 'start_time', 'end_time', 'classrooms.code as aula', 'classroom_id', 'period_id'])->groupBy('section_id');

        // Choques de aula de todo el período en una sola consulta: antes era una por bloque y la página tardaba ~2 s (QA admin 21/11).
        $ocupacion = DB::table('section_schedules')->join('sections', 'sections.id', '=', 'section_schedules.section_id')
            ->join('subjects', 'subjects.id', '=', 'sections.subject_id')
            ->where('section_schedules.period_id', $periodoId)->whereNotNull('section_schedules.classroom_id')
            ->get(['section_schedules.section_id', 'section_schedules.classroom_id', 'section_schedules.weekday', 'section_schedules.start_time', 'section_schedules.end_time',
                DB::raw("subjects.code || ' · ' || sections.code as etiqueta")])
            ->groupBy(fn ($b) => $b->classroom_id.'-'.$b->weekday);
        $choqueDe = function (object $b, int $seccionId) use ($ocupacion): ?string {
            if ($b->classroom_id === null) {
                return null;
            }
            $otro = ($ocupacion[$b->classroom_id.'-'.$b->weekday] ?? collect())->first(fn ($o) => (int) $o->section_id !== $seccionId
                && substr($o->start_time, 0, 5) < substr($b->end_time, 0, 5) && substr($o->end_time, 0, 5) > substr($b->start_time, 0, 5));

            return $otro?->etiqueta;
        };

        $planes = app(PlanesService::class)->etiquetas($secciones->pluck('subject_id'));

        $resultado = $secciones->map(function ($s) use ($docentes, $bloques, $choqueDe, $planes) {
            $s->docentes = ($docentes[$s->id] ?? collect())->values();
            $s->plan = $planes[$s->subject_id] ?? null;
            $s->horario = ($bloques[$s->id] ?? collect())->map(fn ($b) => self::etiquetaBloque($b))->all();
            $s->bloques = ($bloques[$s->id] ?? collect())->map(fn ($b) => (object) ['id' => (int) $b->id, 'etiqueta' => self::etiquetaBloque($b), 'aulaId' => $b->classroom_id ? (int) $b->classroom_id : null])->values();
            $s->choque = ($bloques[$s->id] ?? collect())->map(fn ($b) => $choqueDe($b, (int) $s->id))->filter()->first();
            $s->estado = $s->choque ? 'choque' : ($s->docentes->isEmpty() ? 'sin_docente' : ((int) $s->inscritos >= (int) $s->max_capacity ? 'llena' : 'ok'));

            return $s;
        });

        return ($filtros['estado'] ?? '') !== '' ? $resultado->where('estado', $filtros['estado'])->values() : $resultado;
    }

    /**
     * Docentes que se pueden asignar (activos, con perfil docente y con el rol docente vigente), con su carga en el período.
     * Al quitar el rol el perfil se conserva como historial (RF04), por eso se exige también el rol.
     *
     * @return Collection<int, object>
     */
    public function docentesDisponibles(?int $periodoId): Collection
    {
        return DB::table('teacher_profiles')->join('users', 'users.id', '=', 'teacher_profiles.user_id')
            ->join('departments', 'departments.id', '=', 'teacher_profiles.department_id')
            ->tap(fn (Builder $q) => $this->soloDocentesActivos($q))
            ->select('users.id', 'users.first_name', 'users.last_name', 'departments.code as departamento',
                DB::raw('(select count(*) from teaching_assignments t join sections s on s.id = t.section_id where t.teacher_id = users.id and t.ended_at is null and s.period_id = '.(int) $periodoId.') as carga'))
            ->orderBy('users.last_name')->get();
    }

    /**
     * Crea un grupo teórico (RF08). Si se indica la carrera, debe estar en el alcance del actor y la asignatura debe
     * estar en alguno de sus planes de estudio: así se crea desde la pantalla (carrera → materia). La carrera queda como
     * dato de quién lo solicitó; el grupo lo comparten todas las carreras que llevan la materia (ADR-080).
     *
     * @throws AuthorizationException|ValidationException
     */
    public function crearSeccion(User $actor, int $periodoId, int $asignaturaId, string $grupo, int $cupoMaximo, int $cupoMinimo = 10, ?int $carreraId = null): int
    {
        // Crear es del Administrador y del Jefe (secciones.crear, ADR-074); el Coordinador ya no crea grupos.
        $this->alcance->exigir($actor, 'secciones.crear');
        $this->exigirAsignaturaEnAlcance($actor, $asignaturaId, 'secciones.crear');
        if (! PeriodoService::preparables()->contains('id', $periodoId)) {
            throw ValidationException::withMessages(['asignaturaId' => __('Solo se crean grupos en el período en curso o en uno en preparación.')]);
        }
        if ($carreraId !== null) {
            if (! $this->carrerasParaGrupos($actor)->contains('id', $carreraId)) {
                throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
            }
            if (! $this->asignaturasDeCarrera($actor, $carreraId)->contains('id', $asignaturaId)) {
                throw ValidationException::withMessages(['asignaturaId' => __('La asignatura no está en el plan de estudios de esa carrera.')]);
            }
        }
        if (DB::table('sections')->where('period_id', $periodoId)->where('subject_id', $asignaturaId)->where('code', $grupo)->exists()) {
            throw ValidationException::withMessages(['grupo' => __('Ya existe ese grupo para la asignatura en el período.')]);
        }

        // La sección nace con los cómputos de la plantilla del período (RF17), en la misma transacción.
        return DbContext::run(function () use ($periodoId, $asignaturaId, $grupo, $cupoMinimo, $cupoMaximo, $carreraId) {
            $id = DB::table('sections')->insertGetId([
                'ulid' => (string) str()->ulid(), 'period_id' => $periodoId, 'subject_id' => $asignaturaId, 'career_id' => $carreraId, 'code' => $grupo,
                'min_capacity' => $cupoMinimo, 'max_capacity' => $cupoMaximo, 'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->computos->copiarPlantilla((int) $id);

            return $id;
        }, 'section.created');
    }

    public function agregarHorario(User $actor, int $seccionId, int $dia, string $inicio, string $fin, ?int $aulaId): void
    {
        $this->exigirSeccion($actor, $seccionId);
        if ($fin <= $inicio) {
            throw ValidationException::withMessages(['fin' => __('La hora de fin debe ser posterior a la de inicio.')]);
        }
        $this->exigirTopeDeHoras($seccionId, $inicio, $fin);
        $periodo = (int) DB::table('sections')->where('id', $seccionId)->value('period_id');
        if ($choque = $this->horarios->chocaAula($periodo, $aulaId, $dia, $inicio, $fin)) {
            throw ValidationException::withMessages(['aula' => __('El aula ya está ocupada en ese horario por :seccion.', ['seccion' => $choque])]);
        }
        $nuevo = collect([(object) ['weekday' => $dia, 'start_time' => $inicio, 'end_time' => $fin]]);
        foreach (DB::table('teaching_assignments')->where('section_id', $seccionId)->whereNull('ended_at')->pluck('teacher_id') as $docente) {
            $otras = DB::table('teaching_assignments')->join('sections', 'sections.id', '=', 'teaching_assignments.section_id')
                ->where('teacher_id', $docente)->whereNull('ended_at')->where('sections.period_id', $periodo)->where('sections.id', '!=', $seccionId)->pluck('sections.id')->all();
            if ($this->horarios->seCruzan($nuevo, $this->horarios->bloques($otras))) {
                throw ValidationException::withMessages(['dia' => __('Ese horario choca con otro grupo teórico del docente asignado.')]);
            }
        }

        // Dos envíos a la vez pueden pasar la validación anterior: el EXCLUDE de la base decide y se traduce (no un 500).
        $this->sinChoqueEnBase(fn () => DbContext::run(fn () => DB::table('section_schedules')->insert([
            'section_id' => $seccionId, 'period_id' => $periodo, 'classroom_id' => $aulaId, 'weekday' => $dia, 'start_time' => $inicio, 'end_time' => $fin,
            'created_at' => now(), 'updated_at' => now(),
        ]), 'section.schedule_added'));

        $bloque = (object) ['weekday' => $dia, 'start_time' => $inicio, 'end_time' => $fin, 'aula' => $aulaId ? DB::table('classrooms')->where('id', $aulaId)->value('code') : null];
        $this->avisarCambio($seccionId, __('Se agregó el horario :h.', ['h' => self::etiquetaBloque($bloque)]));
    }

    /**
     * Ejecuta la escritura de un bloque y convierte el choque de aula de la base (23P01, EXCLUDE
     * `section_schedules_classroom_no_overlap`) en un error del campo aula, con el mensaje de DbError.
     *
     * @throws ValidationException
     */
    private function sinChoqueEnBase(callable $escritura): void
    {
        try {
            $escritura();
        } catch (QueryException $excepcion) {
            if ((string) ($excepcion->errorInfo[0] ?? $excepcion->getCode()) !== '23P01') {
                throw $excepcion;
            }

            throw ValidationException::withMessages(['aula' => DbError::toDomain($excepcion)->getMessage()]);
        }
    }

    /**
     * Quita un bloque del horario de la sección (RF08) y avisa a sus estudiantes y docentes.
     *
     * @throws AuthorizationException
     */
    public function quitarHorario(User $actor, int $seccionId, int $bloqueId): void
    {
        $this->exigirSeccion($actor, $seccionId);
        $bloque = $this->bloque($seccionId, $bloqueId);

        DbContext::run(fn () => DB::table('section_schedules')->where('id', $bloqueId)->where('section_id', $seccionId)->delete(), 'section.schedule_removed');

        $this->avisarCambio($seccionId, __('Se quitó el horario :h.', ['h' => self::etiquetaBloque($bloque)]));
    }

    /**
     * Cambia el aula de un bloque del horario (RF08), sin choques de aula, y avisa a sus estudiantes y docentes.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function cambiarAula(User $actor, int $seccionId, int $bloqueId, ?int $aulaId): void
    {
        $this->exigirSeccion($actor, $seccionId);
        $bloque = $this->bloque($seccionId, $bloqueId);
        if ($aulaId === ($bloque->classroom_id === null ? null : (int) $bloque->classroom_id)) {
            throw ValidationException::withMessages(['aula' => __('El bloque ya está en esa aula.')]);
        }
        if ($choque = $this->horarios->chocaAula((int) $bloque->period_id, $aulaId, (int) $bloque->weekday, substr($bloque->start_time, 0, 5), substr($bloque->end_time, 0, 5), exceptoBloque: $bloqueId)) {
            throw ValidationException::withMessages(['aula' => __('El aula ya está ocupada en ese horario por :seccion.', ['seccion' => $choque])]);
        }

        $this->sinChoqueEnBase(fn () => DbContext::run(fn () => DB::table('section_schedules')->where('id', $bloqueId)->where('section_id', $seccionId)
            ->update(['classroom_id' => $aulaId, 'updated_at' => now()]), 'section.classroom_changed'));

        $nueva = $aulaId ? DB::table('classrooms')->where('id', $aulaId)->value('code') : null;
        $this->avisarCambio($seccionId, __('El horario :h cambió de aula: ahora es :a.', [
            'h' => self::etiquetaBloque((object) ['weekday' => $bloque->weekday, 'start_time' => $bloque->start_time, 'end_time' => $bloque->end_time, 'aula' => $bloque->aula]),
            'a' => $nueva ?? __('virtual, sin aula'),
        ]));
    }

    /**
     * Asigna un docente. Si es el responsable, el responsable anterior pasa a co-docente.
     */
    public function asignarDocente(User $actor, int $seccionId, int $docenteId, bool $responsable = true): void
    {
        $this->alcance->exigir($actor, 'secciones.asignar-docente');
        $this->exigirSeccion($actor, $seccionId, 'secciones.asignar-docente');
        if (! DB::table('teacher_profiles')->where('user_id', $docenteId)->exists()) {
            throw ValidationException::withMessages(['docente' => __('La persona no tiene perfil docente.')]);
        }
        if (! $this->soloDocentesActivos(DB::table('users')->where('users.id', $docenteId))->exists()) {
            throw ValidationException::withMessages(['docente' => __('La persona no es un docente activo.')]);
        }
        if (DB::table('teaching_assignments')->where('section_id', $seccionId)->where('teacher_id', $docenteId)->whereNull('ended_at')->exists()) {
            throw ValidationException::withMessages(['docente' => __('Ese docente ya está asignado al grupo teórico.')]);
        }
        if ($choque = $this->horarios->chocaDocente($docenteId, $seccionId)) {
            throw ValidationException::withMessages(['docente' => __('El docente tiene un choque de horario con :seccion.', ['seccion' => $choque])]);
        }
        $tope = $this->tope('max_teachers_per_section', self::MAX_DOCENTES_POR_SECCION);
        $asignados = DB::table('teaching_assignments')->where('section_id', $seccionId)->whereNull('ended_at')->count();
        if ($asignados >= $tope) {
            throw ValidationException::withMessages(['docente' => __('El grupo teórico ya tiene :actual docentes y el máximo es :tope.', ['actual' => $asignados, 'tope' => $tope])]);
        }

        DbContext::run(function () use ($seccionId, $docenteId, $responsable) {
            if ($responsable) {
                DB::table('teaching_assignments')->where('section_id', $seccionId)->whereNull('ended_at')->where('is_responsible', true)->update(['is_responsible' => false, 'updated_at' => now()]);
            }
            DB::table('teaching_assignments')->insert(['section_id' => $seccionId, 'teacher_id' => $docenteId, 'is_responsible' => $responsable,
                'started_at' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
        }, 'section.teacher_assigned');
    }

    public function quitarDocente(User $actor, int $seccionId, int $docenteId): void
    {
        $this->alcance->exigir($actor, 'secciones.asignar-docente');
        $this->exigirSeccion($actor, $seccionId, 'secciones.asignar-docente');

        DbContext::run(fn () => DB::table('teaching_assignments')->where('section_id', $seccionId)->where('teacher_id', $docenteId)->whereNull('ended_at')
            ->update(['ended_at' => now()->toDateString(), 'is_responsible' => false, 'updated_at' => now()]), 'section.teacher_removed');
    }

    /**
     * Departamentos en que el actor crea grupos teóricos: todos (G), los que dirige (D) o los de las carreras que
     * coordina (C).
     *
     * @return Collection<int, \stdClass>
     */
    public function departamentosParaGrupos(User $actor): Collection
    {
        $global = $this->alcance->exigir($actor, 'secciones.crear') === 'G';

        return DB::table('departments')->whereNull('archived_at')
            ->when(! $global, fn ($q) => $q->whereIn('id', $this->carrerasParaGrupos($actor)->pluck('department_id')->unique()->all()))
            ->orderBy('code')->get(['id', 'code', 'name']);
    }

    /**
     * Carreras para elegir al crear un grupo teórico: todas (G), las de su departamento (D) o las que coordina (C).
     *
     * @return Collection<int, \stdClass>
     */
    public function carrerasParaGrupos(User $actor): Collection
    {
        $alcance = $this->alcance->exigir($actor, 'secciones.crear');

        return DB::table('careers')->whereNull('archived_at')
            ->when($alcance === 'D', fn ($q) => $q->whereIn('department_id', $this->alcance->departamentos($actor)))
            ->when($alcance === 'C', fn ($q) => $q->whereIn('id', $this->alcance->carreras($actor)))
            ->when(! in_array($alcance, ['G', 'D', 'C'], true), fn ($q) => $q->whereRaw('1 = 0'))
            ->orderBy('code')->get(['id', 'code', 'name', 'department_id']);
    }

    /**
     * Asignaturas de los planes de la carrera que el actor puede programar (con D, solo las de su departamento).
     *
     * @return Collection<int, object>
     */
    public function asignaturasDeCarrera(User $actor, int $carreraId): Collection
    {
        $delPlan = DB::table('career_subjects')->join('study_plans', 'study_plans.id', '=', 'career_subjects.study_plan_id')
            ->where('study_plans.career_id', $carreraId)->pluck('career_subjects.subject_id')->map(fn ($id) => (int) $id)->all();

        return $this->asignaturas($actor, 'secciones.crear')->filter(fn ($a) => in_array((int) $a->id, $delPlan, true))->values();
    }

    /**
     * Asignaturas que el actor puede programar: todas (G), las de su departamento (D, el Jefe, ADR-073)
     * o las de los planes de su carrera (C).
     *
     * @return Collection<int, object>
     */
    public function asignaturas(User $actor, string $permiso = 'secciones.gestionar'): Collection
    {
        $alcance = $this->alcance->exigir($actor, $permiso);

        return DB::table('subjects')->where('is_active', true)
            ->when($alcance === 'D', fn ($q) => $q->whereIn('department_id', $this->alcance->departamentos($actor)))
            ->when($alcance === 'C', fn ($q) => $q->whereIn('id', DB::table('career_subjects')->join('study_plans', 'study_plans.id', '=', 'career_subjects.study_plan_id')
                ->whereIn('study_plans.career_id', $this->alcance->carreras($actor))->select('career_subjects.subject_id')))
            ->orderBy('code')->get(['id', 'code', 'name']);
    }

    /**
     * @throws AuthorizationException
     */
    private function exigirSeccion(User $actor, int $seccionId, string $permiso = 'secciones.gestionar'): void
    {
        if (! $this->alcance->puedeEnSeccion($actor, $permiso, $seccionId)) {
            throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        }
    }

    /**
     * Cambia el cupo máximo (ADR-037: Administrador y Jefe en su departamento). No baja de los inscritos activos
     * ni del cupo mínimo de la sección.
     */
    public function cambiarCupo(User $actor, int $seccionId, int $cupoMaximo): void
    {
        $this->alcance->exigir($actor, 'secciones.cupo');
        $this->exigirSeccion($actor, $seccionId, 'secciones.cupo');
        if ($cupoMaximo < 1 || $cupoMaximo > self::CUPO_MAXIMO) {
            throw ValidationException::withMessages(['cupo' => __('El cupo debe estar entre 1 y :max.', ['max' => self::CUPO_MAXIMO])]);
        }

        // La sección queda bloqueada mientras se cuentan los inscritos: una inscripción simultánea espera.
        DbContext::run(function () use ($seccionId, $cupoMaximo) {
            $seccion = DB::table('sections')->where('id', $seccionId)->lockForUpdate()->first(['min_capacity']);
            $inscritos = DB::table('enrollments')->where('section_id', $seccionId)->where('status', 'active')->count();

            if ($cupoMaximo < $inscritos) {
                throw ValidationException::withMessages(['cupo' => __('Hay :n estudiantes inscritos: el cupo no puede ser menor.', ['n' => $inscritos])]);
            }
            if ($cupoMaximo < (int) $seccion->min_capacity) {
                throw ValidationException::withMessages(['cupo' => __('El cupo no puede ser menor que el mínimo del grupo teórico (:min).', ['min' => $seccion->min_capacity])]);
            }

            DB::table('sections')->where('id', $seccionId)->update(['max_capacity' => $cupoMaximo, 'updated_at' => now()]);
        }, 'section.capacity_changed');
    }

    /**
     * Secciones que el actor ve en la carga académica: la unión de las que gestiona, en las que asigna docentes
     * y en las que cambia el cupo.
     *
     * @throws AuthorizationException
     */
    private function visibles(Builder $consulta, User $actor): Builder
    {
        $permisos = array_values(array_filter(self::PERMISOS, fn (string $permiso) => $this->alcance->de($actor, $permiso) !== null));
        if ($permisos === []) {
            throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        }

        return $consulta->where(function ($q) use ($permisos, $actor) {
            foreach ($permisos as $permiso) {
                $q->orWhereIn('sections.id', $this->alcance->secciones(DB::table('sections'), $actor, $permiso)->select('sections.id'));
            }
        });
    }

    /**
     * @throws AuthorizationException
     */
    private function exigirAsignaturaEnAlcance(User $actor, int $asignaturaId, string $permiso = 'secciones.gestionar'): void
    {
        if (! $this->asignaturas($actor, $permiso)->contains('id', $asignaturaId)) {
            throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        }
    }

    /**
     * «Lun 07:00–08:40 · B21»: día, horas y aula de un bloque del horario.
     */
    /**
     * Tope de horas semanales de la sección (ADR-054): la suma de sus bloques más el nuevo. Solo se comprueba al
     * agregar un bloque, así que las secciones que ya lo pasan siguen como están.
     */
    private function exigirTopeDeHoras(int $seccionId, string $inicio, string $fin): void
    {
        $minutos = fn (string $desde, string $hasta) => (int) Carbon::parse($desde)->diffInMinutes(Carbon::parse($hasta));
        $actual = DB::table('section_schedules')->where('section_id', $seccionId)->get(['start_time', 'end_time'])
            ->sum(fn (object $b) => $minutos((string) $b->start_time, (string) $b->end_time));
        $tope = $this->tope('max_weekly_hours_per_section', self::MAX_HORAS_SEMANALES_POR_SECCION);

        if ($actual + $minutos($inicio, $fin) > $tope * 60) {
            throw ValidationException::withMessages(['fin' => __('El grupo teórico ya suma :actual h semanales y con este bloque pasaría el máximo de :tope h.', [
                'actual' => rtrim(rtrim(number_format($actual / 60, 1, '.', ''), '0'), '.'), 'tope' => $tope,
            ])]);
        }
    }

    /** Tope de la carga académica en system_settings (ADR-054), con su valor por omisión. */
    private function tope(string $clave, int $porDefecto): int
    {
        return (int) (json_decode((string) DB::table('system_settings')->where('key', $clave)->value('value'), true) ?? $porDefecto);
    }

    private static function etiquetaBloque(object $bloque): string
    {
        return self::DIAS[(int) $bloque->weekday].' '.substr($bloque->start_time, 0, 5).'–'.substr($bloque->end_time, 0, 5).(($bloque->aula ?? null) ? ' · '.$bloque->aula : '');
    }

    private function bloque(int $seccionId, int $bloqueId): object
    {
        return DB::table('section_schedules')->leftJoin('classrooms', 'classrooms.id', '=', 'section_schedules.classroom_id')
            ->where('section_schedules.id', $bloqueId)->where('section_schedules.section_id', $seccionId)
            ->first(['section_schedules.id', 'period_id', 'weekday', 'start_time', 'end_time', 'classroom_id', 'classrooms.code as aula']) ?? abort(404);
    }

    /**
     * Se dispara después de confirmar la transacción: si el cambio falla, nadie recibe el aviso.
     */
    private function avisarCambio(int $seccionId, string $detalle): void
    {
        HorarioCambiado::dispatch($seccionId, $detalle, (string) str()->ulid());
    }

    /**
     * Restringe una consulta que une `users` a los usuarios activos con el rol docente: al quitar el rol,
     * el perfil docente se conserva como historial (RF04) y no basta para asignar secciones.
     */
    private function soloDocentesActivos(Builder $consulta): Builder
    {
        return $consulta->where('users.is_active', true)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->whereColumn('model_has_roles.model_id', 'users.id')
                ->where('model_has_roles.model_type', (new User)->getMorphClass())
                ->where('roles.name', 'docente'));
    }
}
