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

    public static function nombreTipo(string $tipo, ?string $assessmentType = null): string
    {
        if ($tipo === 'submission') {
            return $assessmentType === 'quiz' ? __('Resolución del cuestionario') : __('Entrega de la tarea');
        }

        return self::TIPOS[$tipo] ?? $tipo;
    }

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
     * @return array{datosActividad: object, prorrogas: Collection<int, \stdClass>, estudiantes: Collection<int, \stdClass>, tipos: array<string, string>}
     */
    public function deActividad(User $docente, int $seccionId, string $ulid): array
    {
        $actividad = $this->actividad($seccionId, $ulid);
        $tipos = [];
        foreach (array_keys(self::TIPOS) as $tipo) {
            if ($this->puede($docente, $seccionId, $actividad, $tipo)) {
                $tipos[$tipo] = self::nombreTipo($tipo, $actividad->assessment_type);
            }
        }
        abort_if($tipos === [], 403);

        $seudonimos = $this->seudonimos($actividad);
        $orden = array_flip(array_keys($seudonimos));
        $nombre = fn (object $f) => $seudonimos[(int) $f->matricula] ?? $f->last_name.', '.$f->first_name;

        return [
            'datosActividad' => $actividad,
            'prorrogas' => DB::table('assessment_extensions as x')->join('enrollments as e', 'e.id', '=', 'x.enrollment_id')->join('users as u', 'u.id', '=', 'e.student_id')
                ->where('x.assessment_id', $actividad->id)->orderByDesc('x.until')->get(['x.id', 'x.kind', 'x.until', 'x.reason', 'e.id as matricula', 'u.first_name', 'u.last_name'])
                ->each(fn (object $p) => $p->nombre = $nombre($p)),
            'estudiantes' => DB::table('enrollments as e')->join('users as u', 'u.id', '=', 'e.student_id')->where('e.section_id', $seccionId)->where('e.status', 'active')
                ->orderBy('u.last_name')->orderBy('u.first_name')->get(['e.id', 'e.id as matricula', 'u.first_name', 'u.last_name'])
                ->each(fn (object $e) => $e->nombre = $nombre($e))
                // Primero los nombres y después los seudónimos en su orden, como en Entregas.
                ->sortBy(fn (object $e) => isset($orden[(int) $e->id]) ? 1 + $orden[(int) $e->id] : 0)->values(),
            'tipos' => $tipos,
        ];
    }

    /**
     * Seudónimos de quienes ya entregaron mientras la calificación es anónima y las notas no se publican (ADR-006):
     * los mismos «Estudiante N» de Entregas, para que el selector de prórrogas no revele quién entregó qué.
     *
     * @return array<int, string> matrícula => seudónimo
     */
    private function seudonimos(object $actividad): array
    {
        $anonima = DB::table('assessments as a')->leftJoin('grade_items as g', 'g.id', '=', 'a.grade_item_id')->where('a.id', $actividad->id)
            ->where('a.is_anonymous', true)->where(fn ($q) => $q->whereNull('g.is_published')->orWhere('g.is_published', false))->exists();
        if (! $anonima) {
            return [];
        }

        return DB::table('submissions')->where('assessment_id', $actividad->id)->where('status', 'submitted')->orderBy('submitted_at')->get(['enrollment_id'])
            ->values()->mapWithKeys(fn (object $s, int $i) => $s->enrollment_id === null ? [] : [(int) $s->enrollment_id => __('Estudiante :n', ['n' => $i + 1])])->all();
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
        $permiso = match ($tipo) {
            'submission' => $actividad->assessment_type === 'quiz' ? 'cuestionarios.gestionar' : 'tareas.gestionar',
            'review' => 'calificaciones.revision.resolver',
            default => null,
        };

        return $permiso !== null && $this->alcance->puedeEnSeccion($docente, $permiso, $seccionId);
    }

    private function actividad(int $seccionId, string $ulid): object
    {
        return DB::table('assessments')->where('section_id', $seccionId)->where('ulid', $ulid)->whereIn('assessment_type', ['assignment', 'quiz'])
            ->first(['id', 'ulid', 'title', 'assessment_type']) ?? abort(404);
    }
}
