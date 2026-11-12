<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Autorización por alcance (ADR-026, docs/04 Roles/Matriz RBAC.md).
 *
 * spatie dice si el usuario tiene el permiso; esta clase dice SOBRE QUÉ datos:
 * G global · D su departamento · C su carrera · S las secciones que imparte ·
 * M las asignaturas donde está matriculado · P solo lo propio.
 * Con varios roles se usa el alcance más amplio que le da el permiso.
 */
class Alcance
{
    private const ORDEN = ['G' => 6, 'D' => 5, 'C' => 4, 'S' => 3, 'M' => 2, 'P' => 1];

    /** @var array<string, array<string, string>>|null */
    private ?array $matriz = null;

    /** @var array<string, mixed> */
    private array $memoria = [];

    /**
     * Exige el permiso y devuelve su alcance. Sin permiso lanza 403.
     *
     * @throws AuthorizationException
     */
    public function exigir(User $usuario, string $permiso): string
    {
        return $this->de($usuario, $permiso) ?? throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
    }

    /**
     * Alcance más amplio que el usuario tiene para el permiso, o null si no lo tiene.
     */
    public function de(User $usuario, string $permiso): ?string
    {
        if (! $usuario->checkPermissionTo($permiso)) {
            return null;
        }

        $roles = $usuario->roles()->whereHas('permissions', fn ($q) => $q->where('name', $permiso))->pluck('name');
        $alcances = $roles->map(fn (string $rol) => $this->letra($this->matriz()[$permiso][$rol] ?? ''))->filter();

        return $alcances->sortByDesc(fn (string $letra) => self::ORDEN[$letra])->first() ?? 'P';
    }

    /**
     * Restringe una consulta a las secciones que el usuario puede ver con el permiso.
     *
     * @throws AuthorizationException
     */
    public function secciones(Builder $consulta, User $usuario, string $permiso, string $columna = 'sections.id'): Builder
    {
        return match ($this->exigir($usuario, $permiso)) {
            'G' => $consulta,
            'D' => $consulta->whereIn($columna, DB::table('sections')->join('subjects', 'subjects.id', '=', 'sections.subject_id')
                ->whereIn('subjects.department_id', $this->departamentos($usuario))->select('sections.id')),
            'C' => $consulta->whereIn($columna, DB::table('sections')->whereIn('subject_id', $this->asignaturasDeCarreras($this->carreras($usuario)))->select('sections.id')),
            'S' => $consulta->whereIn($columna, DB::table('teaching_assignments')->where('teacher_id', $usuario->id)->whereNull('ended_at')->select('section_id')),
            default => $consulta->whereIn($columna, DB::table('enrollments')->where('student_id', $usuario->id)->where('status', 'active')->select('section_id')),
        };
    }

    /**
     * ¿Puede el usuario actuar con el permiso sobre esta sección?
     */
    public function puedeEnSeccion(User $usuario, string $permiso, int $seccionId): bool
    {
        if ($this->de($usuario, $permiso) === null) {
            return false;
        }

        return $this->secciones(DB::table('sections'), $usuario, $permiso)->where('sections.id', $seccionId)->exists();
    }

    /**
     * Restringe una consulta de estudiantes (users.id) a los de su carrera o departamento.
     *
     * @throws AuthorizationException
     */
    public function estudiantes(Builder $consulta, User $usuario, string $permiso, string $columna = 'users.id'): Builder
    {
        return match ($this->exigir($usuario, $permiso)) {
            'G' => $consulta,
            'D' => $consulta->whereIn($columna, DB::table('student_careers')->join('careers', 'careers.id', '=', 'student_careers.career_id')
                ->whereIn('careers.department_id', $this->departamentos($usuario))->select('student_careers.student_id')),
            'C' => $consulta->whereIn($columna, DB::table('student_careers')->whereIn('career_id', $this->carreras($usuario))->select('student_id')),
            'S' => $consulta->whereIn($columna, DB::table('enrollments')->where('status', 'active')
                ->whereIn('section_id', DB::table('teaching_assignments')->where('teacher_id', $usuario->id)->whereNull('ended_at')->select('section_id'))->select('student_id')),
            default => $consulta->where($columna, $usuario->id),
        };
    }

    /**
     * Departamentos que dirige hoy.
     *
     * @return array<int, int>
     */
    public function departamentos(User $usuario): array
    {
        return $this->memoria['dep'.$usuario->id] ??= DB::table('department_heads')->where('user_id', $usuario->id)
            ->whereNull('valid_to')->pluck('department_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Carreras que coordina hoy.
     *
     * @return array<int, int>
     */
    public function carreras(User $usuario): array
    {
        return $this->memoria['car'.$usuario->id] ??= DB::table('career_coordinators')->where('user_id', $usuario->id)
            ->whereNull('valid_to')->pluck('career_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Carreras por las que el usuario puede filtrar lo que ve con el permiso: todas (G), las de los
     * departamentos que dirige (D) o las que coordina (C). Con otro alcance no hay filtro de carrera.
     *
     * @return Collection<int, \stdClass>
     */
    public function carrerasVisibles(User $usuario, string $permiso): Collection
    {
        $alcance = $this->de($usuario, $permiso);
        if (! in_array($alcance, ['G', 'D', 'C'], true)) {
            return collect();
        }

        return DB::table('careers')
            ->when($alcance === 'D', fn ($q) => $q->whereIn('department_id', $this->departamentos($usuario)))
            ->when($alcance === 'C', fn ($q) => $q->whereIn('id', $this->carreras($usuario)))
            ->orderBy('code')->get(['id', 'code', 'name']);
    }

    /**
     * Asignaturas que están en algún plan de estudios de las carreras dadas.
     *
     * @param  array<int, int>  $carreras
     */
    public function asignaturasDeCarreras(array $carreras): Builder
    {
        return DB::table('career_subjects')->join('study_plans', 'study_plans.id', '=', 'career_subjects.study_plan_id')
            ->whereIn('study_plans.career_id', $carreras)->select('career_subjects.subject_id');
    }

    private function letra(string $alcance): ?string
    {
        return preg_match('/[GDCSMP]/', $alcance, $coincidencia) ? $coincidencia[0] : null;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function matriz(): array
    {
        return $this->matriz ??= (require database_path('seeders/data/rbac.php'))['permissions'];
    }
}
