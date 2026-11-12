<?php

namespace App\Modules\Asistencia\Services;

use App\Models\User;
use App\Modules\Asistencia\Events\AlertaAsistenciaAbierta;
use App\Modules\Asistencia\Events\ClaseCancelada;
use App\Modules\Asistencia\Jobs\EvaluarAlertasAsistencia;
use App\Support\Alcance;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Asistencia (RF19, RF22, RF26, RF28, RF32; ADR-028; Flujo Código temporal).
 * Porcentaje: presente o justificada = 1, tardanza = 0,5, ausente = 0 (parámetros provisionales).
 * La marcación por QR (cámara, GPS y token de dispositivo) está en AsistenciaQrService (RF20); el registro del dispositivo es de RF27.
 */
class AsistenciaService
{
    public const ESTADOS = ['present' => 'Presente', 'late' => 'Tarde', 'absent' => 'Ausente', 'justified' => 'Justificada'];

    public const METODOS = ['qr' => 'QR', 'code' => 'Código', 'manual' => 'Manual', 'import' => 'Importado', 'meet' => 'Meet'];

    /** Alertas de inasistencia (RF22): su ajuste en system_settings y el valor por omisión. */
    public const ALERTAS = ['attendance_85' => 85, 'attendance_78' => 78, 'attendance_75' => 75];

    /** Las que avisan al estudiante: preventiva y crítica. Bajo el mínimo solo se marca «en riesgo» (Flujo Alerta de inasistencias). */
    public const ALERTAS_CON_AVISO = ['attendance_85', 'attendance_78'];

    /** Vértices de un área por polígono (RF26): con menos no hay superficie; con más, el dibujo ya no es un aula ni un edificio. */
    public const VERTICES_MIN = 3;

    public const VERTICES_MAX = 50;

    public function __construct(private Alcance $alcance, private GeocercaService $geocerca) {}

    /**
     * Expresión SQL del porcentaje de asistencia de un conjunto de registros. Los de un día cancelado no cuentan
     * (RF32): sin ningún registro que cuente, el porcentaje es NULL.
     */
    public static function porcentajeSql(string $tabla = 'r'): string
    {
        $cuenta = self::cuentaSql($tabla);

        return "round(100.0 * sum(case when not ({$cuenta}) then 0 when {$tabla}.status in ('present', 'justified') then 1 when {$tabla}.status = 'late' then 0.5 else 0 end)"
            ." / nullif(sum(case when {$cuenta} then 1 else 0 end), 0), 1)";
    }

    /**
     * Condición SQL: el registro cuenta para el porcentaje porque su sesión no cae en un día cancelado (RF32), sea
     * por una cancelación aprobada de la sección, de una carrera de su asignatura o de toda la facultad.
     */
    public static function cuentaSql(string $tabla = 'r'): string
    {
        return 'not exists (select 1 from attendance_sessions cs join class_cancellations cc on cc.date = cs.session_date'
            ." where cs.id = {$tabla}.session_id and cc.status = 'approved' and cc.kind = 'cancellation'"
            ." and (cc.scope = 'faculty' or (cc.scope = 'section' and cc.section_id = {$tabla}.section_id)"
            ." or (cc.scope = 'career' and cc.career_id in (select sp.career_id from sections sx join career_subjects csx on csx.subject_id = sx.subject_id"
            ." join study_plans sp on sp.id = csx.study_plan_id where sx.id = {$tabla}.section_id))))";
    }

    /**
     * Sesiones de la sección con sus conteos (docente y coordinador).
     *
     * @return Collection<int, object>
     */
    public function sesiones(User $usuario, int $seccionId): Collection
    {
        $this->exigirEnSeccion($usuario, 'asistencia.ver', $seccionId);

        return DB::table('attendance_sessions as s')->where('s.section_id', $seccionId)->orderByDesc('s.session_date')->orderByDesc('s.opened_at')
            ->select('s.id', 's.ulid', 's.session_date', 's.modality', 's.status', 's.opened_at',
                DB::raw("(select count(*) from attendance_records r where r.session_id = s.id and r.status = 'present') as presentes"),
                DB::raw("(select count(*) from attendance_records r where r.session_id = s.id and r.status = 'late') as tardes"),
                DB::raw("(select count(*) from attendance_records r where r.session_id = s.id and r.status = 'absent') as ausentes"),
                DB::raw("(select count(*) from attendance_records r where r.session_id = s.id and r.status = 'justified') as justificadas"))
            ->limit(100)->get();
    }

    /**
     * Historial del estudiante en una sección: registros, porcentaje por cómputo y justificaciones.
     *
     * @return array{registros: Collection<int, object>, porcentaje: ?float, porComputo: Collection<int, object>}
     */
    public function historial(User $estudiante, int $seccionId): array
    {
        $matricula = $this->matricula($estudiante, $seccionId) ?? throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        $registros = DB::table('attendance_records as r')->join('attendance_sessions as s', 's.id', '=', 'r.session_id')
            ->leftJoin('attendance_justifications as j', 'j.record_id', '=', 'r.id')
            ->where('r.enrollment_id', $matricula)->orderByDesc('s.session_date')
            ->get(['r.id', 'r.status', 'r.method', 'r.recorded_at', 's.session_date', 's.modality', 'j.status as justificacion', DB::raw(self::cuentaSql().' as cuenta')]);

        return [
            'registros' => $registros,
            'porcentaje' => $this->porcentaje($registros),
            'porComputo' => DB::table('attendance_records as r')->join('attendance_sessions as s', 's.id', '=', 'r.session_id')
                ->join('grading_components as c', 'c.id', '=', 's.grading_component_id')->where('r.enrollment_id', $matricula)
                ->groupBy('c.id', 'c.name', 'c.number')->orderBy('c.number')
                ->get(['c.name', DB::raw(self::porcentajeSql().' as porcentaje'), DB::raw('sum(case when '.self::cuentaSql().' then 1 else 0 end) as clases')]),
        ];
    }

    /**
     * Mi asistencia (RF22): porcentaje por materia del período con su nivel de alerta.
     *
     * @return Collection<int, object>
     */
    public function miAsistencia(User $estudiante): Collection
    {
        $this->alcance->exigir($estudiante, 'asistencia.marcar');

        return DB::table('enrollments as e')->join('sections', 'sections.id', '=', 'e.section_id')->join('subjects', 'subjects.id', '=', 'sections.subject_id')
            ->join('academic_periods as p', 'p.id', '=', 'e.period_id')->where('p.status', 'active')
            ->where('e.student_id', $estudiante->id)->where('e.status', 'active')->orderBy('subjects.code')
            ->get(['e.id', 'sections.id as seccion_id', 'sections.ulid', 'sections.code as grupo', 'subjects.code', 'subjects.name',
                DB::raw('(select '.self::porcentajeSql().' from attendance_records r where r.enrollment_id = e.id) as porcentaje'),
                DB::raw('(select count(*) from attendance_records r where r.enrollment_id = e.id and '.self::cuentaSql().') as clases'),
                DB::raw("(select count(*) from attendance_records r where r.enrollment_id = e.id and r.status = 'absent' and ".self::cuentaSql().') as faltas')])
            ->each(fn ($m) => $m->nivel = $m->porcentaje === null ? 'sin_datos' : ($m->porcentaje < 75 ? 'critico' : ($m->porcentaje < 78 ? 'riesgo' : ($m->porcentaje < 85 ? 'aviso' : 'bien'))));
    }

    public function abrir(User $docente, int $seccionId, int $minutos, string $modalidad = 'in_person'): string
    {
        $this->exigirEnSeccion($docente, 'asistencia.sesion.abrir', $seccionId);
        if ($minutos < 5 || $minutos > 15) {
            throw ValidationException::withMessages(['minutos' => __('La sesión dura entre 5 y 15 minutos.')]);
        }
        if (DB::table('attendance_sessions')->where('section_id', $seccionId)->where('status', 'open')->exists()) {
            throw ValidationException::withMessages(['minutos' => __('Ya hay una sesión de asistencia abierta en esta sección.')]);
        }
        $hoy = now()->toDateString();
        // RF32: un cambio de modalidad aprobado para hoy fija el método. Virtual se registra con el reporte de Meet
        // (RF21, ADR-003), no con QR; presencial abre el QR con el área, aunque el docente eligiera otra cosa.
        $aprobada = $this->modalidadAprobada($seccionId, $hoy);
        if ($aprobada === 'virtual') {
            throw ValidationException::withMessages(['modalidad' => __('Hoy la clase es virtual por un cambio de modalidad aprobado: la asistencia se registra con el reporte de Meet, no con QR.')]);
        }
        $modalidad = $aprobada ?? $modalidad;
        // Acotada al CHECK de la tabla (5 a 30 s, ADR-028), que en SQLite no existe.
        $rotacion = min(30, max(5, (int) (json_decode((string) DB::table('system_settings')->where('key', 'qr_rotation_seconds_default')->value('value'), true) ?? 10)));

        return DbContext::run(function () use ($docente, $seccionId, $minutos, $modalidad, $hoy, $rotacion) {
            $ulid = (string) Str::ulid();
            DB::table('attendance_sessions')->insert([
                'ulid' => $ulid, 'section_id' => $seccionId,
                'grading_component_id' => DB::table('grading_components')->where('section_id', $seccionId)->where('starts_on', '<=', $hoy)->where('ends_on', '>=', $hoy)->value('id'),
                'area_id' => $modalidad === 'in_person' ? DB::table('attendance_areas')->where('is_active', true)->value('id') : null,
                'session_date' => $hoy, 'modality' => $modalidad, 'status' => 'open', 'opened_at' => now(), 'qr_validity_minutes' => $minutos,
                // Sin tardanza con QR (ADR-046): «tarde» empieza cuando la sesión ya cerró.
                'qr_rotation_seconds' => $rotacion, 'late_after_minutes' => $minutos, 'opened_by' => $docente->id, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $ulid;
        }, 'attendance_session.opened');
    }

    /**
     * Sesión: cada estudiante activo con su registro, conteos y rechazos.
     *
     * @return array{sesion: object, estudiantes: Collection<int, object>, rechazos: Collection<int, object>, conteo: array<string, int>, segundos: int}
     */
    public function sesion(User $docente, int $seccionId, string $ulid): array
    {
        $this->exigirEnSeccion($docente, 'asistencia.sesion.abrir', $seccionId);
        $sesion = DB::table('attendance_sessions')->where('section_id', $seccionId)->where('ulid', $ulid)->first() ?? abort(404);
        if ($sesion->status === 'open' && now()->gt(Carbon::parse($sesion->opened_at)->addMinutes((int) $sesion->qr_validity_minutes))) {
            $this->cerrar($docente, $seccionId, $ulid);
            $sesion = DB::table('attendance_sessions')->where('id', $sesion->id)->first();
        }

        $estudiantes = DB::table('enrollments as e')->join('users as u', 'u.id', '=', 'e.student_id')->leftJoin('student_profiles as p', 'p.user_id', '=', 'u.id')
            ->leftJoin('attendance_records as r', fn ($j) => $j->on('r.enrollment_id', '=', 'e.id')->where('r.session_id', $sesion->id))
            ->where('e.section_id', $seccionId)->where('e.status', 'active')->orderBy('u.last_name')
            ->get(['e.id as matricula', 'u.first_name', 'u.last_name', 'p.student_code', 'r.id as registro', 'r.status', 'r.method', 'r.recorded_at', 'r.distance_m', 'r.correction_reason']);

        return [
            'sesion' => $sesion,
            'estudiantes' => $estudiantes,
            'rechazos' => DB::table('attendance_scan_attempts as a')->join('users as u', 'u.id', '=', 'a.student_id')->where('a.session_id', $sesion->id)
                ->where('a.result', '!=', 'ok')->orderByDesc('a.occurred_at')->get(['a.result', 'a.distance_m', 'a.occurred_at', 'u.first_name', 'u.last_name']),
            'conteo' => $estudiantes->countBy(fn ($e) => $e->status ?? 'sin_registro')->all(),
            'segundos' => $sesion->status === 'open' ? max(0, (int) now()->diffInSeconds(Carbon::parse($sesion->opened_at)->addMinutes((int) $sesion->qr_validity_minutes), false)) : 0,
        ];
    }

    /**
     * Marca o corrige a mano. Si ya había registro, es una corrección y exige motivo (queda auditada).
     */
    public function marcar(User $docente, int $seccionId, string $ulid, int $matriculaId, string $estado, ?string $motivo = null): void
    {
        $this->exigirEnSeccion($docente, 'asistencia.corregir', $seccionId);
        if (! array_key_exists($estado, self::ESTADOS)) {
            throw ValidationException::withMessages(['estado' => __('El estado de asistencia no es válido.')]);
        }
        $sesion = DB::table('attendance_sessions')->where('section_id', $seccionId)->where('ulid', $ulid)->first() ?? abort(404);
        DB::table('enrollments')->where('id', $matriculaId)->where('section_id', $seccionId)->where('status', 'active')->exists() || abort(404);
        $existente = DB::table('attendance_records')->where('session_id', $sesion->id)->where('enrollment_id', $matriculaId)->first();
        if ($existente && blank($motivo)) {
            throw ValidationException::withMessages(['motivo' => __('Corregir un registro exige un motivo.')]);
        }

        DbContext::run(function () use ($docente, $sesion, $seccionId, $matriculaId, $estado, $motivo, $existente) {
            if ($existente) {
                DB::table('attendance_records')->where('id', $existente->id)->update(['status' => $estado, 'corrected_by' => $docente->id, 'correction_reason' => $motivo, 'updated_at' => now()]);
            } else {
                DB::table('attendance_records')->insert(['session_id' => $sesion->id, 'section_id' => $seccionId, 'enrollment_id' => $matriculaId, 'status' => $estado,
                    'method' => 'manual', 'recorded_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            }
        }, $existente ? 'attendance.corrected' : 'attendance.manual', $motivo);

        EvaluarAlertasAsistencia::dispatch($seccionId);
    }

    /**
     * Código temporal para un estudiante (RF28): 6 dígitos, solo para él, un uso, 30 s. Se guarda con hash.
     */
    public function generarCodigo(User $docente, int $seccionId, string $ulid, int $matriculaId): string
    {
        $this->exigirEnSeccion($docente, 'asistencia.codigo-temporal.generar', $seccionId);
        $sesion = DB::table('attendance_sessions')->where('section_id', $seccionId)->where('ulid', $ulid)->first() ?? abort(404);
        if ($sesion->status !== 'open') {
            throw ValidationException::withMessages(['codigo' => __('La sesión ya cerró.')]);
        }
        if (DB::table('attendance_records')->where('session_id', $sesion->id)->where('enrollment_id', $matriculaId)->exists()) {
            throw ValidationException::withMessages(['codigo' => __('El estudiante ya tiene registro en esta sesión.')]);
        }
        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::table('temporary_attendance_codes')->where('session_id', $sesion->id)->where('enrollment_id', $matriculaId)->whereNull('used_at')->delete();
        DB::table('temporary_attendance_codes')->insert(['session_id' => $sesion->id, 'enrollment_id' => $matriculaId, 'code_hash' => hash('sha256', $codigo),
            'expires_at' => now()->addSeconds(30), 'issued_by' => $docente->id, 'created_at' => now()]);

        return $codigo;
    }

    /**
     * El estudiante ingresa el código temporal que le dictó el docente.
     */
    public function usarCodigo(User $estudiante, string $codigo): string
    {
        $this->alcance->exigir($estudiante, 'asistencia.marcar');
        $hash = hash('sha256', trim($codigo));
        $fila = DB::table('temporary_attendance_codes as t')->join('enrollments as e', 'e.id', '=', 't.enrollment_id')
            ->join('attendance_sessions as s', 's.id', '=', 't.session_id')->join('sections', 'sections.id', '=', 's.section_id')->join('subjects', 'subjects.id', '=', 'sections.subject_id')
            ->where('e.student_id', $estudiante->id)->where('t.code_hash', $hash)->whereNull('t.used_at')
            ->first(['t.id', 't.expires_at', 't.session_id', 't.enrollment_id', 's.section_id', 's.status', 's.opened_at', 's.late_after_minutes', 'subjects.code']);

        if (! $fila || now()->gt(Carbon::parse($fila->expires_at)) || $fila->status !== 'open') {
            throw ValidationException::withMessages(['codigo' => __('Código inválido o vencido. Pida otro a su docente.')]);
        }
        $tarde = now()->gt(Carbon::parse($fila->opened_at)->addMinutes((int) $fila->late_after_minutes));

        DbContext::run(function () use ($fila, $tarde) {
            $usado = DB::table('temporary_attendance_codes')->where('id', $fila->id)->whereNull('used_at')->update(['used_at' => now()]);
            if ($usado === 0) {
                throw ValidationException::withMessages(['codigo' => __('Código inválido o vencido. Pida otro a su docente.')]);
            }
            DB::table('attendance_records')->insert(['session_id' => $fila->session_id, 'section_id' => $fila->section_id, 'enrollment_id' => $fila->enrollment_id,
                'status' => $tarde ? 'late' : 'present', 'method' => 'code', 'recorded_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }, 'attendance.code_used');

        return $fila->code;
    }

    /**
     * Cierra la sesión: quienes no marcaron quedan ausentes.
     */
    public function cerrar(User $docente, int $seccionId, string $ulid): void
    {
        $this->exigirEnSeccion($docente, 'asistencia.sesion.abrir', $seccionId);
        $sesion = DB::table('attendance_sessions')->where('section_id', $seccionId)->where('ulid', $ulid)->where('status', 'open')->first();
        if (! $sesion) {
            return;
        }
        DbContext::run(function () use ($sesion, $seccionId) {
            $faltan = DB::table('enrollments')->where('section_id', $seccionId)->where('status', 'active')
                ->whereNotIn('id', DB::table('attendance_records')->where('session_id', $sesion->id)->select('enrollment_id'))->pluck('id');
            foreach ($faltan as $matricula) {
                DB::table('attendance_records')->insert(['session_id' => $sesion->id, 'section_id' => $seccionId, 'enrollment_id' => $matricula, 'status' => 'absent',
                    'method' => 'manual', 'recorded_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('attendance_sessions')->where('id', $sesion->id)->update(['status' => 'closed', 'closed_at' => now(), 'updated_at' => now()]);
        }, 'attendance_session.closed');

        EvaluarAlertasAsistencia::dispatch($seccionId);
    }

    public function justificar(User $estudiante, int $registroId, string $motivo, ?UploadedFile $documento): void
    {
        $this->alcance->exigir($estudiante, 'asistencia.marcar');
        $registro = DB::table('attendance_records as r')->join('enrollments as e', 'e.id', '=', 'r.enrollment_id')
            ->where('r.id', $registroId)->where('e.student_id', $estudiante->id)->first(['r.id', 'r.status']) ?? abort(404);
        if ($registro->status !== 'absent') {
            throw ValidationException::withMessages(['motivoJustificacion' => __('Solo se justifican las inasistencias.')]);
        }
        if (DB::table('attendance_justifications')->where('record_id', $registroId)->whereIn('status', ['pending', 'accepted'])->exists()) {
            throw ValidationException::withMessages(['motivoJustificacion' => __('Esa inasistencia ya tiene una justificación.')]);
        }
        $ruta = $documento?->store('justificaciones');
        DbContext::run(fn () => DB::table('attendance_justifications')->insert(['record_id' => $registroId, 'reason' => $motivo, 'file_path' => $ruta ?: null,
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]), 'attendance.justification_submitted');
    }

    /**
     * Justificaciones pendientes de la sección (docente).
     *
     * @return Collection<int, object>
     */
    public function justificacionesPendientes(User $docente, int $seccionId): Collection
    {
        $this->exigirEnSeccion($docente, 'asistencia.corregir', $seccionId);

        return DB::table('attendance_justifications as j')->join('attendance_records as r', 'r.id', '=', 'j.record_id')->join('attendance_sessions as s', 's.id', '=', 'r.session_id')
            ->join('enrollments as e', 'e.id', '=', 'r.enrollment_id')->join('users as u', 'u.id', '=', 'e.student_id')
            ->where('r.section_id', $seccionId)->where('j.status', 'pending')->orderBy('j.created_at')
            ->get(['j.id', 'j.reason', 'j.file_path', 's.session_date', 'u.first_name', 'u.last_name']);
    }

    public function revisarJustificacion(User $docente, int $seccionId, int $justificacionId, bool $aceptar): void
    {
        $this->exigirEnSeccion($docente, 'asistencia.corregir', $seccionId);
        $j = DB::table('attendance_justifications as j')->join('attendance_records as r', 'r.id', '=', 'j.record_id')
            ->where('j.id', $justificacionId)->where('r.section_id', $seccionId)->where('j.status', 'pending')->first(['j.id', 'j.record_id', 'j.reason']) ?? abort(404);

        DbContext::run(function () use ($docente, $j, $aceptar) {
            DB::table('attendance_justifications')->where('id', $j->id)->update(['status' => $aceptar ? 'accepted' : 'rejected', 'reviewed_by' => $docente->id, 'reviewed_at' => now(), 'updated_at' => now()]);
            if ($aceptar) {
                DB::table('attendance_records')->where('id', $j->record_id)->update(['status' => 'justified', 'corrected_by' => $docente->id, 'correction_reason' => __('Justificación aceptada: :m', ['m' => $j->reason]), 'updated_at' => now()]);
            }
        }, $aceptar ? 'attendance.justification_accepted' : 'attendance.justification_rejected');

        if ($aceptar) {
            EvaluarAlertasAsistencia::dispatch($seccionId);
        }
    }

    /**
     * Modalidad que fija para la sección un cambio de modalidad aprobado en esa fecha (RF32), o null si no hay. Vale el
     * de la sección, el de una carrera de su asignatura o el de toda la facultad; si hay varios, manda el más cercano.
     */
    public function modalidadAprobada(int $seccionId, string $fecha): ?string
    {
        $carreras = DB::table('sections')->join('career_subjects as cs', 'cs.subject_id', '=', 'sections.subject_id')
            ->join('study_plans as sp', 'sp.id', '=', 'cs.study_plan_id')->where('sections.id', $seccionId)->pluck('sp.career_id');

        $modalidad = DB::table('class_cancellations')->where('status', 'approved')->where('kind', 'modality_change')->whereDate('date', $fecha)
            ->where(fn ($q) => $q->where('scope', 'faculty')
                ->orWhere(fn ($s) => $s->where('scope', 'section')->where('section_id', $seccionId))
                ->orWhere(fn ($c) => $c->where('scope', 'career')->whereIn('career_id', $carreras)))
            ->orderByRaw("case scope when 'section' then 0 when 'career' then 1 else 2 end")->value('new_modality');

        return $modalidad === null ? null : (string) $modalidad;
    }

    /**
     * El docente pide cancelar una clase o cambiar su modalidad (RF32); la aprueba el Coordinador.
     */
    public function solicitarCancelacion(User $docente, int $seccionId, string $fecha, string $tipo, string $motivo, ?string $modalidad = null): void
    {
        $this->exigirEnSeccion($docente, 'clases.solicitar-cancelacion', $seccionId);
        DbContext::run(fn () => DB::table('class_cancellations')->insert(['scope' => 'section', 'section_id' => $seccionId, 'date' => $fecha, 'kind' => $tipo,
            'new_modality' => $tipo === 'modality_change' ? $modalidad : null, 'reason' => $motivo, 'status' => 'requested', 'requested_by' => $docente->id,
            'created_at' => now(), 'updated_at' => now()]), 'class_cancellation.requested', $motivo);
    }

    /**
     * Solicitudes y cancelaciones (Coordinador: su carrera; Administrador: todas).
     *
     * @return Collection<int, object>
     */
    public function cancelaciones(User $actor, string $estado = 'requested'): Collection
    {
        $alcance = $this->alcance->exigir($actor, 'clases.cancelar');
        $consulta = DB::table('class_cancellations as c')->leftJoin('sections', 'sections.id', '=', 'c.section_id')->leftJoin('subjects', 'subjects.id', '=', 'sections.subject_id')
            ->leftJoin('careers', 'careers.id', '=', 'c.career_id')->join('users as u', 'u.id', '=', 'c.requested_by')
            ->when($estado === 'requested', fn ($q) => $q->where('c.status', 'requested'), fn ($q) => $q->where('c.status', '!=', 'requested'))
            ->orderBy('c.date')
            ->select('c.*', 'subjects.code', 'sections.code as grupo', 'careers.code as carrera', DB::raw("u.first_name || ' ' || u.last_name as solicitante"));

        if ($alcance === 'C') {
            $secciones = $this->alcance->secciones(DB::table('sections')->select('sections.id'), $actor, 'clases.cancelar');
            $consulta->where(fn ($q) => $q->whereIn('c.section_id', $secciones)->orWhereIn('c.career_id', $this->alcance->carreras($actor)));
        }

        return $consulta->get();
    }

    /**
     * El Coordinador o el Administrador aprueba o rechaza la solicitud. Al aprobarla se avisa a los afectados (RF32).
     */
    public function resolverCancelacion(User $actor, int $id, bool $aprobar): void
    {
        $solicitud = $this->cancelaciones($actor)->firstWhere('id', $id) ?? abort(404);
        DbContext::run(fn () => DB::table('class_cancellations')->where('id', $id)->update(['status' => $aprobar ? 'approved' : 'rejected', 'approved_by' => $actor->id, 'updated_at' => now()]),
            $aprobar ? 'class_cancellation.approved' : 'class_cancellation.rejected', $solicitud->reason);

        if ($aprobar) {
            ClaseCancelada::dispatch($id);
        }
    }

    /**
     * Cancelación directa de la facultad (Administrador) o de una carrera (Coordinador).
     */
    public function cancelarDirecto(User $actor, string $fecha, string $motivo): void
    {
        $alcance = $this->alcance->exigir($actor, 'clases.cancelar');
        $carrera = $alcance === 'C' ? ($this->alcance->carreras($actor)[0] ?? throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'))) : null;
        $id = DbContext::run(fn () => DB::table('class_cancellations')->insertGetId(['scope' => $carrera ? 'career' : 'faculty', 'career_id' => $carrera, 'date' => $fecha, 'kind' => 'cancellation',
            'reason' => $motivo, 'status' => 'approved', 'requested_by' => $actor->id, 'approved_by' => $actor->id, 'created_at' => now(), 'updated_at' => now()]), 'class_cancellation.created', $motivo);

        ClaseCancelada::dispatch((int) $id);
    }

    /**
     * Abre y cierra las alertas de inasistencia de las matrículas activas de la sección, por cómputo (RF22). Lo
     * llama el job EvaluarAlertasAsistencia; es idempotente: con los mismos registros no cambia nada.
     *
     * Una alerta se abre cuando el porcentaje del cómputo baja del umbral y se cierra cuando lo vuelve a superar.
     * El aviso al estudiante sale una sola vez por umbral y cómputo (RNF12): si la alerta se cerró y se vuelve a
     * abrir, ya no avisa. Si de una vez cruza el preventivo y el crítico, sale un solo aviso, el crítico, y el
     * preventivo queda como avisado.
     *
     * @return int Alertas abiertas en esta evaluación.
     */
    public function evaluarAlertas(int $seccionId): int
    {
        $umbrales = self::umbralesDeAlerta();
        $porcentajes = DB::table('attendance_records as r')->join('attendance_sessions as s', 's.id', '=', 'r.session_id')
            ->join('enrollments as e', 'e.id', '=', 'r.enrollment_id')->where('r.section_id', $seccionId)->where('e.status', 'active')
            ->groupBy('r.enrollment_id', 's.grading_component_id')
            ->get(['r.enrollment_id', 's.grading_component_id', DB::raw(self::porcentajeSql().' as porcentaje')]);
        $abiertas = DB::table('academic_alerts')->whereIn('enrollment_id', $porcentajes->pluck('enrollment_id')->unique())
            ->whereIn('alert_type', array_keys($umbrales))->whereNull('closed_at')->get();

        $nuevas = DbContext::system(function () use ($porcentajes, $abiertas, $umbrales) {
            $nuevas = [];
            foreach ($porcentajes as $p) {
                $porcentaje = $p->porcentaje === null ? null : (float) $p->porcentaje;
                $aviso = null;
                foreach ($umbrales as $tipo => $umbral) {
                    $abierta = $abiertas->first(fn ($a) => (int) $a->enrollment_id === (int) $p->enrollment_id && $a->alert_type === $tipo
                        && (int) $a->grading_component_id === (int) $p->grading_component_id);
                    $baja = $porcentaje !== null && $porcentaje < $umbral;

                    if ($baja && ! $abierta) {
                        $avisada = DB::table('academic_alerts')->where('enrollment_id', $p->enrollment_id)->where('alert_type', $tipo)
                            ->where('grading_component_id', $p->grading_component_id)->whereNotNull('notified_at')->exists();
                        $avisar = in_array($tipo, self::ALERTAS_CON_AVISO, true) && ! $avisada;
                        $id = DB::table('academic_alerts')->insertGetId(['enrollment_id' => $p->enrollment_id, 'alert_type' => $tipo,
                            'grading_component_id' => $p->grading_component_id, 'opened_at' => now(), 'notified_at' => $avisar ? now() : null]);
                        if ($avisar) {
                            // Los umbrales van de menos a más grave: el último que se abre es el que se avisa.
                            $aviso = [$id, $porcentaje];
                        }
                    }
                    if (! $baja && $abierta) {
                        DB::table('academic_alerts')->where('id', $abierta->id)->update(['closed_at' => now()]);
                    }
                }
                if ($aviso !== null) {
                    $nuevas[] = $aviso;
                }
            }

            return $nuevas;
        }, 'academic_alert.evaluated');

        foreach ($nuevas as [$id, $porcentaje]) {
            AlertaAsistenciaAbierta::dispatch((int) $id, $porcentaje);
        }

        return count($nuevas);
    }

    /**
     * Umbrales vigentes de las alertas (RF22): preventivo y crítico de `attendance_alert_thresholds` y el mínimo de
     * `min_attendance_percent`, con 85, 78 y 75 si faltan.
     *
     * @return array<string, float>
     */
    public static function umbralesDeAlerta(): array
    {
        $ajuste = fn (string $clave) => json_decode((string) DB::table('system_settings')->where('key', $clave)->value('value'), true);
        [$preventivo, $critico] = array_pad((array) ($ajuste('attendance_alert_thresholds') ?? []), 2, null);

        return [
            'attendance_85' => (float) ($preventivo ?? self::ALERTAS['attendance_85']),
            'attendance_78' => (float) ($critico ?? self::ALERTAS['attendance_78']),
            'attendance_75' => (float) ($ajuste('min_attendance_percent') ?? self::ALERTAS['attendance_75']),
        ];
    }

    /**
     * Secciones del período activo que alcanza una cancelación (RF32): la sección, las de una carrera o todas.
     *
     * @return Collection<int, int>
     */
    public static function seccionesDeCancelacion(object $cancelacion): Collection
    {
        return DB::table('sections')->join('academic_periods as p', 'p.id', '=', 'sections.period_id')->where('p.status', 'active')
            ->when($cancelacion->scope === 'section', fn ($q) => $q->where('sections.id', $cancelacion->section_id))
            ->when($cancelacion->scope === 'career', fn ($q) => $q->whereIn('sections.subject_id', DB::table('career_subjects')
                ->join('study_plans', 'study_plans.id', '=', 'career_subjects.study_plan_id')
                ->where('study_plans.career_id', $cancelacion->career_id)->select('career_subjects.subject_id')))
            ->pluck('sections.id')->map(fn ($id) => (int) $id);
    }

    /**
     * @return Collection<int, object>
     */
    public function areas(User $admin): Collection
    {
        $this->alcance->exigir($admin, 'areas-asistencia.gestionar');

        return DB::table('attendance_areas')->orderBy('name')->get();
    }

    public function guardarArea(User $admin, ?int $id, string $nombre, float $lat, float $lng, int $radio): void
    {
        $this->alcance->exigir($admin, 'areas-asistencia.gestionar');
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || $radio < 10 || $radio > 5000) {
            throw ValidationException::withMessages(['radio' => __('Revise las coordenadas y el radio (entre 10 y 5 000 m).')]);
        }
        $this->escribirArea($id, ['name' => $nombre, 'area_type' => 'circle', 'center_lat' => $lat, 'center_lng' => $lng, 'radius_m' => $radio, 'polygon' => null]);
    }

    /**
     * Área por polígono (RF26): de 3 a 50 vértices [[lat, lng], …] con coordenadas válidas y sin lados que se
     * crucen. Guarda su centroide en center_lat y center_lng para la lista y el mapa; no lleva radio. El margen por
     * la imprecisión del GPS va dentro del dibujo: el polígono no tiene otra columna donde guardarlo.
     *
     * @param  array<int, mixed>  $vertices
     */
    public function guardarPoligono(User $admin, ?int $id, string $nombre, array $vertices): void
    {
        $this->alcance->exigir($admin, 'areas-asistencia.gestionar');
        // Un vértice que no son dos números queda en INF y lo rechaza la validación de rango.
        $vertices = array_values(array_map(fn (mixed $v) => is_array($v) && is_numeric($v[0] ?? null) && is_numeric($v[1] ?? null)
            ? [(float) $v[0], (float) $v[1]] : [INF, INF], $vertices));
        if (count($vertices) > 1 && $vertices[0] === $vertices[count($vertices) - 1]) {
            array_pop($vertices); // Un GeoJSON repite el primer vértice al final para cerrar el anillo.
        }

        if (count($vertices) < self::VERTICES_MIN || count($vertices) > self::VERTICES_MAX) {
            throw ValidationException::withMessages(['vertices' => __('El polígono necesita entre :min y :max vértices; tiene :n.', ['min' => self::VERTICES_MIN, 'max' => self::VERTICES_MAX, 'n' => count($vertices)])]);
        }
        foreach ($vertices as [$lat, $lng]) {
            if (! is_finite($lat) || ! is_finite($lng) || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                throw ValidationException::withMessages(['vertices' => __('Hay un vértice con coordenadas fuera de rango.')]);
            }
        }
        if ($this->geocerca->seCruza($vertices)) {
            throw ValidationException::withMessages(['vertices' => __('Los lados del polígono se cruzan. Dibújelo siguiendo el contorno, sin volver sobre sus pasos.')]);
        }

        [$lat, $lng] = $this->geocerca->centroide($vertices);
        $this->escribirArea($id, ['name' => $nombre, 'area_type' => 'polygon', 'center_lat' => round($lat, 7), 'center_lng' => round($lng, 7), 'radius_m' => null,
            'polygon' => json_encode($vertices)]);
    }

    /**
     * Crea o actualiza el área. Al cambiar de forma se vacían los campos de la otra, para que el CHECK de
     * attendance_areas_shape se cumpla.
     *
     * @param  array<string, mixed>  $fila
     */
    private function escribirArea(?int $id, array $fila): void
    {
        $fila['updated_at'] = now();
        DbContext::run(fn () => $id ? DB::table('attendance_areas')->where('id', $id)->update($fila) : DB::table('attendance_areas')->insert($fila + ['is_active' => true, 'created_at' => now()]), 'attendance_area.saved');
    }

    public function alternarArea(User $admin, int $id): void
    {
        $this->alcance->exigir($admin, 'areas-asistencia.gestionar');
        DbContext::run(fn () => DB::table('attendance_areas')->where('id', $id)->update(['is_active' => DB::raw('not is_active'), 'updated_at' => now()]), 'attendance_area.toggled');
    }

    /**
     * @param  Collection<int, object>  $registros
     */
    private function porcentaje(Collection $registros): ?float
    {
        // Los registros de un día cancelado (RF32) no cuentan.
        $registros = $registros->filter(fn ($r) => (bool) $r->cuenta);
        if ($registros->isEmpty()) {
            return null;
        }
        $puntos = $registros->sum(fn ($r) => match ($r->status) {
            'present', 'justified' => 1, 'late' => 0.5, default => 0
        });

        return round(100 * $puntos / $registros->count(), 1);
    }

    /**
     * Permiso con alcance sobre la sección; sin él, 403 (ADR-026).
     *
     * @throws AuthorizationException
     */
    private function exigirEnSeccion(User $usuario, string $permiso, int $seccionId): void
    {
        if (! $this->alcance->puedeEnSeccion($usuario, $permiso, $seccionId)) {
            throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        }
    }

    private function matricula(User $usuario, int $seccionId): ?int
    {
        $id = DB::table('enrollments')->where('student_id', $usuario->id)->where('section_id', $seccionId)->where('status', 'active')->value('id');

        return $id === null ? null : (int) $id;
    }
}
