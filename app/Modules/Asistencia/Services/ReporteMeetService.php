<?php

namespace App\Modules\Asistencia\Services;

use App\Models\User;
use App\Modules\Asistencia\Jobs\CrearEventoDeClase;
use App\Modules\Asistencia\Jobs\EvaluarAlertasAsistencia;
use App\Support\Alcance;
use App\Support\DbContext;
use App\Support\HojaCalculo;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Asistencia virtual con el reporte de Meet (RF21, ADR-003, ADR-048; Flujo Asistencia virtual con reporte de Meet).
 *
 * El docente programa la clase, la da en Meet y, al terminar, sube el reporte de asistencia (CSV o Excel). El
 * sistema suma el tiempo de cada estudiante, muestra una vista previa y, al confirmar, registra Presente si llegó
 * al 70 % de la duración programada o Ausente si no. La creación del evento en Google Calendar es de la entrega 2.
 */
class ReporteMeetService
{
    /** Porcentaje de la duración programada para quedar Presente (ADR-003). */
    public const UMBRAL = 70;

    public const DURACION_MIN = 15;

    public const DURACION_MAX = 300;

    public const EXTENSIONES = ['csv', 'txt', 'xlsx'];

    public const ESTADOS = ['scheduled' => 'Programada', 'in_progress' => 'En curso', 'finished' => 'Finalizada'];

    /** Encabezados que se reconocen (normalizados: minúsculas y sin tildes), en español e inglés. */
    public const ALIAS = [
        'correo' => ['email', 'correo', 'correo electronico', 'e-mail'],
        'nombre' => ['first name', 'nombre', 'name', 'nombre completo', 'participante', 'participant'],
        'apellido' => ['last name', 'apellido', 'apellidos'],
        'duracion' => ['duration', 'duracion', 'tiempo', 'tiempo de asistencia', 'attended duration'],
        'entrada' => ['time joined', 'joined', 'joined at', 'first seen', 'hora de entrada', 'hora en que se unio', 'primera vez visto'],
    ];

    private const MESES = ['ene' => 1, 'jan' => 1, 'feb' => 2, 'mar' => 3, 'abr' => 4, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7,
        'ago' => 8, 'aug' => 8, 'sep' => 9, 'set' => 9, 'oct' => 10, 'nov' => 11, 'dic' => 12, 'dec' => 12];

    public function __construct(private Alcance $alcance) {}

    /**
     * Clases virtuales de la sección. Antes de devolverlas, guarda el estado que les toca por la hora.
     *
     * @return Collection<int, object>
     */
    public function clases(User $usuario, int $seccionId): Collection
    {
        $this->exigirEnSeccion($usuario, 'asistencia.ver', $seccionId);
        $this->actualizarEstados(DB::table('video_sessions')->where('section_id', $seccionId)->where('status', '!=', 'finished')->get(['id', 'starts_at', 'duration_minutes', 'status']));

        return DB::table('video_sessions as v')->where('v.section_id', $seccionId)->orderByDesc('v.starts_at')
            ->select('v.id', 'v.topic', 'v.starts_at', 'v.duration_minutes', 'v.status', 'v.meet_url', 'v.recording_url',
                DB::raw('(select m.id from meet_reports m where m.video_session_id = v.id and m.is_current = '.$this->verdadero().' and m.confirmed_at is not null order by m.id desc limit 1) as reporte_id'),
                DB::raw('(select m.id from meet_reports m where m.video_session_id = v.id and m.confirmed_at is null order by m.id desc limit 1) as borrador_id'),
                DB::raw('(select s.ulid from attendance_sessions s where s.video_session_id = v.id order by s.id limit 1) as sesion_ulid'),
                DB::raw("(select count(*) from attendance_records r join attendance_sessions s on s.id = r.session_id where s.video_session_id = v.id and r.status = 'present') as presentes"))
            ->get()
            ->each(function (object $c): void {
                $c->id = (int) $c->id;
                $c->duration_minutes = (int) $c->duration_minutes;
                $c->reporte_id = $c->reporte_id === null ? null : (int) $c->reporte_id;
                $c->borrador_id = $c->borrador_id === null ? null : (int) $c->borrador_id;
                $c->presentes = $c->sesion_ulid === null ? null : (int) $c->presentes;
            });
    }

    /**
     * Programa una clase virtual. `$inicio` llega en hora local con el formato 'Y-m-d H:i'.
     */
    public function programar(User $docente, int $seccionId, string $tema, string $inicio, int $duracion, ?string $meetUrl): int
    {
        $this->exigirEnSeccion($docente, 'videoconferencias.gestionar', $seccionId);
        if ($duracion < self::DURACION_MIN || $duracion > self::DURACION_MAX) {
            throw ValidationException::withMessages(['duracion' => __('La clase dura entre :min y :max minutos.', ['min' => self::DURACION_MIN, 'max' => self::DURACION_MAX])]);
        }
        $comienzo = $this->fechaHora($inicio) ?? throw ValidationException::withMessages(['inicio' => __('Indique la fecha y la hora de la clase.')]);
        $periodo = DB::table('sections')->join('academic_periods as p', 'p.id', '=', 'sections.period_id')->where('sections.id', $seccionId)->first(['p.start_date', 'p.end_date']) ?? abort(404);
        $dia = $comienzo->toDateString();
        if ($dia < Carbon::parse($periodo->start_date)->toDateString() || $dia > Carbon::parse($periodo->end_date)->toDateString()) {
            throw ValidationException::withMessages(['inicio' => __('La fecha de la clase debe estar dentro del período (del :ini al :fin).', [
                'ini' => Carbon::parse($periodo->start_date)->format('d/m/Y'), 'fin' => Carbon::parse($periodo->end_date)->format('d/m/Y')])]);
        }
        $meetUrl = blank($meetUrl) ? null : trim((string) $meetUrl);
        if ($meetUrl !== null && ! preg_match('#^https://meet\.google\.com/\S+$#i', $meetUrl)) {
            throw ValidationException::withMessages(['meetUrl' => __('El enlace debe ser de Google Meet (https://meet.google.com/…).')]);
        }

        $id = (int) DbContext::run(fn () => DB::table('video_sessions')->insertGetId([
            'section_id' => $seccionId, 'topic' => trim($tema), 'starts_at' => $comienzo, 'duration_minutes' => $duracion,
            'status' => $this->estadoPorHora($comienzo, $duracion), 'meet_url' => $meetUrl, 'created_at' => now(), 'updated_at' => now(),
        ]), 'video_session.scheduled');
        // CP-RF21-01: el evento de Calendar con Meet e invitados se crea en la cola «google», sin frenar al docente.
        CrearEventoDeClase::dispatch($id, $docente->id)->afterCommit();

        return $id;
    }

    /**
     * Asocia (o quita, con null) la grabación de la clase. No cuenta como asistencia.
     */
    public function grabacion(User $docente, int $seccionId, int $claseId, ?string $url): void
    {
        $this->exigirEnSeccion($docente, 'videoconferencias.gestionar', $seccionId);
        $this->clase($seccionId, $claseId);
        $url = blank($url) ? null : trim((string) $url);
        if ($url !== null && (! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $url))) {
            throw ValidationException::withMessages(['grabacion' => __('El enlace de la grabación no es válido.')]);
        }

        DbContext::run(fn () => DB::table('video_sessions')->where('id', $claseId)->update(['recording_url' => $url, 'updated_at' => now()]), 'video_session.recording');
    }

    /**
     * Lee el reporte de Meet de una clase finalizada y lo deja como borrador (sin confirmar). Devuelve su id.
     */
    public function subir(User $docente, int $seccionId, int $claseId, UploadedFile $archivo): int
    {
        $this->exigirEnSeccion($docente, 'videoconferencias.gestionar', $seccionId);
        $clase = DB::table('video_sessions')->where('id', $claseId)->where('section_id', $seccionId)->first()
            ?? throw ValidationException::withMessages(['archivo' => __('La clase no es de esta sección.')]);
        $this->actualizarEstados(collect([$clase]));
        if ($this->estadoPorHora(Carbon::parse($clase->starts_at), (int) $clase->duration_minutes) !== 'finished') {
            throw ValidationException::withMessages(['archivo' => __('Solo se sube el reporte de una clase que ya terminó.')]);
        }

        $extension = strtolower($archivo->getClientOriginalExtension() ?: (string) $archivo->guessExtension());
        if (! in_array($extension, self::EXTENSIONES, true)) {
            throw ValidationException::withMessages(['archivo' => __('Suba el reporte en CSV o Excel (.xlsx).')]);
        }
        try {
            $hoja = HojaCalculo::leer((string) $archivo->getRealPath(), $extension);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['archivo' => __('No se pudo leer el archivo. Descargue de nuevo el reporte de Meet.')]);
        }

        [$filas, $fecha] = $this->interpretar($hoja);
        $diaClase = $this->local($clase->starts_at)->toDateString();
        if ($fecha === null) {
            throw ValidationException::withMessages(['archivo' => __('No se encontró la fecha de la reunión en el reporte.')]);
        }
        if ($fecha !== $diaClase) {
            throw ValidationException::withMessages(['archivo' => __('El reporte es del :r y la clase es del :c. Revise que sea el reporte de esta clase.', [
                'r' => Carbon::parse($fecha)->format('d/m/Y'), 'c' => Carbon::parse($diaClase)->format('d/m/Y')])]);
        }

        $matriculas = DB::table('enrollments as e')->join('users as u', 'u.id', '=', 'e.student_id')
            ->where('e.section_id', $seccionId)->where('e.status', 'active')->get(['e.id', 'u.institutional_email as email'])
            ->mapWithKeys(fn ($m) => [mb_strtolower(trim((string) $m->email)) => (int) $m->id]);
        $anteriores = DB::table('meet_reports')->where('video_session_id', $claseId)->whereNull('confirmed_at')->get(['id', 'file_path']);
        $ruta = $archivo->storeAs('meet/'.$claseId, Str::ulid().'.'.$extension, 'local');
        if ($ruta === false) {
            throw ValidationException::withMessages(['archivo' => __('No se pudo guardar el archivo. Inténtelo de nuevo.')]);
        }

        try {
            $id = (int) DbContext::run(function () use ($docente, $claseId, $ruta, $filas, $matriculas, $anteriores) {
                if ($anteriores->isNotEmpty()) {
                    DB::table('meet_report_rows')->whereIn('meet_report_id', $anteriores->pluck('id'))->delete();
                    DB::table('meet_reports')->whereIn('id', $anteriores->pluck('id'))->delete();
                }
                $id = DB::table('meet_reports')->insertGetId(['video_session_id' => $claseId, 'file_path' => $ruta, 'uploaded_by' => $docente->id,
                    'confirmed_at' => null, 'is_current' => false, 'created_at' => now(), 'updated_at' => now()]);
                foreach (array_chunk($filas, 200) as $lote) {
                    DB::table('meet_report_rows')->insert(array_map(function (array $f) use ($id, $matriculas) {
                        $matricula = $f['correo'] !== null ? $matriculas->get($f['correo']) : null;

                        return ['meet_report_id' => $id, 'name' => $f['nombre'], 'email' => $f['correo'], 'minutes' => $f['minutos'],
                            'enrollment_id' => $matricula, 'status' => $matricula ? 'matched' : 'unmatched'];
                    }, $lote));
                }

                return $id;
            }, 'meet_report.uploaded');
        } catch (Throwable $e) {
            Storage::disk('local')->delete($ruta);
            throw $e;
        }

        Storage::disk('local')->delete($anteriores->pluck('file_path')->filter()->all());

        return $id;
    }

    /**
     * Vista previa de un reporte: por cada matrícula activa, sus minutos, el porcentaje y el estado que le tocaría;
     * más las filas sin asociar y las descartadas.
     *
     * @return array{reporte: object, clase: object, presentes: Collection<int, object>, ausentes: Collection<int, object>, sin_asociar: Collection<int, object>, descartadas: Collection<int, object>}
     */
    public function vistaPrevia(User $docente, int $seccionId, int $reporteId): array
    {
        $this->exigirEnSeccion($docente, 'videoconferencias.gestionar', $seccionId);
        $reporte = $this->reporte($seccionId, $reporteId);
        $clase = $this->clase($seccionId, (int) $reporte->video_session_id);
        $estudiantes = $this->calculo($seccionId, $reporteId, (int) $clase->duration_minutes);
        $filas = DB::table('meet_report_rows')->where('meet_report_id', $reporteId)->whereIn('status', ['unmatched', 'discarded'])
            ->orderBy('name')->orderBy('email')->get(['id', 'name', 'email', 'minutes', 'status']);

        return [
            'reporte' => $reporte,
            'clase' => $clase,
            'presentes' => $estudiantes->where('estado', 'present')->values(),
            'ausentes' => $estudiantes->where('estado', 'absent')->values(),
            'sin_asociar' => $filas->where('status', 'unmatched')->values(),
            'descartadas' => $filas->where('status', 'discarded')->values(),
        ];
    }

    /**
     * Asigna a mano una fila del borrador a una matrícula activa de la sección.
     */
    public function asignar(User $docente, int $seccionId, int $filaId, int $matriculaId): void
    {
        $this->exigirEnSeccion($docente, 'videoconferencias.gestionar', $seccionId);
        $this->filaDeBorrador($seccionId, $filaId);
        DB::table('enrollments')->where('id', $matriculaId)->where('section_id', $seccionId)->where('status', 'active')->exists() || abort(404);

        DbContext::run(fn () => DB::table('meet_report_rows')->where('id', $filaId)->update(['enrollment_id' => $matriculaId, 'status' => 'matched']), 'meet_report_row.assigned');
    }

    /**
     * Descarta una fila del borrador (p. ej. un invitado externo): no cuenta para nadie.
     */
    public function descartar(User $docente, int $seccionId, int $filaId): void
    {
        $this->exigirEnSeccion($docente, 'videoconferencias.gestionar', $seccionId);
        $this->filaDeBorrador($seccionId, $filaId);

        DbContext::run(fn () => DB::table('meet_report_rows')->where('id', $filaId)->update(['enrollment_id' => null, 'status' => 'discarded']), 'meet_report_row.discarded');
    }

    /**
     * Confirma el borrador: pasa a ser el reporte vigente de la clase y registra la asistencia de cada matrícula
     * activa con el método «meet». Si había otro reporte vigente, lo reemplaza (auditado). Devuelve el ulid de la
     * sesión de asistencia de la clase.
     */
    public function confirmar(User $docente, int $seccionId, int $reporteId): string
    {
        $this->exigirEnSeccion($docente, 'videoconferencias.gestionar', $seccionId);
        $reporte = $this->reporte($seccionId, $reporteId);
        if ($reporte->confirmed_at !== null) {
            throw ValidationException::withMessages(['reporte' => __('Este reporte ya se confirmó.')]);
        }
        if (DB::table('meet_report_rows')->where('meet_report_id', $reporteId)->where('status', 'unmatched')->exists()) {
            throw ValidationException::withMessages(['reporte' => __('Asigne o descarte las filas sin asociar antes de confirmar.')]);
        }
        $clase = $this->clase($seccionId, (int) $reporte->video_session_id);
        $inicio = $this->local($clase->starts_at);
        $fin = $inicio->copy()->addMinutes((int) $clase->duration_minutes);
        $fecha = $inicio->toDateString();
        $estudiantes = $this->calculo($seccionId, $reporteId, (int) $clase->duration_minutes);
        $anterior = DB::table('meet_reports')->where('video_session_id', $clase->id)->where('is_current', true)->where('id', '!=', $reporteId)->value('id');

        $ulid = DbContext::run(function () use ($docente, $seccionId, $reporteId, $clase, $inicio, $fin, $fecha, $estudiantes, $anterior) {
            if ($anterior !== null) {
                DB::table('meet_reports')->where('id', $anterior)->update(['is_current' => false, 'updated_at' => now()]);
            }
            DB::table('meet_reports')->where('id', $reporteId)->update(['is_current' => true, 'confirmed_at' => now(), 'updated_at' => now()]);

            $sesion = DB::table('attendance_sessions')->where('video_session_id', $clase->id)->orderBy('id')->first(['id', 'ulid']);
            if (! $sesion) {
                $sesion = (object) ['ulid' => (string) Str::ulid()];
                $sesion->id = DB::table('attendance_sessions')->insertGetId([
                    'ulid' => $sesion->ulid, 'section_id' => $seccionId,
                    'grading_component_id' => DB::table('grading_components')->where('section_id', $seccionId)->where('starts_on', '<=', $fecha)->where('ends_on', '>=', $fecha)->value('id'),
                    'area_id' => null, 'video_session_id' => $clase->id, 'session_date' => $fecha, 'modality' => 'virtual', 'status' => 'closed',
                    'opened_at' => $inicio, 'closed_at' => $fin, 'opened_by' => $docente->id, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            $existentes = DB::table('attendance_records')->where('session_id', $sesion->id)->get(['id', 'enrollment_id', 'status'])->keyBy('enrollment_id');
            foreach ($estudiantes as $e) {
                $registro = $existentes->get($e->matricula);
                if ($registro && $registro->status === 'justified') {
                    continue;
                }
                $datos = ['status' => $e->estado, 'method' => 'meet', 'meet_percent' => $e->porcentaje, 'recorded_at' => now(),
                    'corrected_by' => null, 'correction_reason' => null, 'updated_at' => now()];
                if ($registro) {
                    DB::table('attendance_records')->where('id', $registro->id)->update($datos);
                } else {
                    DB::table('attendance_records')->insert($datos + ['session_id' => $sesion->id, 'section_id' => $seccionId, 'enrollment_id' => $e->matricula, 'created_at' => now()]);
                }
            }

            return $sesion->ulid;
        }, $anterior !== null ? 'meet_report.replaced' : 'meet_report.confirmed');

        EvaluarAlertasAsistencia::dispatch($seccionId);

        return (string) $ulid;
    }

    /**
     * Segundos de una duración del reporte: «1 h 23 min», «1 hr 23 min», «1 hora 5 minutos», «45 min», «30 s»,
     * «1:23:45» (h:m:s), «23:45» (m:s) o un número. Un número menor que 1 es la fracción de día con que Excel guarda
     * las duraciones; uno mayor o igual, minutos. Null si no se entiende.
     */
    public static function duracionEnSegundos(string $valor): ?int
    {
        $texto = Str::of($valor)->ascii()->lower()->replace(',', '.')->squish()->toString();
        if ($texto === '') {
            return null;
        }
        if (preg_match('/^(\d+):(\d{1,2}):(\d{1,2})(?:\.\d+)?$/', $texto, $m)) {
            return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3];
        }
        if (preg_match('/^(\d+):(\d{1,2})(?:\.\d+)?$/', $texto, $m)) {
            return (int) $m[1] * 60 + (int) $m[2];
        }
        if (is_numeric($texto)) {
            $numero = (float) $texto;

            return (int) round($numero < 1 ? $numero * 86400 : $numero * 60);
        }

        $unidades = '(horas|hora|hours|hour|hrs|hr|h|minutos|minuto|minutes|minute|mins|min|m|segundos|segundo|seconds|second|segs|seg|secs|sec|s)';
        if (! preg_match_all('/(\d+(?:\.\d+)?)\s*'.$unidades.'(?![a-z])/', $texto, $partes, PREG_SET_ORDER)) {
            return null;
        }
        $segundos = 0.0;
        foreach ($partes as [, $cantidad, $unidad]) {
            $segundos += (float) $cantidad * match (true) {
                str_starts_with($unidad, 'h') => 3600,
                str_starts_with($unidad, 'm') => 60,
                default => 1,
            };
        }

        return (int) round($segundos);
    }

    /**
     * Día (Y-m-d) de un texto del reporte: «2026-10-23 08:01», «23/10/2026», «23 oct 2026», «23 de octubre de 2026»,
     * «Oct 23, 2026» o un número de serie de Excel. Null si no trae una fecha completa.
     */
    public static function fechaDe(string $valor): ?string
    {
        $texto = Str::of($valor)->ascii()->lower()->squish()->toString();
        $fecha = null;
        if (preg_match('/(\d{4})-(\d{1,2})-(\d{1,2})/', $texto, $m)) {
            $fecha = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#(\d{1,2})/(\d{1,2})/(\d{4})#', $texto, $m)) {
            $fecha = [(int) $m[3], (int) $m[2], (int) $m[1]];
        } elseif (preg_match('/(\d{1,2})\s+(?:de\s+)?([a-z]{3,})\.?\s+(?:de\s+|del\s+)?(\d{4})/', $texto, $m) && isset(self::MESES[substr($m[2], 0, 3)])) {
            $fecha = [(int) $m[3], self::MESES[substr($m[2], 0, 3)], (int) $m[1]];
        } elseif (preg_match('/([a-z]{3,})\.?\s+(\d{1,2}),?\s+(\d{4})/', $texto, $m) && isset(self::MESES[substr($m[1], 0, 3)])) {
            $fecha = [(int) $m[3], self::MESES[substr($m[1], 0, 3)], (int) $m[2]];
        } elseif (preg_match('/^\d{5}(\.\d+)?$/', $texto) && (float) $texto >= 36526 && (float) $texto < 73051) {
            // Número de serie de Excel (días desde el 30/12/1899), entre los años 2000 y 2100.
            return Carbon::create(1899, 12, 30)->addDays((int) floor((float) $texto))->toDateString();
        }

        if ($fecha === null || ! checkdate($fecha[1], $fecha[2], $fecha[0])) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $fecha[0], $fecha[1], $fecha[2]);
    }

    /**
     * Ubica la fila de encabezados, agrupa las filas por persona sumando los minutos y obtiene la fecha del reporte.
     *
     * @param  list<list<string>>  $hoja
     * @return array{0: list<array{correo: ?string, nombre: ?string, minutos: int}>, 1: ?string}
     */
    private function interpretar(array $hoja): array
    {
        $columnas = null;
        $inicio = 0;
        foreach ($hoja as $i => $fila) {
            $mapa = [];
            foreach ($fila as $j => $celda) {
                $clave = $this->clave($celda);
                if ($clave !== null && ! isset($mapa[$clave])) {
                    $mapa[$clave] = $j;
                }
            }
            if (isset($mapa['duracion']) && (isset($mapa['correo']) || isset($mapa['nombre']))) {
                $columnas = $mapa;
                $inicio = $i;
                break;
            }
        }
        if ($columnas === null) {
            throw ValidationException::withMessages(['archivo' => __('No se reconocen las columnas del reporte. Se esperan: correo (Email), nombre (First name / Nombre), apellido (Last name) y duración (Duration / Duración).')]);
        }

        $celda = fn (array $fila, string $clave) => isset($columnas[$clave]) ? trim((string) ($fila[$columnas[$clave]] ?? '')) : '';
        $fecha = null;
        $personas = [];
        foreach (array_slice($hoja, $inicio + 1) as $fila) {
            $correo = mb_strtolower($celda($fila, 'correo'));
            $correo = str_contains($correo, '@') ? $correo : null;
            $nombre = trim($celda($fila, 'nombre').' '.$celda($fila, 'apellido'));
            if ($correo === null && $nombre === '') {
                continue;
            }
            $fecha ??= ($entrada = $celda($fila, 'entrada')) !== '' ? self::fechaDe($entrada) : null;

            $clave = $correo ?? 'nombre:'.mb_strtolower($nombre);
            $personas[$clave] ??= ['correo' => $correo, 'nombre' => $nombre !== '' ? $nombre : null, 'segundos' => 0];
            $personas[$clave]['nombre'] ??= $nombre !== '' ? $nombre : null;
            $personas[$clave]['segundos'] += self::duracionEnSegundos($celda($fila, 'duracion')) ?? 0;
        }

        if ($fecha === null) {
            foreach (array_slice($hoja, 0, $inicio) as $fila) {
                foreach ($fila as $valor) {
                    if ($fecha = ($valor !== '' ? self::fechaDe($valor) : null)) {
                        break 2;
                    }
                }
            }
        }

        $filas = array_values(array_map(fn (array $p) => ['correo' => $p['correo'], 'nombre' => $p['nombre'] === null ? null : Str::limit($p['nombre'], 250, ''),
            'minutos' => (int) round($p['segundos'] / 60)], $personas));

        return [$filas, $fecha];
    }

    /**
     * Columna canónica de un encabezado (minúsculas, sin tildes ni texto entre paréntesis), o null.
     */
    private function clave(string $encabezado): ?string
    {
        $texto = Str::of($encabezado)->ascii()->lower()->replaceMatches('/\(.*?\)/', '')->replace(['_', ':'], ' ')->squish()->toString();
        foreach (self::ALIAS as $clave => $alias) {
            if (in_array($texto, $alias, true)) {
                return $clave;
            }
        }

        return null;
    }

    /**
     * Minutos, porcentaje y estado de cada matrícula activa según las filas asociadas del reporte.
     *
     * @return Collection<int, object>
     */
    private function calculo(int $seccionId, int $reporteId, int $duracion): Collection
    {
        $minutos = DB::table('meet_report_rows')->where('meet_report_id', $reporteId)->where('status', 'matched')->whereNotNull('enrollment_id')
            ->groupBy('enrollment_id')->selectRaw('enrollment_id, sum(minutes) as total')->get()->pluck('total', 'enrollment_id');

        return DB::table('enrollments as e')->join('users as u', 'u.id', '=', 'e.student_id')->leftJoin('student_profiles as p', 'p.user_id', '=', 'u.id')
            ->where('e.section_id', $seccionId)->where('e.status', 'active')->orderBy('u.last_name')->orderBy('u.first_name')
            ->get(['e.id', 'u.first_name', 'u.last_name', 'u.institutional_email as email', 'p.student_code'])
            ->map(function (object $e) use ($minutos, $duracion) {
                $min = (int) ($minutos[$e->id] ?? 0);
                $porcentaje = $duracion > 0 ? min(100.0, round($min / $duracion * 100, 2)) : 0.0;

                return (object) ['matricula' => (int) $e->id, 'nombre' => trim($e->first_name.' '.$e->last_name), 'correo' => $e->email,
                    'carnet' => $e->student_code, 'minutos' => $min, 'porcentaje' => $porcentaje, 'estado' => $porcentaje >= self::UMBRAL ? 'present' : 'absent'];
            });
    }

    /**
     * Guarda el estado que corresponde por la hora a las clases que cambiaron.
     *
     * @param  Collection<int, object>  $clases
     */
    private function actualizarEstados(Collection $clases): void
    {
        $cambios = $clases->mapWithKeys(fn (object $c) => [(int) $c->id => $this->estadoPorHora(Carbon::parse($c->starts_at), (int) $c->duration_minutes)])
            ->filter(fn (string $estado, int $id) => $estado !== $clases->firstWhere('id', $id)?->status);
        if ($cambios->isEmpty()) {
            return;
        }

        DbContext::run(function () use ($cambios): void {
            foreach ($cambios->groupBy(fn (string $estado) => $estado, true) as $estado => $ids) {
                DB::table('video_sessions')->whereIn('id', $ids->keys())->update(['status' => $estado, 'updated_at' => now()]);
            }
        }, 'video_session.status');
    }

    private function estadoPorHora(Carbon $inicio, int $duracion): string
    {
        $ahora = now();

        return match (true) {
            $ahora->lt($inicio) => 'scheduled',
            $ahora->lt($inicio->copy()->addMinutes($duracion)) => 'in_progress',
            default => 'finished',
        };
    }

    private function fechaHora(string $valor): ?Carbon
    {
        $valor = str_replace('T', ' ', trim($valor));
        if (! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $valor)) {
            return null;
        }
        $fecha = Carbon::createFromFormat('Y-m-d H:i', $valor, (string) config('app.timezone'));

        return $fecha instanceof Carbon && $fecha->format('Y-m-d H:i') === $valor ? $fecha->startOfMinute() : null;
    }

    /**
     * Instante de la base en la zona de la aplicación (ADR-041).
     */
    private function local(string $instante): Carbon
    {
        return Carbon::parse($instante)->setTimezone((string) config('app.timezone'));
    }

    private function clase(int $seccionId, int $claseId): object
    {
        return DB::table('video_sessions')->where('id', $claseId)->where('section_id', $seccionId)->first() ?? abort(404);
    }

    private function reporte(int $seccionId, int $reporteId): object
    {
        return DB::table('meet_reports as m')->join('video_sessions as v', 'v.id', '=', 'm.video_session_id')
            ->where('m.id', $reporteId)->where('v.section_id', $seccionId)->first(['m.*']) ?? abort(404);
    }

    private function filaDeBorrador(int $seccionId, int $filaId): object
    {
        return DB::table('meet_report_rows as f')->join('meet_reports as m', 'm.id', '=', 'f.meet_report_id')->join('video_sessions as v', 'v.id', '=', 'm.video_session_id')
            ->where('f.id', $filaId)->where('v.section_id', $seccionId)->whereNull('m.confirmed_at')->first(['f.id', 'f.meet_report_id']) ?? abort(404);
    }

    private function verdadero(): string
    {
        return DB::connection()->getDriverName() === 'pgsql' ? 'true' : '1';
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
}
