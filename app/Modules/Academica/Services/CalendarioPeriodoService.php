<?php

namespace App\Modules\Academica\Services;

use App\Models\User;
use App\Modules\Academica\Jobs\BorrarEventoDePeriodo;
use App\Modules\Academica\Jobs\SincronizarEventoDePeriodo;
use App\Modules\Asistencia\Services\CalendarConexionService;
use App\Support\Alcance;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;

/**
 * Calendario de evaluaciones del período (RF06, CP-RF06-04, ADR-068) por ámbito.
 *
 * Con `periodos.calendario.gestionar`: el Administrador (G) fija las fechas **globales** del período, el Jefe (D)
 * las de su departamento y el Coordinador (C) las de su carrera. Cada fecha se sincroniza con el Google Calendar
 * **de quien la gestiona** (ADR-068, opción A), con el OAuth de ADR-018, invitando solo a los involucrados de ese
 * ámbito. `google_sync_refs` guarda el id del evento y el estado de cada fecha.
 *
 * Todos ven las fechas globales del período, porque les aplican; solo las toca quien las creó en su ámbito.
 */
class CalendarioPeriodoService
{
    public const ENTIDAD = 'period_calendar_event';

    public const PERMISO = 'periodos.calendario.gestionar';

    public function __construct(private Alcance $alcance, private CalendarConexionService $conexion) {}

    /**
     * Fechas del período visibles para el usuario con su estado en Google Calendar (`sync_status`: pending, synced,
     * failed, o null si nunca se sincronizó) y si puede modificarlas (`editable`).
     *
     * @return Collection<int, stdClass>
     */
    public function eventos(User $usuario, int $periodoId): Collection
    {
        $alcance = $this->alcance->exigir($usuario, self::PERMISO);

        $consulta = DB::table('period_calendar_events as e')
            ->leftJoin('google_sync_refs as g', fn ($j) => $j->on('g.entity_id', '=', 'e.id')->where('g.entity_type', self::ENTIDAD)->where('g.provider', 'google_calendar'))
            ->leftJoin('departments as d', 'd.id', '=', 'e.department_id')
            ->leftJoin('careers as c', 'c.id', '=', 'e.career_id')
            ->where('e.period_id', $periodoId);

        // El Jefe y el Coordinador ven además las fechas globales del período: les aplican aunque no las manejen.
        if ($alcance !== 'G') {
            $consulta->where(fn (Builder $q) => $q
                ->where(fn (Builder $g) => $g->whereNull('e.department_id')->whereNull('e.career_id'))
                ->orWhereIn('e.department_id', $this->alcance->departamentos($usuario))
                ->orWhereIn('e.career_id', $this->alcance->carreras($usuario)));
        }

        return $consulta->orderBy('e.starts_at')
            ->get(['e.id', 'e.title', 'e.starts_at', 'e.ends_at', 'e.department_id', 'e.career_id', 'd.name as departamento', 'c.name as carrera', 'g.sync_status', 'g.last_error'])
            ->map(function (stdClass $evento) use ($usuario): stdClass {
                $evento->ambito = match (true) {
                    $evento->department_id !== null => __('Departamento: :n', ['n' => $evento->departamento]),
                    $evento->career_id !== null => __('Carrera: :n', ['n' => $evento->carrera]),
                    default => __('Todo el período'),
                };
                $evento->editable = $this->editable($usuario, $evento);

                return $evento;
            });
    }

    /**
     * Ámbitos en los que el usuario puede crear fechas, para el selector de la pantalla.
     *
     * @return list<array{valor: string, etiqueta: string}>
     */
    public function ambitos(User $usuario): array
    {
        $alcance = $this->alcance->exigir($usuario, self::PERMISO);
        $opciones = $alcance === 'G' ? [['valor' => 'global', 'etiqueta' => __('Todo el período')]] : [];

        if (in_array($alcance, ['G', 'D'], true)) {
            foreach (DB::table('departments')->whereIn('id', $this->alcance->departamentos($usuario))->orderBy('name')->get(['id', 'name']) as $d) {
                $opciones[] = ['valor' => 'departamento:'.$d->id, 'etiqueta' => __('Departamento: :n', ['n' => $d->name])];
            }
        }
        foreach (DB::table('careers')->whereIn('id', $this->alcance->carreras($usuario))->orderBy('name')->get(['id', 'name']) as $c) {
            $opciones[] = ['valor' => 'carrera:'.$c->id, 'etiqueta' => __('Carrera: :n', ['n' => $c->name])];
        }

        return $opciones;
    }

    /**
     * Agrega una fecha. `$ambito` viene del selector: `global`, `departamento:<id>` o `carrera:<id>`.
     *
     * @throws ValidationException si la fecha queda fuera del período, el fin no es posterior al inicio, el período
     *                             está cerrado o el ámbito no es suyo.
     */
    public function agregar(User $usuario, int $periodoId, string $titulo, string $inicio, ?string $fin, string $ambito = 'global'): int
    {
        $this->alcance->exigir($usuario, self::PERMISO);
        [$departamentoId, $carreraId] = $this->ambito($usuario, $ambito);
        $periodo = $this->periodoAbierto($periodoId);
        $desde = Carbon::parse($inicio);
        $hasta = $fin === null ? null : Carbon::parse($fin);

        if ($desde->toDateString() < $periodo->start_date || $desde->toDateString() > $periodo->end_date) {
            throw ValidationException::withMessages(['inicio' => __('La fecha debe estar dentro del período (del :ini al :fin).', [
                'ini' => Carbon::parse($periodo->start_date)->format('d/m/Y'), 'fin' => Carbon::parse($periodo->end_date)->format('d/m/Y')])]);
        }
        if ($hasta !== null && $hasta->lessThanOrEqualTo($desde)) {
            throw ValidationException::withMessages(['fin' => __('El fin debe ser posterior al inicio.')]);
        }

        return DbContext::run(fn () => DB::table('period_calendar_events')->insertGetId([
            'period_id' => $periodoId, 'department_id' => $departamentoId, 'career_id' => $carreraId,
            'title' => $titulo, 'starts_at' => $desde, 'ends_at' => $hasta, 'created_at' => now(), 'updated_at' => now(),
        ]), 'period_calendar_event.created');
    }

    /**
     * Quita la fecha. Si ya estaba en Google Calendar, el evento se borra en la cola «google» y Google avisa a los
     * invitados. Solo quien gestiona ese ámbito puede quitarla.
     */
    public function eliminar(User $usuario, int $eventoId): void
    {
        $this->alcance->exigir($usuario, self::PERMISO);
        $evento = DB::table('period_calendar_events')->where('id', $eventoId)->first() ?? abort(404);
        if (! $this->editable($usuario, $evento)) {
            throw new AuthorizationException(__('Esa fecha es de otro ámbito: solo la modifica quien lo gestiona.'));
        }
        $this->periodoAbierto((int) $evento->period_id);
        $externo = DB::table('google_sync_refs')->where('entity_type', self::ENTIDAD)->where('entity_id', $eventoId)->where('provider', 'google_calendar')->value('external_id');

        DbContext::run(function () use ($eventoId) {
            DB::table('google_sync_refs')->where('entity_type', self::ENTIDAD)->where('entity_id', $eventoId)->delete();
            DB::table('period_calendar_events')->where('id', $eventoId)->delete();
        }, 'period_calendar_event.deleted');

        if ($externo !== null) {
            BorrarEventoDePeriodo::dispatch((string) $externo, $usuario->id)->afterCommit();
        }
    }

    /**
     * Envía a Google Calendar las fechas **de su propio ámbito**, en la cola «google»: el evento queda en el
     * calendario de quien sincroniza (ADR-068) e invita solo a los involucrados de ese ámbito. Las ya
     * sincronizadas se actualizan en vez de duplicarse.
     *
     * @return int Cantidad de fechas enviadas.
     *
     * @throws ValidationException si Calendar no está habilitado, no lo conectó o no hay fechas suyas.
     */
    public function sincronizar(User $usuario, int $periodoId): int
    {
        $this->alcance->exigir($usuario, self::PERMISO);
        $this->periodoAbierto($periodoId);
        if (! $this->conexion->habilitada()) {
            throw ValidationException::withMessages(['calendario' => __('La conexión con Google Calendar no está habilitada en la plataforma.')]);
        }
        if (! $this->conexion->conectado($usuario)) {
            throw ValidationException::withMessages(['calendario' => __('Conecte su Google Calendar en Mi perfil › Google Calendar para sincronizar.')]);
        }

        $ids = $this->eventos($usuario, $periodoId)->filter(fn (stdClass $e) => $e->editable)->pluck('id')->map(fn ($id) => (int) $id);
        if ($ids->isEmpty()) {
            throw ValidationException::withMessages(['calendario' => __('Agregue al menos una fecha de su ámbito al calendario de evaluaciones.')]);
        }

        DbContext::run(fn () => $ids->each(fn (int $id) => DB::table('google_sync_refs')->updateOrInsert(
            ['entity_type' => self::ENTIDAD, 'entity_id' => $id, 'provider' => 'google_calendar'],
            ['sync_status' => 'pending', 'last_error' => null, 'updated_at' => now(), 'created_at' => now()],
        )), 'period_calendar.sync_requested');
        $ids->each(fn (int $id) => SincronizarEventoDePeriodo::dispatch($id, $usuario->id)->afterCommit());

        return $ids->count();
    }

    /**
     * Correos institucionales de los involucrados en el ámbito de la fecha: los docentes con una asignación vigente
     * en esas secciones y los estudiantes con una matrícula activa. Solo cuentas activas. Sin departamento ni
     * carrera, todo el período.
     *
     * @return list<string>
     */
    public static function involucrados(int $periodoId, ?int $departamentoId = null, ?int $carreraId = null): array
    {
        $secciones = DB::table('sections')->where('sections.period_id', $periodoId);
        if ($departamentoId !== null) {
            $secciones->join('subjects', 'subjects.id', '=', 'sections.subject_id')->where('subjects.department_id', $departamentoId);
        } elseif ($carreraId !== null) {
            $secciones->whereIn('sections.subject_id', DB::table('career_subjects')
                ->join('study_plans', 'study_plans.id', '=', 'career_subjects.study_plan_id')
                ->where('study_plans.career_id', $carreraId)->select('career_subjects.subject_id'));
        }
        $secciones->select('sections.id');

        $docentes = DB::table('teaching_assignments')->whereIn('section_id', $secciones)->whereNull('ended_at')->select('teacher_id as user_id');
        $estudiantes = DB::table('enrollments')->whereIn('section_id', $secciones)->where('status', 'active')->select('student_id as user_id');

        return DB::table('users')->where('is_active', true)->whereIn('id', $docentes->union($estudiantes))
            ->orderBy('institutional_email')->pluck('institutional_email')->map(fn ($c) => (string) $c)->values()->all();
    }

    /**
     * Período sobre el que trabaja la pantalla cuando la ruta no trae uno: el que está en curso y, si no hay
     * ninguno, el siguiente planificado.
     */
    public function periodoVigente(): ?int
    {
        $id = DB::table('academic_periods')->where('status', 'active')->value('id')
            ?? DB::table('academic_periods')->where('status', 'planned')->orderBy('start_date')->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Cabecera del período para la pantalla. Exige el permiso: la fija cualquiera de los tres ámbitos.
     */
    public function periodo(User $usuario, int $periodoId): object
    {
        $this->alcance->exigir($usuario, self::PERMISO);

        return DB::table('academic_periods')->where('id', $periodoId)->first(['id', 'name', 'status', 'start_date', 'end_date']) ?? abort(404);
    }

    /**
     * ¿Es esta fecha de su ámbito? El Administrador (G) maneja las globales; el Jefe, las de los departamentos que
     * dirige; el Coordinador, las de las carreras que coordina. Nadie toca el ámbito de otro.
     */
    private function editable(User $usuario, stdClass $evento): bool
    {
        $global = $evento->department_id === null && $evento->career_id === null;

        return match ($this->alcance->de($usuario, self::PERMISO)) {
            'G' => $global,
            'D' => $evento->department_id !== null && in_array((int) $evento->department_id, $this->alcance->departamentos($usuario), true),
            'C' => $evento->career_id !== null && in_array((int) $evento->career_id, $this->alcance->carreras($usuario), true),
            default => false,
        };
    }

    /**
     * Traduce el ámbito del selector a las dos columnas, comprobando que sea suyo.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function ambito(User $usuario, string $ambito): array
    {
        if (! in_array($ambito, array_column($this->ambitos($usuario), 'valor'), true)) {
            throw ValidationException::withMessages(['ambito' => __('Elija un ámbito que usted gestione.')]);
        }
        [$tipo, $id] = array_pad(explode(':', $ambito, 2), 2, null);

        return match ($tipo) {
            'departamento' => [(int) $id, null],
            'carrera' => [null, (int) $id],
            default => [null, null],
        };
    }

    private function periodoAbierto(int $periodoId): object
    {
        $periodo = DB::table('academic_periods')->where('id', $periodoId)->first() ?? abort(404);
        if ($periodo->status === 'closed') {
            throw ValidationException::withMessages(['calendario' => __('El período está cerrado: su calendario ya no se modifica.')]);
        }

        return $periodo;
    }
}
