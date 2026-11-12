<?php

namespace App\Modules\Evaluaciones\Services;

use App\Models\User;
use App\Support\Alcance;
use App\Support\DbContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Equipos de una tarea grupal (RF15, CP-RF15-05). Los arma el docente con estudiantes de su sección; la entrega
 * de un integrante cuenta para todo el equipo y su nota se copia a cada uno, con ajuste individual (decidido por
 * el usuario el 31/10/2026).
 *
 * Una entrega grupal guarda `team_id` y deja `enrollment_id` vacío (CHECK `submissions_owner`): por eso, para saber
 * si una matrícula entregó, se usa `deMatricula()` o `deMatriculaColumna()`, nunca `enrollment_id` solo.
 */
class EquipoService
{
    public function __construct(private Alcance $alcance) {}

    /**
     * Condición sobre `submissions`: la entrega es de la matrícula, propia o de un equipo al que pertenece.
     */
    public static function deMatricula(Builder $consulta, int $matricula, string $tabla = 'submissions'): Builder
    {
        return $consulta->where(fn (Builder $q) => $q->where("{$tabla}.enrollment_id", $matricula)
            ->orWhereIn("{$tabla}.team_id", fn (Builder $t) => $t->select('team_id')->from('team_members')->where('enrollment_id', $matricula)));
    }

    /**
     * Igual que `deMatricula()`, con la matrícula como columna de la consulta exterior (p. ej. `e.id`).
     */
    public static function deMatriculaColumna(Builder $consulta, string $columna, string $tabla = 's'): Builder
    {
        return $consulta->where(fn (Builder $q) => $q->whereColumn("{$tabla}.enrollment_id", $columna)
            ->orWhereIn("{$tabla}.team_id", fn (Builder $t) => $t->select('tm.team_id')->from('team_members as tm')->whereColumn('tm.enrollment_id', $columna)));
    }

    /**
     * El equipo de la matrícula en esa tarea, con sus integrantes, o null si aún no tiene.
     */
    public function equipoDe(int $tareaId, int $matricula): ?object
    {
        $equipo = DB::table('teams')->join('team_members as tm', 'tm.team_id', '=', 'teams.id')
            ->where('teams.assessment_id', $tareaId)->where('tm.enrollment_id', $matricula)->first(['teams.id', 'teams.name']);
        if ($equipo !== null) {
            $equipo->integrantes = $this->integrantes([(int) $equipo->id])->get((int) $equipo->id, collect());
        }

        return $equipo;
    }

    /**
     * Matrículas activas de los integrantes del equipo: a quienes se copia la nota.
     *
     * @return Collection<int, int>
     */
    public function matriculasDe(int $equipoId): Collection
    {
        return DB::table('team_members as tm')->join('enrollments as e', 'e.id', '=', 'tm.enrollment_id')
            ->where('tm.team_id', $equipoId)->where('e.status', 'active')->pluck('e.id')->map(fn ($id) => (int) $id);
    }

    /**
     * Equipos de la tarea con sus integrantes y si ya entregaron, y los matriculados que aún no tienen equipo.
     *
     * @return array{equipos: Collection<int, \stdClass>, sinEquipo: Collection<int, \stdClass>}
     */
    public function equipos(User $docente, int $seccionId, int $tareaId): array
    {
        $this->exigir($docente, $seccionId, $tareaId);
        $equipos = DB::table('teams')->where('assessment_id', $tareaId)->orderBy('name')->get(['id', 'name']);
        $integrantes = $this->integrantes($equipos->pluck('id')->all());
        $entregaron = DB::table('submissions')->where('assessment_id', $tareaId)->whereNotNull('team_id')->where('status', 'submitted')->pluck('team_id')->flip();
        $equipos->each(function (object $equipo) use ($integrantes, $entregaron) {
            $equipo->integrantes = $integrantes->get((int) $equipo->id, collect());
            $equipo->entrego = $entregaron->has($equipo->id);
        });

        $sinEquipo = DB::table('enrollments as e')->join('users as u', 'u.id', '=', 'e.student_id')->leftJoin('student_profiles as p', 'p.user_id', '=', 'u.id')
            ->where('e.section_id', $seccionId)->where('e.status', 'active')
            ->whereNotIn('e.id', DB::table('team_members as tm')->join('teams', 'teams.id', '=', 'tm.team_id')->where('teams.assessment_id', $tareaId)->select('tm.enrollment_id'))
            ->orderBy('u.last_name')->get(['e.id as matricula', 'u.first_name', 'u.last_name', 'p.student_code']);

        return ['equipos' => $equipos, 'sinEquipo' => $sinEquipo];
    }

    /**
     * Crea un equipo con estudiantes de la sección que todavía no tienen equipo en esta tarea.
     *
     * @param  array<int, int>  $matriculas
     */
    public function crear(User $docente, int $seccionId, int $tareaId, string $nombre, array $matriculas): int
    {
        $this->exigir($docente, $seccionId, $tareaId);
        $nombre = trim($nombre);
        $matriculas = array_values(array_unique(array_map('intval', $matriculas)));
        if ($nombre === '' || mb_strlen($nombre) > 100) {
            throw ValidationException::withMessages(['nombreEquipo' => __('Escriba un nombre de hasta 100 caracteres.')]);
        }
        if ($matriculas === []) {
            throw ValidationException::withMessages(['integrantes' => __('Elija al menos un integrante.')]);
        }
        $validas = DB::table('enrollments')->where('section_id', $seccionId)->where('status', 'active')->whereIn('id', $matriculas)->count();
        if ($validas !== count($matriculas)) {
            throw ValidationException::withMessages(['integrantes' => __('Solo pueden integrar el equipo estudiantes matriculados en esta sección.')]);
        }
        $yaTienen = DB::table('team_members as tm')->join('teams', 'teams.id', '=', 'tm.team_id')->where('teams.assessment_id', $tareaId)->whereIn('tm.enrollment_id', $matriculas)->exists();
        if ($yaTienen) {
            throw ValidationException::withMessages(['integrantes' => __('Un estudiante no puede estar en dos equipos de la misma tarea.')]);
        }

        return (int) DbContext::run(function () use ($tareaId, $nombre, $matriculas) {
            $id = DB::table('teams')->insertGetId(['assessment_id' => $tareaId, 'name' => $nombre, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('team_members')->insert(array_map(fn (int $m) => ['team_id' => $id, 'enrollment_id' => $m], $matriculas));

            return $id;
        }, 'team.created');
    }

    /**
     * Elimina un equipo que todavía no entregó: sus integrantes vuelven a quedar sin equipo.
     */
    public function eliminar(User $docente, int $seccionId, int $tareaId, int $equipoId): void
    {
        $this->exigir($docente, $seccionId, $tareaId);
        DB::table('teams')->where('id', $equipoId)->where('assessment_id', $tareaId)->exists() || abort(404);
        if (DB::table('submissions')->where('team_id', $equipoId)->exists()) {
            throw ValidationException::withMessages(['equipo' => __('El equipo ya entregó: no se puede eliminar.')]);
        }

        DbContext::run(fn () => DB::table('teams')->where('id', $equipoId)->delete(), 'team.deleted');
    }

    /**
     * El docente gestiona tareas en la sección y la tarea es grupal y de esa sección.
     */
    private function exigir(User $docente, int $seccionId, int $tareaId): void
    {
        abort_unless($this->alcance->puedeEnSeccion($docente, 'tareas.gestionar', $seccionId), 403);
        $grupal = DB::table('assessments as a')->join('assignment_settings as s', 's.assessment_id', '=', 'a.id')
            ->where('a.id', $tareaId)->where('a.section_id', $seccionId)->value('s.is_group');
        abort_if($grupal === null, 404);
        if (! $grupal) {
            throw ValidationException::withMessages(['equipo' => __('La tarea no es grupal.')]);
        }
    }

    /**
     * @param  array<int, mixed>  $equipos
     * @return Collection<int|string, Collection<int, \stdClass>>
     */
    private function integrantes(array $equipos): Collection
    {
        return DB::table('team_members as tm')->join('enrollments as e', 'e.id', '=', 'tm.enrollment_id')->join('users as u', 'u.id', '=', 'e.student_id')
            ->leftJoin('student_profiles as p', 'p.user_id', '=', 'u.id')->whereIn('tm.team_id', $equipos)->orderBy('u.last_name')
            ->get(['tm.team_id', 'e.id as matricula', 'u.first_name', 'u.last_name', 'p.student_code'])
            ->groupBy(fn (object $i) => (int) $i->team_id);
    }
}
