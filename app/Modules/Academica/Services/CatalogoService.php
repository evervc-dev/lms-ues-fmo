<?php

namespace App\Modules\Academica\Services;

use App\Models\User;
use App\Support\Alcance;
use App\Support\Busqueda;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Catálogo (RF07, ADR-034): asignaturas con prerrequisitos, y la estructura institucional
 * (departamentos, carreras, planes y aulas). Lo mantiene el Administrador.
 * Los prerrequisitos se exigen al matricular (ADR-052, en MatriculaService).
 */
class CatalogoService
{
    public function __construct(private Alcance $alcance) {}

    /**
     * @param  array{buscar?: string, departamento?: string, creditos?: int|string|null}  $filtros
     */
    public function asignaturas(User $actor, array $filtros): LengthAwarePaginator
    {
        $this->alcance->exigir($actor, 'asignaturas.gestionar');
        $buscar = trim($filtros['buscar'] ?? '');
        $patron = Busqueda::fragmentoSinTildes($buscar);

        return DB::table('subjects')->join('departments', 'departments.id', '=', 'subjects.department_id')
            ->select('subjects.*', 'departments.code as departamento',
                DB::raw("(select count(*) from sections s join academic_periods p on p.id = s.period_id where s.subject_id = subjects.id and p.status = 'active') as secciones"))
            ->when($buscar !== '', fn ($q) => $q->where(fn ($q) => $q->whereRaw("lower(subjects.code) like ? escape '\\'", [$patron])
                ->orWhereRaw(Busqueda::sinTildes('subjects.name')." like ? escape '\\'", [$patron])))
            ->when($filtros['departamento'] ?? null, fn ($q, $d) => $q->where('subjects.department_id', $d))
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
     * @param  array{codigo: string, nombre: string, creditos: int, departamento: int, prerrequisitos?: array<int, int>}  $datos
     */
    public function guardarAsignatura(User $actor, ?int $id, array $datos): int
    {
        $this->alcance->exigir($actor, 'asignaturas.gestionar');
        $codigo = mb_strtoupper(trim($datos['codigo']));

        if (DB::table('subjects')->whereRaw('upper(code) = ?', [$codigo])->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
            throw ValidationException::withMessages(['codigo' => __('Ya existe una asignatura con ese código.')]);
        }
        $prerrequisitos = array_values(array_unique(array_map('intval', $datos['prerrequisitos'] ?? [])));
        if ($id !== null && $this->creaCiclo($id, $prerrequisitos)) {
            throw ValidationException::withMessages(['prerrequisitos' => __('Ese prerrequisito crearía un ciclo (A requiere B y B requiere A).')]);
        }

        // QA sesión 4 (H-08 parcial): cada fila se escribe solo si cambia, para que la auditoría no se duplique. Antes
        // el alta hacía insert y luego update, y cada guardado borraba y reinsertaba todos los prerrequisitos.
        return DbContext::run(function () use ($id, $codigo, $datos, $prerrequisitos) {
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

            return $id;
        }, 'subject.saved');
    }

    /**
     * @return array{departamentos: Collection<int, object>, carreras: Collection<int, object>, aulas: Collection<int, object>}
     */
    public function estructura(User $actor): array
    {
        $this->alcance->exigir($actor, 'asignaturas.gestionar');

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
        $this->alcance->exigir($actor, 'asignaturas.gestionar');
        DB::table('subjects')->where('id', $id)->exists() || abort(404);

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
     */
    public function cambiarEstadoAsignatura(User $actor, int $id, bool $activa): void
    {
        $this->alcance->exigir($actor, 'asignaturas.gestionar');
        DB::table('subjects')->where('id', $id)->exists() || abort(404);

        DbContext::run(fn () => DB::table('subjects')->where('id', $id)->update(['is_active' => $activa, 'updated_at' => now()]),
            $activa ? 'subject.activated' : 'subject.deactivated');
    }

    /**
     * Descripción de la materia que ve el estudiante al inscribirse (RF29, ADR-050), en texto plano.
     * La edita el Administrador (asignaturas.gestionar, G) o el docente (contenidos.gestionar, S) que tiene un grupo
     * vigente de la asignatura en el período activo. Si varios docentes la dictan, la comparten.
     *
     * @throws AuthorizationException
     */
    public function editarDescripcion(User $actor, int $asignaturaId, ?string $descripcion): void
    {
        $esAdministrador = $this->alcance->de($actor, 'asignaturas.gestionar') === 'G';
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
        $this->alcance->exigir($actor, 'asignaturas.gestionar');
        $codigo = mb_strtoupper(trim($codigo));
        if (DB::table('classrooms')->whereRaw('upper(code) = ?', [$codigo])->exists()) {
            throw ValidationException::withMessages(['aulaCodigo' => __('Ya existe un aula con ese código.')]);
        }
        DbContext::run(fn () => DB::table('classrooms')->insert(['code' => $codigo, 'capacity' => $capacidad, 'created_at' => now(), 'updated_at' => now()]), 'classroom.created');
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
