<?php

namespace App\Modules\Academica\Services;

use App\Models\User;
use App\Modules\Academica\Events\HorarioCambiado;
use App\Modules\Calificaciones\Services\ComputoService;
use App\Support\Alcance;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
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
    public const PERMISOS = ['secciones.gestionar', 'secciones.asignar-docente', 'secciones.cupo'];

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
        $buscar = mb_strtolower(trim($filtros['buscar'] ?? ''));
        $consulta->when($buscar !== '', fn ($q) => $q->where(fn ($q) => $q->whereRaw('lower(subjects.code) like ?', ["%{$buscar}%"])->orWhereRaw('lower(subjects.name) like ?', ["%{$buscar}%"])));
        $consulta->when($filtros['ciclo'] ?? null, fn ($q, $c) => $q->havingRaw('min(career_subjects.cycle) = ?', [(int) $c]));

        $secciones = $this->visibles($consulta, $actor)->get();
        $ids = $secciones->pluck('id')->all();

        $docentes = DB::table('teaching_assignments')->join('users', 'users.id', '=', 'teaching_assignments.teacher_id')
            ->whereIn('section_id', $ids)->whereNull('ended_at')->orderByDesc('is_responsible')
            ->get(['section_id', 'users.id', 'users.first_name', 'users.last_name', 'is_responsible'])->groupBy('section_id');
        $bloques = DB::table('section_schedules')->leftJoin('classrooms', 'classrooms.id', '=', 'section_schedules.classroom_id')
            ->whereIn('section_id', $ids)->orderBy('weekday')->orderBy('start_time')
            ->get(['section_schedules.id', 'section_id', 'weekday', 'start_time', 'end_time', 'classrooms.code as aula', 'classroom_id', 'period_id'])->groupBy('section_id');

        $resultado = $secciones->map(function ($s) use ($docentes, $bloques, $periodoId) {
            $s->docentes = ($docentes[$s->id] ?? collect())->values();
            $s->horario = ($bloques[$s->id] ?? collect())->map(fn ($b) => self::etiquetaBloque($b))->all();
            $s->bloques = ($bloques[$s->id] ?? collect())->map(fn ($b) => (object) ['id' => (int) $b->id, 'etiqueta' => self::etiquetaBloque($b), 'aulaId' => $b->classroom_id ? (int) $b->classroom_id : null])->values();
            $s->choque = ($bloques[$s->id] ?? collect())->map(fn ($b) => $this->horarios->chocaAula((int) $periodoId, $b->classroom_id ? (int) $b->classroom_id : null, (int) $b->weekday, substr($b->start_time, 0, 5), substr($b->end_time, 0, 5), (int) $s->id))->filter()->first();
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

    public function crearSeccion(User $actor, int $periodoId, int $asignaturaId, string $grupo, int $cupoMaximo, int $cupoMinimo = 10): int
    {
        $this->alcance->exigir($actor, 'secciones.gestionar');
        $this->exigirAsignaturaEnAlcance($actor, $asignaturaId);
        if (DB::table('sections')->where('period_id', $periodoId)->where('subject_id', $asignaturaId)->where('code', $grupo)->exists()) {
            throw ValidationException::withMessages(['grupo' => __('Ya existe ese grupo para la asignatura en el período.')]);
        }

        // La sección nace con los cómputos de la plantilla del período (RF17), en la misma transacción.
        return DbContext::run(function () use ($periodoId, $asignaturaId, $grupo, $cupoMinimo, $cupoMaximo) {
            $id = DB::table('sections')->insertGetId([
                'ulid' => (string) str()->ulid(), 'period_id' => $periodoId, 'subject_id' => $asignaturaId, 'code' => $grupo,
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
        if ($choque = $this->horarios->chocaAula($periodo, $aulaId, $dia, $inicio, $fin, $seccionId)) {
            throw ValidationException::withMessages(['aula' => __('El aula ya está ocupada en ese horario por :seccion.', ['seccion' => $choque])]);
        }
        $nuevo = collect([(object) ['weekday' => $dia, 'start_time' => $inicio, 'end_time' => $fin]]);
        foreach (DB::table('teaching_assignments')->where('section_id', $seccionId)->whereNull('ended_at')->pluck('teacher_id') as $docente) {
            $otras = DB::table('teaching_assignments')->join('sections', 'sections.id', '=', 'teaching_assignments.section_id')
                ->where('teacher_id', $docente)->whereNull('ended_at')->where('sections.period_id', $periodo)->where('sections.id', '!=', $seccionId)->pluck('sections.id')->all();
            if ($this->horarios->seCruzan($nuevo, $this->horarios->bloques($otras))) {
                throw ValidationException::withMessages(['dia' => __('Ese horario choca con otra sección del docente asignado.')]);
            }
        }

        DbContext::run(fn () => DB::table('section_schedules')->insert([
            'section_id' => $seccionId, 'period_id' => $periodo, 'classroom_id' => $aulaId, 'weekday' => $dia, 'start_time' => $inicio, 'end_time' => $fin,
            'created_at' => now(), 'updated_at' => now(),
        ]), 'section.schedule_added');

        $bloque = (object) ['weekday' => $dia, 'start_time' => $inicio, 'end_time' => $fin, 'aula' => $aulaId ? DB::table('classrooms')->where('id', $aulaId)->value('code') : null];
        $this->avisarCambio($seccionId, __('Se agregó el horario :h.', ['h' => self::etiquetaBloque($bloque)]));
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
        if ($choque = $this->horarios->chocaAula((int) $bloque->period_id, $aulaId, (int) $bloque->weekday, substr($bloque->start_time, 0, 5), substr($bloque->end_time, 0, 5), $seccionId)) {
            throw ValidationException::withMessages(['aula' => __('El aula ya está ocupada en ese horario por :seccion.', ['seccion' => $choque])]);
        }

        DbContext::run(fn () => DB::table('section_schedules')->where('id', $bloqueId)->where('section_id', $seccionId)
            ->update(['classroom_id' => $aulaId, 'updated_at' => now()]), 'section.classroom_changed');

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
            throw ValidationException::withMessages(['docente' => __('Ese docente ya está asignado a la sección.')]);
        }
        if ($choque = $this->horarios->chocaDocente($docenteId, $seccionId)) {
            throw ValidationException::withMessages(['docente' => __('El docente tiene un choque de horario con :seccion.', ['seccion' => $choque])]);
        }
        $tope = $this->tope('max_teachers_per_section', self::MAX_DOCENTES_POR_SECCION);
        $asignados = DB::table('teaching_assignments')->where('section_id', $seccionId)->whereNull('ended_at')->count();
        if ($asignados >= $tope) {
            throw ValidationException::withMessages(['docente' => __('La sección ya tiene :actual docentes y el máximo es :tope.', ['actual' => $asignados, 'tope' => $tope])]);
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
     * Asignaturas que el actor puede programar (su carrera, o todas).
     *
     * @return Collection<int, object>
     */
    public function asignaturas(User $actor): Collection
    {
        $alcance = $this->alcance->exigir($actor, 'secciones.gestionar');

        return DB::table('subjects')->where('is_active', true)
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
                throw ValidationException::withMessages(['cupo' => __('El cupo no puede ser menor que el mínimo de la sección (:min).', ['min' => $seccion->min_capacity])]);
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
    private function exigirAsignaturaEnAlcance(User $actor, int $asignaturaId): void
    {
        if (! $this->asignaturas($actor)->contains('id', $asignaturaId)) {
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
            throw ValidationException::withMessages(['fin' => __('La sección ya suma :actual h semanales y con este bloque pasaría el máximo de :tope h.', [
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
