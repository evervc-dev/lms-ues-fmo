<?php

namespace App\Modules\Academica\Services;

use App\Models\User;
use App\Modules\Academica\Events\MatriculaCambiada;
use App\Support\Alcance;
use App\Support\Busqueda;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Matrícula (RF09, RF29, ADR-030, ADR-032, ADR-050; Flujo Matrícula y cambio de grupo).
 * Valida cupo, duplicado, choque de horario, que la asignatura sea del plan de la carrera del estudiante y sus
 * prerrequisitos aprobados (ADR-052, que supera en esto a ADR-030 y ADR-032). Docente (S), Coordinador (C),
 * Jefe (D) y Administrador (G) inscriben; el estudiante elige una materia de su carrera y ciclo y se
 * automatricula con la clave de esa asignatura, que ponen el Coordinador (C) y el Jefe (D).
 * Retirar exige motivo y lo hacen Coordinador, Jefe y Administrador.
 * Toda inscripción nueva respeta la fecha límite del departamento de la carrera del estudiante (ADR-051).
 */
class MatriculaService
{
    /** CP-RF29-05: 5 claves fallidas seguidas bloquean la auto-matrícula 15 minutos. */
    public const CLAVES_FALLIDAS = 5;

    public const BLOQUEO_SEGUNDOS = 900;

    private const VIA = ['G' => 'admin', 'D' => 'head', 'C' => 'coordinator', 'S' => 'teacher'];

    /** Semanas desde el inicio del período si falta system_settings.enrollment_deadline_weeks_default (ADR-051). */
    public const SEMANAS_PLAZO = 2;

    public function __construct(private Alcance $alcance, private HorarioService $horarios) {}

    /**
     * @param  array{buscar?: string, seccion?: string|int}  $filtros
     */
    public function matriculas(User $actor, array $filtros = [], int $porPagina = 25): LengthAwarePaginator
    {
        $buscar = mb_strtolower(trim($filtros['buscar'] ?? ''));
        $consulta = DB::table('enrollments')->join('users', 'users.id', '=', 'enrollments.student_id')
            ->join('sections', 'sections.id', '=', 'enrollments.section_id')->join('subjects', 'subjects.id', '=', 'sections.subject_id')
            ->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->where('enrollments.period_id', PeriodoService::activoId())
            ->when($buscar !== '', fn ($q) => $q->where(fn ($q) => $q->whereRaw('lower(users.institutional_email) like ?', ["%{$buscar}%"])
                ->orWhereRaw("lower(users.first_name || ' ' || users.last_name) like ?", ["%{$buscar}%"])
                ->orWhereRaw('lower(subjects.code) like ?', ["%{$buscar}%"])))
            ->when($filtros['seccion'] ?? null, fn ($q, $s) => $q->where('enrollments.section_id', $s))
            ->orderBy('subjects.code')->orderBy('users.last_name')
            ->select('enrollments.id', 'enrollments.status', 'enrollments.enrolled_via', 'enrollments.created_at', 'users.id as estudiante_id', 'users.first_name', 'users.last_name',
                'users.institutional_email', 'student_profiles.student_code', 'subjects.code', 'sections.code as grupo', 'sections.id as section_id');

        return $this->alcance->secciones($consulta, $actor, 'matricula.gestionar')->paginate($porPagina);
    }

    /**
     * Inscribe a un estudiante en una sección de su alcance.
     */
    public function inscribir(User $actor, int $estudianteId, int $seccionId): int
    {
        $alcance = $this->alcance->exigir($actor, 'matricula.gestionar');
        if (! $this->alcance->puedeEnSeccion($actor, 'matricula.gestionar', $seccionId)) {
            throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        }
        if (! DB::table('student_careers')->where('student_id', $estudianteId)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['estudiante' => __('La persona no es estudiante activo.')]);
        }

        return $this->matricular($estudianteId, $seccionId, self::VIA[$alcance] ?? 'admin', $actor->id);
    }

    public function retirar(User $actor, int $matriculaId, string $motivo): void
    {
        $alcance = $this->alcance->exigir($actor, 'matricula.gestionar');
        $matricula = DB::table('enrollments')->where('id', $matriculaId)->first() ?? abort(404);
        if ($alcance === 'S' || ! $this->alcance->puedeEnSeccion($actor, 'matricula.gestionar', (int) $matricula->section_id)) {
            throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        }
        if ($matricula->status !== 'active') {
            throw ValidationException::withMessages(['motivo' => __('La matrícula ya no está activa.')]);
        }

        DbContext::run(function () use ($matricula, $motivo, $actor) {
            DB::table('enrollments')->where('id', $matricula->id)->update(['status' => 'withdrawn', 'updated_at' => now()]);
            DB::table('enrollment_changes')->insert(['enrollment_id' => $matricula->id, 'change_type' => 'withdrawn', 'reason' => $motivo, 'actor_id' => $actor->id, 'occurred_at' => now()]);
        }, 'enrollment.withdrawn', $motivo);

        MatriculaCambiada::dispatch((int) $matricula->id, 'retirada', (string) Str::ulid(), $motivo);
    }

    /**
     * Asignaturas que el estudiante puede inscribir (RF29, ADR-050): las del plan de su carrera activa con la
     * paridad del ciclo en curso (ciclo I: ciclos impares del plan; ciclo II: pares). Cada fila trae cuántos grupos
     * abiertos tiene en el período, si tiene clave vigente y si ya está inscrito.
     *
     * @return Collection<int, object>
     */
    public function materiasInscribibles(User $estudiante): Collection
    {
        $this->alcance->exigir($estudiante, 'matricula.automatricular');
        $periodo = PeriodoService::activoId();
        $plan = DB::table('student_careers')->where('student_id', $estudiante->id)->where('status', 'active')->value('study_plan_id');
        if ($periodo === null || $plan === null) {
            return collect();
        }
        $paridad = PeriodoService::ciclo($periodo) === 2 ? 0 : 1;

        $abiertas = DB::table('sections')->where('period_id', $periodo)->where('status', 'open')
            ->groupBy('subject_id')->selectRaw('subject_id, count(*) as total')->pluck('total', 'subject_id');
        $conClave = $this->clavesVigentes($periodo)->pluck('subject_id')->map(fn ($id) => (int) $id)->all();
        $inscritas = DB::table('enrollments')->where('student_id', $estudiante->id)->where('period_id', $periodo)->where('status', 'active')
            ->pluck('subject_id')->map(fn ($id) => (int) $id)->all();

        return DB::table('career_subjects')->join('subjects', 'subjects.id', '=', 'career_subjects.subject_id')
            ->where('career_subjects.study_plan_id', $plan)->where('subjects.is_active', true)
            ->whereRaw('career_subjects.cycle % 2 = ?', [$paridad])
            ->orderBy('career_subjects.cycle')->orderBy('subjects.code')
            ->get(['subjects.id', 'subjects.code', 'subjects.name', 'subjects.credits', 'career_subjects.cycle'])
            ->each(function (object $m) use ($abiertas, $conClave, $inscritas): void {
                $m->id = (int) $m->id;
                $m->cycle = (int) $m->cycle;
                $m->secciones = (int) ($abiertas[$m->id] ?? 0);
                $m->tiene_clave = in_array($m->id, $conClave, true);
                $m->inscrito = in_array($m->id, $inscritas, true);
            });
    }

    /**
     * Detalle de una materia inscribible: descripción y grupos abiertos con su cupo, choque, horario y docente.
     * Una asignatura fuera de su carrera y ciclo da 404.
     *
     * @return array{asignatura: object, secciones: Collection<int, object>, inscrito: bool, tiene_clave: bool}
     */
    public function detalleMateria(User $estudiante, int $asignaturaId): array
    {
        $materia = $this->materiasInscribibles($estudiante)->firstWhere('id', $asignaturaId) ?? abort(404);
        $asignatura = DB::table('subjects')->where('id', $asignaturaId)->first(['id', 'code', 'name', 'credits', 'description']);
        $asignatura->cycle = $materia->cycle;

        return [
            'asignatura' => $asignatura,
            'secciones' => $this->seccionesAbiertas($estudiante, $asignaturaId, (int) PeriodoService::activoId()),
            'inscrito' => $materia->inscrito,
            'tiene_clave' => $materia->tiene_clave,
        ];
    }

    /**
     * El estudiante se inscribe en una materia de su carrera y ciclo con la clave de esa asignatura y elige el grupo.
     * La clave se compara solo con la vigente de la asignatura (se guarda con hash); 5 fallos bloquean 15 minutos.
     */
    public function automatricular(User $estudiante, int $asignaturaId, string $clave, int $seccionId): int
    {
        $this->alcance->exigir($estudiante, 'matricula.automatricular');
        $limite = 'automatricula:'.$estudiante->id;
        if (RateLimiter::tooManyAttempts($limite, self::CLAVES_FALLIDAS)) {
            throw ValidationException::withMessages(['clave' => __('Demasiados intentos con claves no válidas. Intente de nuevo en :min minutos.', ['min' => (int) ceil(RateLimiter::availableIn($limite) / 60)])]);
        }
        if (! $this->materiasInscribibles($estudiante)->contains('id', $asignaturaId)) {
            throw ValidationException::withMessages(['clave' => __('La materia no corresponde a su carrera y ciclo.')]);
        }
        $periodo = (int) PeriodoService::activoId();

        $vigente = $this->clavesVigentes($periodo)->where('subject_id', $asignaturaId)->first(['key_hash']);
        if ($vigente === null || ! Hash::check(trim($clave), $vigente->key_hash)) {
            RateLimiter::hit($limite, self::BLOQUEO_SEGUNDOS);
            throw ValidationException::withMessages(['clave' => __('La clave no es válida o venció.')]);
        }
        RateLimiter::clear($limite);

        if (! DB::table('sections')->where('id', $seccionId)->where('period_id', $periodo)->where('subject_id', $asignaturaId)->where('status', 'open')->exists()) {
            throw ValidationException::withMessages(['seccion' => __('Elija una de las secciones de la materia.')]);
        }

        return $this->matricular($estudiante->id, $seccionId, 'key', $estudiante->id);
    }

    /**
     * Claves vigentes por asignatura del período (Coordinador, sus carreras; Jefe, su departamento).
     *
     * @return Collection<int, object>
     */
    public function claves(User $actor, string $buscar = ''): Collection
    {
        $alcance = $this->alcance->exigir($actor, 'matricula.claves.gestionar');
        $periodo = PeriodoService::activoId();
        $buscar = Busqueda::fragmento($buscar);
        $ambito = $alcance === 'D'
            ? DB::table('subjects')->whereIn('department_id', $this->alcance->departamentos($actor))->select('id')
            : DB::table('career_subjects')->join('study_plans', 'study_plans.id', '=', 'career_subjects.study_plan_id')
                ->whereIn('study_plans.career_id', $this->alcance->carreras($actor))->select('career_subjects.subject_id');

        return DB::table('subjects')
            ->when($buscar !== '%%', fn ($q) => $q->where(fn ($q) => $q->whereRaw("lower(subjects.code) like ? escape '\\'", [$buscar])
                ->orWhereRaw("lower(subjects.name) like ? escape '\\'", [$buscar])))
            ->whereIn('subjects.id', $ambito)
            ->leftJoin('enrollment_keys as k', fn ($j) => $j->on('k.subject_id', '=', 'subjects.id')->where('k.period_id', $periodo)->where('k.is_active', true))
            ->select('subjects.id', 'subjects.code', 'subjects.name', 'k.id as clave_id', 'k.valid_until', 'k.updated_at as generada',
                DB::raw('(select count(*) from enrollments e where e.subject_id = subjects.id and e.period_id = '.(int) $periodo." and e.status = 'active' and e.enrolled_via = 'key') as por_clave"),
                DB::raw('(select count(*) from sections s where s.subject_id = subjects.id and s.period_id = '.(int) $periodo.') as secciones'))
            ->orderBy('subjects.code')->get();
    }

    /**
     * Pone (o cambia) la clave de una asignatura: la escrita a mano o una generada. La anterior deja de funcionar.
     * Devuelve la clave en claro una sola vez: se guarda con hash.
     */
    public function generarClave(User $actor, int $asignaturaId, ?string $vence = null, ?string $clave = null): string
    {
        $this->alcance->exigir($actor, 'matricula.claves.gestionar');
        if (! $this->claves($actor)->contains('id', $asignaturaId)) {
            throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        }
        $periodo = (int) PeriodoService::activoId();
        $clave = $clave !== null && trim($clave) !== ''
            ? trim($clave)
            : DB::table('subjects')->where('id', $asignaturaId)->value('code').'-'.Str::upper(Str::random(5));

        DbContext::run(fn () => $this->guardarClave($asignaturaId, $periodo, Hash::make($clave), $vence), 'enrollment_key.generated');

        return $clave;
    }

    /**
     * Pone la misma clave en todas las asignaturas del ámbito (ADR-050). Se calcula un solo hash y se repite por
     * asignatura, así se mantiene una clave activa por asignatura y período. Devuelve cuántas asignaturas cambió.
     */
    public function aplicarClaveATodas(User $actor, string $clave, ?string $vence = null): int
    {
        $this->alcance->exigir($actor, 'matricula.claves.gestionar');
        $clave = trim($clave);
        if ($clave === '') {
            throw ValidationException::withMessages(['clave' => __('Escriba la clave que se aplicará a todas las asignaturas.')]);
        }
        $asignaturas = $this->claves($actor)->pluck('id')->map(fn ($id) => (int) $id);
        $periodo = (int) PeriodoService::activoId();
        $hash = Hash::make($clave);

        DbContext::run(function () use ($asignaturas, $periodo, $hash, $vence): void {
            foreach ($asignaturas as $asignaturaId) {
                $this->guardarClave($asignaturaId, $periodo, $hash, $vence);
            }
        }, 'enrollment_key.generated');

        return $asignaturas->count();
    }

    public function desactivarClave(User $actor, int $asignaturaId): void
    {
        $this->alcance->exigir($actor, 'matricula.claves.gestionar');
        if (! $this->claves($actor)->contains('id', $asignaturaId)) {
            throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        }
        DbContext::run(fn () => DB::table('enrollment_keys')->where('subject_id', $asignaturaId)->where('period_id', PeriodoService::activoId())
            ->where('is_active', true)->update(['is_active' => false, 'updated_at' => now()]), 'enrollment_key.disabled');
    }

    /**
     * Plazo efectivo de inscripción del estudiante (RF29, ADR-051): la mayor entre la fecha del departamento de su
     * carrera activa (o, sin ella, inicio del período + N semanas), la prórroga del Jefe a ese departamento y la
     * prórroga personal. Vale hasta el final de ese día. Sin período en curso no hay plazo que vencer.
     *
     * @return array{fecha: Carbon, vencido: bool, origen: string}
     */
    public function plazoDe(int $estudianteId, ?int $periodoId = null): array
    {
        $periodoId ??= PeriodoService::activoId();
        if ($periodoId === null) {
            return ['fecha' => now()->endOfDay(), 'vencido' => false, 'origen' => 'defecto'];
        }
        $departamento = DB::table('student_careers')->join('careers', 'careers.id', '=', 'student_careers.career_id')
            ->where('student_careers.student_id', $estudianteId)->where('student_careers.status', 'active')
            ->orderBy('student_careers.id')->value('careers.department_id');
        $plazo = $this->reglaDepartamento($periodoId, $departamento === null ? null : (int) $departamento);

        $personal = DB::table('enrollment_extensions')->where('period_id', $periodoId)->where('student_id', $estudianteId)->value('until');
        if ($personal !== null && Carbon::parse($personal)->endOfDay()->gt($plazo['fecha'])) {
            $plazo = ['fecha' => Carbon::parse($personal)->endOfDay(), 'origen' => 'prorroga-estudiante'];
        }

        return $plazo + ['vencido' => now()->gt($plazo['fecha'])];
    }

    /**
     * Fecha límite de cada departamento del ámbito en el período en curso: la fijada (o null), la de por defecto,
     * la prórroga del Jefe y la que rige hoy (Administrador G, Jefe D, Coordinador C: departamentos de sus carreras).
     *
     * @return Collection<int, object>
     */
    public function plazos(User $actor): Collection
    {
        $departamentos = $this->departamentosDelAmbito($actor, 'matricula.plazo.gestionar');
        $periodo = PeriodoService::activoId();
        if ($periodo === null) {
            return collect();
        }
        $porDefecto = $this->plazoPorDefecto($periodo);
        $filas = DB::table('enrollment_deadlines')->where('period_id', $periodo)->get()->keyBy('department_id');

        return DB::table('departments')->when($departamentos !== null, fn ($q) => $q->whereIn('id', $departamentos))
            ->orderBy('name')->get(['id', 'name'])
            ->map(function (object $d) use ($periodo, $porDefecto, $filas): object {
                $fila = $filas[$d->id] ?? null;
                $rige = $this->reglaDepartamento($periodo, (int) $d->id)['fecha'];

                return (object) [
                    'id' => (int) $d->id,
                    'name' => $d->name,
                    'deadline' => $fila ? Carbon::parse($fila->deadline) : null,
                    'por_defecto' => $porDefecto,
                    'extended_until' => $fila?->extended_until ? Carbon::parse($fila->extended_until) : null,
                    'extension_reason' => $fila?->extension_reason,
                    'rige' => $rige,
                    'vencido' => now()->gt($rige),
                ];
            });
    }

    /**
     * Fija (o cambia) la fecha límite del departamento en el período en curso. Si hay una prórroga, la fecha debe
     * quedar antes de ella (CHECK enrollment_deadlines_extension).
     */
    public function fijarPlazo(User $actor, int $departamentoId, string $fecha): void
    {
        $this->exigirDepartamento($actor, 'matricula.plazo.gestionar', $departamentoId);
        $periodo = $this->periodoEnCurso('fecha');
        $fecha = Carbon::parse($fecha)->toDateString();
        $fila = DB::table('enrollment_deadlines')->where('period_id', $periodo)->where('department_id', $departamentoId)->first();
        if ($fila?->extended_until !== null && $fecha >= Carbon::parse($fila->extended_until)->toDateString()) {
            throw ValidationException::withMessages(['fecha' => __('La fecha debe ser anterior a la prórroga vigente, que termina el :fecha.', ['fecha' => Carbon::parse($fila->extended_until)->format('d/m/Y')])]);
        }

        DbContext::run(function () use ($fila, $periodo, $departamentoId, $fecha, $actor): void {
            if ($fila !== null) {
                DB::table('enrollment_deadlines')->where('id', $fila->id)->update(['deadline' => $fecha, 'set_by' => $actor->id, 'updated_at' => now()]);

                return;
            }
            DB::table('enrollment_deadlines')->insert(['period_id' => $periodo, 'department_id' => $departamentoId, 'deadline' => $fecha,
                'set_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
        }, 'enrollment_deadline.set');
    }

    /**
     * Prórroga del Jefe a todo su departamento, con motivo: guarda la nueva fecha y conserva la original. Debe ser
     * posterior a la que rige hoy; sin fila del departamento se crea con la fecha por defecto.
     */
    public function prorrogarDepartamento(User $actor, int $departamentoId, string $hasta, string $motivo): void
    {
        $this->exigirDepartamento($actor, 'matricula.plazo.prorrogar', $departamentoId);
        $periodo = $this->periodoEnCurso('hasta');
        if (trim($motivo) === '') {
            throw ValidationException::withMessages(['motivo' => __('Indique el motivo de la prórroga.')]);
        }
        $rige = $this->reglaDepartamento($periodo, $departamentoId)['fecha'];
        $hasta = Carbon::parse($hasta)->endOfDay();
        if (! $hasta->gt($rige)) {
            throw ValidationException::withMessages(['hasta' => __('La prórroga debe terminar después del :fecha, la fecha que rige hoy.', ['fecha' => $rige->format('d/m/Y')])]);
        }
        $fila = DB::table('enrollment_deadlines')->where('period_id', $periodo)->where('department_id', $departamentoId)->first();
        $cambios = ['extended_until' => $hasta->toDateString(), 'extension_reason' => trim($motivo), 'extended_by' => $actor->id, 'updated_at' => now()];

        DbContext::run(function () use ($fila, $periodo, $departamentoId, $cambios, $actor): void {
            if ($fila !== null) {
                DB::table('enrollment_deadlines')->where('id', $fila->id)->update($cambios);

                return;
            }
            DB::table('enrollment_deadlines')->insert($cambios + ['period_id' => $periodo, 'department_id' => $departamentoId,
                'deadline' => $this->plazoPorDefecto($periodo)->toDateString(), 'set_by' => $actor->id, 'created_at' => now()]);
        }, 'enrollment_deadline.extended', trim($motivo));
    }

    /**
     * Prórrogas por estudiante del período en curso, de los estudiantes de su ámbito (Jefe D, Coordinador C).
     *
     * @return Collection<int, object>
     */
    public function prorrogas(User $actor, string $buscar = ''): Collection
    {
        $this->alcance->exigir($actor, 'matricula.prorroga.otorgar');
        $periodo = PeriodoService::activoId();
        if ($periodo === null) {
            return collect();
        }
        $buscar = Busqueda::fragmento($buscar);
        $consulta = DB::table('enrollment_extensions as x')->join('users', 'users.id', '=', 'x.student_id')
            ->join('users as otorgante', 'otorgante.id', '=', 'x.granted_by')
            ->where('x.period_id', $periodo)
            ->when($buscar !== '%%', fn ($q) => $q->where(fn ($q) => $q->whereRaw("lower(users.institutional_email) like ? escape '\\'", [$buscar])
                ->orWhereRaw("lower(users.first_name || ' ' || users.last_name) like ? escape '\\'", [$buscar])))
            ->orderBy('users.last_name')->orderBy('users.first_name')
            ->select('x.id', 'x.student_id', 'x.until', 'x.reason', 'x.updated_at', 'users.first_name', 'users.last_name', 'users.institutional_email',
                DB::raw("otorgante.first_name || ' ' || otorgante.last_name as otorgada_por"));

        return $this->alcance->estudiantes($consulta, $actor, 'matricula.prorroga.otorgar', 'x.student_id')->get()
            ->each(function (object $p): void {
                $p->until = Carbon::parse($p->until);
                $p->vigente = now()->lte($p->until->copy()->endOfDay());
            });
    }

    /**
     * Prórroga personal de inscripción, con motivo: una por período y estudiante (si ya existe, se actualiza).
     */
    public function prorrogarEstudiante(User $actor, int $estudianteId, string $hasta, string $motivo): void
    {
        $this->alcance->exigir($actor, 'matricula.prorroga.otorgar');
        $enAmbito = $this->alcance->estudiantes(DB::table('users')->join('student_careers', 'student_careers.student_id', '=', 'users.id')
            ->where('users.id', $estudianteId)->where('student_careers.status', 'active'), $actor, 'matricula.prorroga.otorgar')->exists();
        if (! $enAmbito) {
            throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        }
        $periodo = $this->periodoEnCurso('hasta');
        if (trim($motivo) === '') {
            throw ValidationException::withMessages(['motivo' => __('Indique el motivo de la prórroga.')]);
        }
        $hasta = Carbon::parse($hasta)->startOfDay();
        if ($hasta->lt(today())) {
            throw ValidationException::withMessages(['hasta' => __('La prórroga no puede terminar antes de hoy.')]);
        }
        $cambios = ['until' => $hasta->toDateString(), 'reason' => trim($motivo), 'granted_by' => $actor->id, 'updated_at' => now()];

        DbContext::run(function () use ($periodo, $estudianteId, $cambios): void {
            $existe = DB::table('enrollment_extensions')->where('period_id', $periodo)->where('student_id', $estudianteId);
            if ($existe->exists()) {
                $existe->update($cambios);

                return;
            }
            DB::table('enrollment_extensions')->insert($cambios + ['period_id' => $periodo, 'student_id' => $estudianteId, 'created_at' => now()]);
        }, 'enrollment_extension.granted', trim($motivo));
    }

    /**
     * Solicitudes de cambio de grupo de la carrera (o todas).
     *
     * @return Collection<int, object>
     */
    public function solicitudes(User $actor, string $estado = 'pending'): Collection
    {
        $consulta = DB::table('section_change_requests as r')->join('enrollments as e', 'e.id', '=', 'r.enrollment_id')
            ->join('users as u', 'u.id', '=', 'e.student_id')->join('sections as origen', 'origen.id', '=', 'e.section_id')
            ->join('sections as destino', 'destino.id', '=', 'r.target_section_id')->join('subjects', 'subjects.id', '=', 'origen.subject_id')
            ->leftJoin('student_profiles as p', 'p.user_id', '=', 'u.id')
            ->when($estado === 'pending', fn ($q) => $q->where('r.status', 'pending'), fn ($q) => $q->where('r.status', '!=', 'pending'))
            ->orderBy('r.created_at')
            ->select('r.id', 'r.status', 'r.justification', 'r.created_at', 'r.resolved_at', 'u.first_name', 'u.last_name', 'p.student_code',
                'subjects.code', 'subjects.name', 'origen.code as grupo_origen', 'destino.code as grupo_destino', 'destino.id as destino_id', 'e.student_id');

        return $this->alcance->secciones($consulta, $actor, 'matricula.gestionar', 'origen.id')->get();
    }

    /**
     * Último día para pedir cambio de grupo (CP-RF09-07): inicio del período + sus semanas de cambio (3 por defecto).
     */
    public function plazoDeCambio(int $periodoId): Carbon
    {
        $periodo = DB::table('academic_periods')->where('id', $periodoId)->first(['start_date', 'group_change_weeks']) ?? abort(404);

        return Carbon::parse($periodo->start_date)->addWeeks((int) $periodo->group_change_weeks)->endOfDay();
    }

    /**
     * Grupos de la misma materia a los que el estudiante puede pedir cambiarse, con su plazo.
     *
     * @return array{materia: object, plazo: Carbon, abierto: bool, pendiente: bool, secciones: Collection<int, object>}
     */
    public function opcionesDeCambio(User $estudiante, int $matriculaId): array
    {
        $this->alcance->exigir($estudiante, 'matricula.solicitar-cambio');
        $matricula = $this->matriculaPropia($estudiante, $matriculaId);
        $plazo = $this->plazoDeCambio((int) $matricula->period_id);

        $secciones = DB::table('sections')->where('period_id', $matricula->period_id)->where('subject_id', $matricula->subject_id)
            ->where('status', 'open')->where('id', '!=', $matricula->section_id)
            ->select('id', 'code', 'max_capacity', DB::raw("(select count(*) from enrollments e where e.section_id = sections.id and e.status = 'active') as inscritos"))
            ->orderBy('code')->get()
            ->each(function (object $s) use ($estudiante, $matricula): void {
                $s->llena = (int) $s->inscritos >= (int) $s->max_capacity;
                $s->choque = $this->horarios->chocaEstudiante($estudiante->id, (int) $s->id, (int) $matricula->section_id);
            });

        return [
            'materia' => DB::table('subjects')->where('id', $matricula->subject_id)->first(['code', 'name']),
            'plazo' => $plazo,
            'abierto' => now()->lte($plazo),
            'pendiente' => DB::table('section_change_requests')->where('enrollment_id', $matricula->id)->where('status', 'pending')->exists(),
            'secciones' => $secciones,
        ];
    }

    /**
     * El estudiante pide cambiar de grupo con una justificación, dentro del plazo (CP-RF09-07). La resuelve el Coordinador.
     */
    public function solicitarCambio(User $estudiante, int $matriculaId, int $seccionDestinoId, string $justificacion): void
    {
        $opciones = $this->opcionesDeCambio($estudiante, $matriculaId);
        if (! $opciones['abierto']) {
            throw ValidationException::withMessages(['destino' => __('Fuera de plazo: los cambios de grupo se pedían hasta el :fecha.', ['fecha' => $opciones['plazo']->format('d/m/Y')])]);
        }
        if ($opciones['pendiente']) {
            throw ValidationException::withMessages(['destino' => __('Ya tiene una solicitud de cambio pendiente en esta materia.')]);
        }
        if (! $opciones['secciones']->contains('id', $seccionDestinoId)) {
            throw ValidationException::withMessages(['destino' => __('Elija otro grupo abierto de la misma materia.')]);
        }
        if (trim($justificacion) === '') {
            throw ValidationException::withMessages(['justificacion' => __('Explique por qué necesita el cambio.')]);
        }
        $this->validarCupoYChoque($estudiante->id, $seccionDestinoId, (int) DB::table('enrollments')->where('id', $matriculaId)->value('section_id'));

        DbContext::run(fn () => DB::table('section_change_requests')->insert([
            'enrollment_id' => $matriculaId, 'target_section_id' => $seccionDestinoId, 'justification' => trim($justificacion),
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]), 'enrollment.change.requested');
    }

    /**
     * Aprueba un cambio de grupo: la matrícula pasa a «transferida» y se crea la nueva, enlazada con transferred_to_id.
     *
     * Las notas y las entregas **se quedan en la matrícula anterior y no se mueve ninguna fila** (ADR-062): la
     * clave foránea compuesta (enrollment_id, section_id) de grades, submissions y attendance_records obliga a
     * que la nota, su actividad y la matrícula sean de la misma sección. La lectura recorre la cadena desde la
     * matrícula nueva (CalificacionService::notasDeCadena(), por cómputo; ActividadService::entregasDeCadena()).
     * La asistencia no se encadena: cada sección cuenta sus propias sesiones.
     */
    public function aprobarCambio(User $actor, int $solicitudId): void
    {
        $solicitud = $this->solicitudPendiente($actor, $solicitudId);
        $origen = DB::table('enrollments')->where('id', $solicitud->enrollment_id)->first();
        $destino = DB::table('sections')->where('id', $solicitud->target_section_id)->first();

        $nueva = DbContext::run(function () use ($actor, $solicitud, $origen, $destino) {
            // Igual que al matricular: con la sección destino bloqueada, el último cupo no se entrega dos veces.
            DB::table('sections')->where('id', $destino->id)->lockForUpdate()->first();
            $this->validarCupoYChoque((int) $origen->student_id, (int) $destino->id, (int) $origen->section_id);

            DB::table('enrollments')->where('id', $origen->id)->update(['status' => 'transferred', 'updated_at' => now()]);
            $nueva = DB::table('enrollments')->insertGetId(['student_id' => $origen->student_id, 'section_id' => $destino->id, 'period_id' => $origen->period_id,
                'subject_id' => $origen->subject_id, 'status' => 'active', 'enrolled_via' => 'group_change', 'enrolled_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('enrollments')->where('id', $origen->id)->update(['transferred_to_id' => $nueva]);
            DB::table('enrollment_changes')->insert(['enrollment_id' => $origen->id, 'change_type' => 'transferred', 'reason' => $solicitud->justification, 'actor_id' => $actor->id, 'occurred_at' => now()]);
            DB::table('section_change_requests')->where('id', $solicitud->id)->update(['status' => 'approved', 'resolved_by' => $actor->id, 'resolved_at' => now(), 'updated_at' => now()]);

            return $nueva;
        }, 'enrollment.group_changed');

        MatriculaCambiada::dispatch((int) $nueva, 'trasladada', (string) Str::ulid());
    }

    public function rechazarCambio(User $actor, int $solicitudId, string $motivo): void
    {
        $solicitud = $this->solicitudPendiente($actor, $solicitudId);
        DbContext::run(fn () => DB::table('section_change_requests')->where('id', $solicitud->id)
            ->update(['status' => 'rejected', 'resolved_by' => $actor->id, 'resolved_at' => now(), 'updated_at' => now()]), 'enrollment.group_change_rejected', $motivo);

        MatriculaCambiada::dispatch((int) $solicitud->enrollment_id, 'cambio_rechazado', (string) Str::ulid(), $motivo);
    }

    private function solicitudPendiente(User $actor, int $solicitudId): object
    {
        $solicitud = DB::table('section_change_requests')->where('id', $solicitudId)->first() ?? abort(404);
        $seccion = (int) DB::table('enrollments')->where('id', $solicitud->enrollment_id)->value('section_id');
        if (! $this->alcance->puedeEnSeccion($actor, 'matricula.gestionar', $seccion)) {
            throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        }
        if ($solicitud->status !== 'pending') {
            throw ValidationException::withMessages(['solicitud' => __('La solicitud ya fue resuelta.')]);
        }

        return $solicitud;
    }

    private function matricular(int $estudianteId, int $seccionId, string $via, int $actorId): int
    {
        DB::table('sections')->where('id', $seccionId)->exists() || abort(404);

        $plazo = $this->plazoDe($estudianteId);
        if ($plazo['vencido']) {
            throw ValidationException::withMessages(['plazo' => __('El plazo de inscripción terminó el :fecha.', ['fecha' => $plazo['fecha']->format('d/m/Y')])]);
        }

        // La sección queda bloqueada mientras se revisan el cupo y los choques: dos inscripciones al último cupo
        // esperan en fila y la segunda ya la encuentra llena (RF09).
        $id = (int) DbContext::run(function () use ($estudianteId, $seccionId, $via, $actorId) {
            $seccion = DB::table('sections')->where('id', $seccionId)->lockForUpdate()->first();
            if (DB::table('enrollments')->where('student_id', $estudianteId)->where('period_id', $seccion->period_id)
                ->where('subject_id', $seccion->subject_id)->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['seccion' => __('El estudiante ya está inscrito en esta materia en el período.')]);
            }
            $this->exigirPlanDeCarrera($estudianteId, (int) $seccion->subject_id);
            $this->exigirPrerrequisitos($estudianteId, (int) $seccion->subject_id);
            $this->validarCupoYChoque($estudianteId, $seccionId);

            $id = DB::table('enrollments')->insertGetId(['student_id' => $estudianteId, 'section_id' => $seccion->id, 'period_id' => $seccion->period_id,
                'subject_id' => $seccion->subject_id, 'status' => 'active', 'enrolled_via' => $via, 'enrolled_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('enrollment_changes')->insert(['enrollment_id' => $id, 'change_type' => 'enrolled', 'actor_id' => $actorId, 'occurred_at' => now()]);

            return $id;
        }, 'enrollment.created');

        MatriculaCambiada::dispatch($id, 'inscrita', (string) Str::ulid());

        return $id;
    }

    /**
     * La asignatura es del plan de estudios de la carrera activa del estudiante (CP-RF09-14). Antes cualquier vía
     * aceptaba cualquier asignatura y se inscribió a una estudiante de Administración en materias de Ingeniería
     * (QA del rol Administrador, 27/10/2026, figuras 7 a 9).
     */
    private function exigirPlanDeCarrera(int $estudianteId, int $asignaturaId): void
    {
        $enSuPlan = DB::table('student_careers')->join('career_subjects', 'career_subjects.study_plan_id', '=', 'student_careers.study_plan_id')
            ->where('student_careers.student_id', $estudianteId)->where('student_careers.status', 'active')
            ->where('career_subjects.subject_id', $asignaturaId)->exists();
        if (! $enSuPlan) {
            throw ValidationException::withMessages(['seccion' => __('La asignatura no pertenece al plan de estudios de la carrera del estudiante.')]);
        }
    }

    /**
     * Prerrequisitos aprobados (ADR-052): cada uno con un resultado `passed` congelado en `enrollment_results`, que
     * solo existe para períodos cerrados (PeriodoService::cerrar()). Vale igual para todas las vías y roles; una
     * asignatura sin prerrequisitos declarados no bloquea a nadie, tampoco al estudiante sin histórico.
     */
    private function exigirPrerrequisitos(int $estudianteId, int $asignaturaId): void
    {
        $aprobadas = DB::table('enrollment_results')->join('enrollments', 'enrollments.id', '=', 'enrollment_results.enrollment_id')
            ->where('enrollments.student_id', $estudianteId)->where('enrollment_results.result', 'passed')->select('enrollments.subject_id');

        $faltan = DB::table('subject_prerequisites')->join('subjects', 'subjects.id', '=', 'subject_prerequisites.prerequisite_id')
            ->where('subject_prerequisites.subject_id', $asignaturaId)->whereNotIn('subject_prerequisites.prerequisite_id', $aprobadas)
            ->orderBy('subjects.code')->get(['subjects.code', 'subjects.name'])
            ->map(fn (object $a) => "{$a->code} · {$a->name}");

        if ($faltan->isNotEmpty()) {
            throw ValidationException::withMessages(['seccion' => trans_choice('Le falta aprobar :lista.|Le faltan por aprobar: :lista.', $faltan->count(), ['lista' => $faltan->implode(', ')])]);
        }
    }

    /**
     * Fecha que rige para el departamento en el período, sin la prórroga personal: la fijada (o, sin fila, la de
     * por defecto) o la prórroga del Jefe si es posterior.
     *
     * @return array{fecha: Carbon, origen: string}
     */
    private function reglaDepartamento(int $periodoId, ?int $departamentoId): array
    {
        $fila = $departamentoId === null ? null
            : DB::table('enrollment_deadlines')->where('period_id', $periodoId)->where('department_id', $departamentoId)->first(['deadline', 'extended_until']);
        $plazo = $fila === null
            ? ['fecha' => $this->plazoPorDefecto($periodoId), 'origen' => 'defecto']
            : ['fecha' => Carbon::parse($fila->deadline)->endOfDay(), 'origen' => 'departamento'];
        if ($fila?->extended_until !== null && Carbon::parse($fila->extended_until)->endOfDay()->gt($plazo['fecha'])) {
            $plazo = ['fecha' => Carbon::parse($fila->extended_until)->endOfDay(), 'origen' => 'prorroga-departamento'];
        }

        return $plazo;
    }

    /**
     * Inicio del período + system_settings.enrollment_deadline_weeks_default semanas (2 si falta), hasta el final del día.
     */
    private function plazoPorDefecto(int $periodoId): Carbon
    {
        $inicio = DB::table('academic_periods')->where('id', $periodoId)->value('start_date') ?? abort(404);
        $semanas = json_decode((string) DB::table('system_settings')->where('key', 'enrollment_deadline_weeks_default')->value('value'), true);

        return Carbon::parse($inicio)->addWeeks(is_numeric($semanas) ? (int) $semanas : self::SEMANAS_PLAZO)->endOfDay();
    }

    /**
     * Departamentos sobre los que el usuario actúa con el permiso: null = todos (G); Jefe, los que dirige (D);
     * Coordinador, los de las carreras que coordina (C).
     *
     * @return array<int, int>|null
     */
    private function departamentosDelAmbito(User $actor, string $permiso): ?array
    {
        return match ($this->alcance->exigir($actor, $permiso)) {
            'G' => null,
            'D' => $this->alcance->departamentos($actor),
            'C' => DB::table('careers')->whereIn('id', $this->alcance->carreras($actor))->distinct()->pluck('department_id')->map(fn ($id) => (int) $id)->all(),
            default => [],
        };
    }

    private function exigirDepartamento(User $actor, string $permiso, int $departamentoId): void
    {
        $departamentos = $this->departamentosDelAmbito($actor, $permiso);
        if ($departamentos !== null && ! in_array($departamentoId, $departamentos, true)) {
            throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        }
        DB::table('departments')->where('id', $departamentoId)->exists() || abort(404);
    }

    private function periodoEnCurso(string $campo): int
    {
        return PeriodoService::activoId() ?? throw ValidationException::withMessages([$campo => __('No hay un período en curso.')]);
    }

    /**
     * Claves activas y vigentes del período (sin vencer).
     */
    private function clavesVigentes(int $periodoId): Builder
    {
        return DB::table('enrollment_keys')->where('period_id', $periodoId)->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()));
    }

    /**
     * Grupos abiertos de la asignatura en el período, con cupo, choque con el horario del estudiante, horario y docente.
     *
     * @return Collection<int, object>
     */
    private function seccionesAbiertas(User $estudiante, int $asignaturaId, int $periodoId): Collection
    {
        return DB::table('sections')->where('period_id', $periodoId)->where('subject_id', $asignaturaId)->where('status', 'open')
            ->select('id', 'code', 'max_capacity', DB::raw("(select count(*) from enrollments e where e.section_id = sections.id and e.status = 'active') as inscritos"),
                DB::raw("(select users.first_name || ' ' || users.last_name from teaching_assignments t join users on users.id = t.teacher_id where t.section_id = sections.id and t.is_responsible = true and t.ended_at is null limit 1) as docente"))
            ->orderBy('code')->get()
            ->each(function (object $s) use ($estudiante): void {
                $s->llena = (int) $s->inscritos >= (int) $s->max_capacity;
                $s->choque = $this->horarios->chocaEstudiante($estudiante->id, (int) $s->id);
                $s->horario = DB::table('section_schedules')->leftJoin('classrooms', 'classrooms.id', '=', 'section_schedules.classroom_id')
                    ->where('section_id', $s->id)->orderBy('weekday')->get(['weekday', 'start_time', 'end_time', 'classrooms.code as aula']);
            });
    }

    /**
     * Desactiva la clave activa de la asignatura en el período y guarda la nueva (ya con hash). Va dentro de DbContext::run().
     */
    private function guardarClave(int $asignaturaId, int $periodoId, string $hash, ?string $vence): void
    {
        DB::table('enrollment_keys')->where('subject_id', $asignaturaId)->where('period_id', $periodoId)->where('is_active', true)->update(['is_active' => false, 'updated_at' => now()]);
        DB::table('enrollment_keys')->insert(['subject_id' => $asignaturaId, 'period_id' => $periodoId, 'key_hash' => $hash,
            'valid_until' => $vence ? $vence.' 23:59:59' : null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function matriculaPropia(User $estudiante, int $matriculaId): object
    {
        return DB::table('enrollments')->where('id', $matriculaId)->where('student_id', $estudiante->id)->where('status', 'active')
            ->where('period_id', PeriodoService::activoId())->first() ?? abort(404);
    }

    private function validarCupoYChoque(int $estudianteId, int $seccionId, ?int $ignorarSeccion = null): void
    {
        $seccion = DB::table('sections')->where('id', $seccionId)->first();
        if ($seccion->status !== 'open') {
            throw ValidationException::withMessages(['seccion' => __('La sección no está abierta.')]);
        }
        $inscritos = DB::table('enrollments')->where('section_id', $seccionId)->where('status', 'active')->count();
        if ($inscritos >= (int) $seccion->max_capacity) {
            throw ValidationException::withMessages(['seccion' => __('La sección está llena.')]);
        }
        if ($choque = $this->horarios->chocaEstudiante($estudianteId, $seccionId, $ignorarSeccion)) {
            throw ValidationException::withMessages(['seccion' => __('Choca con el horario de :seccion.', ['seccion' => $choque])]);
        }
    }
}
