<?php

namespace App\Modules\Evaluaciones\Services;

use App\Models\User;
use App\Modules\Evaluaciones\Events\ProrrogaConcedida;
use App\Support\Alcance;
use App\Support\DbContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Prórrogas por estudiante y actividad (RF15, RF35, ADR-040): el docente amplía la entrega de una tarea (entrega a
 * tiempo, sin penalización, hasta la fecha) o el plazo para pedir revisión de la nota.
 */
class ProrrogaService
{
    public const TIPOS = ['submission' => 'Entrega de la tarea', 'review' => 'Plazo para pedir revisión'];

    /** Permiso que exige cada tipo de prórroga (alcance S: las secciones del docente). */
    private const PERMISOS = ['submission' => 'tareas.gestionar', 'review' => 'calificaciones.revision.resolver'];

    public function __construct(private Alcance $alcance) {}

    /**
     * Fecha de la prórroga del estudiante, vigente o no; null si no tiene.
     */
    public function hasta(int $matriculaId, int $actividadId, string $tipo): ?Carbon
    {
        $hasta = DB::table('assessment_extensions')->where('enrollment_id', $matriculaId)->where('assessment_id', $actividadId)->where('kind', $tipo)->value('until');

        return $hasta === null ? null : Carbon::parse($hasta);
    }

    /**
     * Fecha de la prórroga solo si sigue vigente.
     */
    public function vigente(int $matriculaId, int $actividadId, string $tipo): ?Carbon
    {
        $hasta = $this->hasta($matriculaId, $actividadId, $tipo);

        return $hasta?->isFuture() ? $hasta : null;
    }

    /**
     * Prórrogas de la actividad, los estudiantes de la sección y los tipos que el docente puede dar.
     *
     * @return array{datosActividad: object, prorrogas: Collection<int, object>, estudiantes: Collection<int, object>, tipos: array<string, string>}
     */
    public function deActividad(User $docente, int $seccionId, string $ulid): array
    {
        $actividad = $this->actividad($seccionId, $ulid);
        $tipos = array_filter(self::TIPOS, fn (string $tipo) => $this->puede($docente, $seccionId, $actividad, $tipo), ARRAY_FILTER_USE_KEY);
        abort_if($tipos === [], 403);

        return [
            'datosActividad' => $actividad,
            'prorrogas' => DB::table('assessment_extensions as x')->join('enrollments as e', 'e.id', '=', 'x.enrollment_id')->join('users as u', 'u.id', '=', 'e.student_id')
                ->where('x.assessment_id', $actividad->id)->orderByDesc('x.until')->get(['x.id', 'x.kind', 'x.until', 'x.reason', 'u.first_name', 'u.last_name']),
            'estudiantes' => DB::table('enrollments as e')->join('users as u', 'u.id', '=', 'e.student_id')->where('e.section_id', $seccionId)->where('e.status', 'active')
                ->orderBy('u.last_name')->orderBy('u.first_name')->get(['e.id', 'u.first_name', 'u.last_name']),
            'tipos' => $tipos,
        ];
    }

    /**
     * Da o actualiza la prórroga del estudiante (una por tipo y actividad).
     *
     * @throws ValidationException
     */
    public function conceder(User $docente, int $seccionId, string $ulid, int $matriculaId, string $tipo, string $hasta, string $motivo): void
    {
        $actividad = $this->actividad($seccionId, $ulid);
        if (! array_key_exists($tipo, self::TIPOS)) {
            abort(422);
        }
        abort_unless($this->puede($docente, $seccionId, $actividad, $tipo), 403);
        DB::table('enrollments')->where('id', $matriculaId)->where('section_id', $seccionId)->where('status', 'active')->exists() || abort(404);

        $fecha = rescue(fn () => Carbon::parse($hasta), null, false);
        $errores = array_filter([
            'hasta' => $fecha === null || ! $fecha->isFuture() ? __('La prórroga debe terminar en una fecha futura.') : null,
            'motivo' => mb_strlen(trim($motivo)) < 5 ? __('Explique el motivo de la prórroga (al menos 5 caracteres).') : null,
        ]);
        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        $id = DbContext::run(function () use ($seccionId, $actividad, $matriculaId, $tipo, $fecha, $motivo, $docente) {
            DB::table('assessment_extensions')->updateOrInsert(['assessment_id' => $actividad->id, 'enrollment_id' => $matriculaId, 'kind' => $tipo],
                ['section_id' => $seccionId, 'until' => $fecha, 'reason' => trim($motivo), 'granted_by' => $docente->id, 'updated_at' => now(), 'created_at' => now()]);

            return (int) DB::table('assessment_extensions')->where('assessment_id', $actividad->id)->where('enrollment_id', $matriculaId)->where('kind', $tipo)->value('id');
        }, 'extension.granted');

        ProrrogaConcedida::dispatch($id);
    }

    private function puede(User $docente, int $seccionId, object $actividad, string $tipo): bool
    {
        // La prórroga de entrega solo existe para tareas; los cuestionarios tienen su propio límite de tiempo.
        if ($tipo === 'submission' && $actividad->assessment_type !== 'assignment') {
            return false;
        }

        return $this->alcance->puedeEnSeccion($docente, self::PERMISOS[$tipo], $seccionId);
    }

    private function actividad(int $seccionId, string $ulid): object
    {
        return DB::table('assessments')->where('section_id', $seccionId)->where('ulid', $ulid)->whereIn('assessment_type', ['assignment', 'quiz'])
            ->first(['id', 'ulid', 'title', 'assessment_type']) ?? abort(404);
    }
}
