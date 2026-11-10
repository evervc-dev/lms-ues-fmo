<?php

namespace App\Modules\Asistencia\Services;

use App\Models\User;
use App\Modules\Identidad\Models\DeviceEvent;
use App\Modules\Identidad\Models\StudentDevice;
use App\Modules\Identidad\Services\DispositivoService;
use App\Support\Alcance;
use App\Support\DbContext;
use App\Support\DbError;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Asistencia con QR (RF20, ADR-028, ADR-045, ADR-046; Flujo Asistencia con QR).
 *
 * El token del QR se deriva con HMAC de la sesión y de la ventana de rotación: todas las pantallas que proyectan
 * la misma sesión muestran el mismo QR y solo se guarda su SHA-256 en `qr_tokens`. Se acepta el token vigente y el
 * inmediatamente anterior (ADR-046). Cada intento, aceptado o no, queda en `attendance_scan_attempts`.
 */
class AsistenciaQrService
{
    /** Textos para el estudiante; los tres literales vienen de RF20. */
    public const MENSAJES = [
        'ok' => 'Asistencia registrada.',
        'no_location' => 'No se pudo obtener su ubicación. Active el GPS y permita el acceso a la ubicación, o pida un código temporal a su docente.',
        'session_closed' => 'La sesión de asistencia está cerrada',
        'token_expired' => 'Código vencido; escanee el QR actual',
        'scan_limit' => 'Ya usó sus :n intentos en esta sesión. Pida un código temporal a su docente.',
        'not_enrolled' => 'No está matriculado en esta sección.',
        'already_recorded' => 'Ya registró su asistencia en esta sesión.',
        'out_of_range' => 'Fuera del área de asistencia',
        'invalid_device' => 'Este teléfono no es su dispositivo registrado. Regístrelo en Mi perfil o solicite el cambio a Soporte.',
    ];

    public function __construct(private Alcance $alcance, private DispositivoService $dispositivos, private GeocercaService $geocerca) {}

    /**
     * QR que proyecta el docente y los datos de la pantalla de proyección (sin nombres de estudiantes).
     *
     * @return array{sesion: object, abierta: bool, svg: ?string, presentes: int, segundos: int}
     *
     * @throws AuthorizationException
     */
    public function proyeccion(User $docente, int $seccionId, string $ulid): array
    {
        $this->alcance->puedeEnSeccion($docente, 'asistencia.sesion.abrir', $seccionId)
            || throw new AuthorizationException(__('No tiene permiso para realizar esta acción.'));
        $sesion = DB::table('attendance_sessions')->where('section_id', $seccionId)->where('ulid', $ulid)->first() ?? abort(404);
        $abierta = $this->abierta($sesion) && $sesion->modality === 'in_person';

        return [
            'sesion' => $sesion,
            'abierta' => $abierta,
            'svg' => $abierta ? $this->svg($this->contenido($sesion->ulid, $this->emitirToken($sesion))) : null,
            'presentes' => DB::table('attendance_records')->where('session_id', $sesion->id)->whereIn('status', ['present', 'late'])->count(),
            'segundos' => $abierta ? max(0, (int) now()->diffInSeconds($this->vence($sesion), false)) : 0,
        ];
    }

    /**
     * El estudiante envía el QR leído, su ubicación y los dos tokens de su dispositivo.
     * Valida en el orden del flujo y guarda el intento siempre. `cookie` y `local` traen el token que hay que
     * volver a escribir en el teléfono cuando solo coincidió el otro (ADR-045).
     *
     * @return array{resultado: string, mensaje: string, intentosRestantes: int, cookie: ?string, local: ?string}
     *
     * @throws AuthorizationException
     */
    public function marcar(User $estudiante, string $qr, ?float $lat, ?float $lng, ?float $precision, ?string $tokenCookie, ?string $tokenLocal): array
    {
        $this->alcance->exigir($estudiante, 'asistencia.marcar');
        [$ulid, $token] = $this->leerContenido($qr);
        $sesion = DB::table('attendance_sessions')->where('ulid', $ulid)->first()
            ?? throw ValidationException::withMessages(['qr' => __('Este QR no es de una sesión de asistencia.')]);
        $maximo = $this->parametro('max_scans_per_student', 3);
        [$distancia, $dentro] = $this->evaluarAreas($sesion, $lat, $lng);
        $dispositivo = $this->dispositivos->validar($estudiante, $tokenCookie, $tokenLocal);

        // Una sola transacción, serializada por estudiante: los intentos se cuentan bajo el bloqueo, así que
        // varios envíos a la vez no pasan del límite (ADR-028).
        [$resultado, $usados] = DbContext::run(function () use ($sesion, $estudiante, $token, $maximo, $dentro, $distancia, $precision, $lat, $lng, $dispositivo) {
            DB::table('users')->where('id', $estudiante->id)->lockForUpdate()->first(['id']);
            // Los intentos aceptados no gastan cupo: tras un «ok», el siguiente escaneo dice «ya registró».
            $usados = DB::table('attendance_scan_attempts')->where('session_id', $sesion->id)->where('student_id', $estudiante->id)->where('result', '!=', 'ok')->count();
            $matricula = DB::table('enrollments')->where('section_id', $sesion->section_id)->where('student_id', $estudiante->id)->where('status', 'active')->value('id');

            $resultado = match (true) {
                $lat === null || $lng === null => 'no_location',
                ! $this->abierta($sesion) => 'session_closed',
                ! $this->tokenValido($sesion, $token) => 'token_expired',
                $usados >= $maximo => 'scan_limit',
                $matricula === null => 'not_enrolled',
                DB::table('attendance_records')->where('session_id', $sesion->id)->where('enrollment_id', $matricula)->exists() => 'already_recorded',
                ! $dentro => 'out_of_range',
                $dispositivo === null => 'invalid_device',
                default => 'ok',
            };

            if ($resultado === 'ok' && $dispositivo !== null) {
                try {
                    // En un savepoint: si el registro falla, el intento rechazado igual queda guardado.
                    DB::transaction(fn () => $this->registrar($sesion, (int) $matricula, $dispositivo, $distancia, $precision));
                } catch (UniqueConstraintViolationException) {
                    $resultado = 'already_recorded';
                } catch (QueryException $e) {
                    // AC203: el período de la sesión ya cerró (fn_block_closed_period); cualquier otro error se traduce.
                    $resultado = $e->getCode() === 'AC203' || str_contains($e->getMessage(), 'AC203') ? 'session_closed' : throw DbError::toDomain($e);
                }
            }
            $this->intento($sesion->id, $estudiante->id, $resultado, $distancia);

            return [$resultado, $usados];
        }, 'attendance.qr');

        $cookieCoincide = $dispositivo && filled($tokenCookie) && StudentDevice::hashDe($tokenCookie) === $dispositivo->credential_id;
        $localCoincide = $dispositivo && filled($tokenLocal) && StudentDevice::hashDe($tokenLocal) === $dispositivo->credential_id;

        return [
            'resultado' => $resultado,
            'mensaje' => __(self::MENSAJES[$resultado], ['n' => $maximo]),
            'intentosRestantes' => $resultado === 'ok' ? 0 : max(0, $maximo - $usados - 1),
            'cookie' => $resultado === 'ok' && ! $cookieCoincide ? $tokenLocal : null,
            'local' => $resultado === 'ok' && ! $localCoincide ? $tokenCookie : null,
        ];
    }

    /**
     * Borra los tokens del QR vencidos hace más de `qr_token_retention_days` (ADR-028). Idempotente.
     */
    public function purgarTokens(): int
    {
        $limite = now()->subDays($this->parametro('qr_token_retention_days', 7));

        return DbContext::system(fn () => DB::table('qr_tokens')->where('expires_at', '<', $limite)->delete(), 'qr_tokens.purged');
    }

    /**
     * Contenido del QR: la URL de «Marcar asistencia» con la sesión y el token, para que una cámara nativa al menos
     * abra la página correcta. El lector de la página solo usa el parámetro `qr`.
     */
    public function contenido(string $ulid, string $token): string
    {
        return route('asistencia.escanear', ['qr' => $ulid.'.'.$token]);
    }

    /**
     * Token de la ventana de rotación actual: lo guarda (solo su hash) la primera vez que se pide.
     */
    private function emitirToken(object $sesion): string
    {
        $rotacion = max(1, (int) $sesion->qr_rotation_seconds);
        $ventana = intdiv(now()->getTimestamp(), $rotacion);
        $token = $this->token($sesion->ulid, $ventana);

        DB::table('qr_tokens')->insertOrIgnore([
            'session_id' => $sesion->id, 'token_hash' => hash('sha256', $token),
            // En la zona de la app (ADR-041): createFromTimestamp() sin zona da UTC y se guardaría 6 h corrida.
            'expires_at' => Carbon::createFromTimestamp(($ventana + 1) * $rotacion, config('app.timezone')), 'created_at' => now(),
        ]);

        return $token;
    }

    /**
     * Vale el token vigente y el inmediatamente anterior: el que venció hace menos de una rotación.
     */
    private function tokenValido(object $sesion, string $token): bool
    {
        $fila = DB::table('qr_tokens')->where('session_id', $sesion->id)->where('token_hash', hash('sha256', $token))->first();

        return $fila !== null && now()->lt(Carbon::parse($fila->expires_at)->addSeconds((int) $sesion->qr_rotation_seconds));
    }

    private function token(string $ulid, int $ventana): string
    {
        return hash_hmac('sha256', 'qr|'.$ulid.'|'.$ventana, (string) config('app.key'));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function leerContenido(string $qr): array
    {
        $valor = str_contains($qr, '?') ? (string) (parse_url($qr, PHP_URL_QUERY) ?? '') : '';
        if ($valor !== '') {
            parse_str($valor, $parametros);
            $qr = (string) ($parametros['qr'] ?? '');
        }
        if (! preg_match('/^([0-9A-Za-z]{26})\.([0-9a-f]{64})$/', trim($qr), $partes)) {
            throw ValidationException::withMessages(['qr' => __('Este QR no es de una sesión de asistencia.')]);
        }

        return [$partes[1], $partes[2]];
    }

    /**
     * Asistencia aceptada y último uso del dispositivo. Corre dentro de la transacción de marcar().
     */
    private function registrar(object $sesion, int $matricula, StudentDevice $dispositivo, ?int $distancia, ?float $precision): void
    {
        DB::table('attendance_records')->insert([
            'session_id' => $sesion->id, 'section_id' => $sesion->section_id, 'enrollment_id' => $matricula,
            // Sin tardanza con QR (ADR-046): toda marca aceptada es «presente».
            'status' => 'present', 'method' => 'qr', 'recorded_at' => now(),
            'distance_m' => $distancia, 'accuracy_m' => $precision === null ? null : (int) round($precision), 'inside_area' => true,
            'device_id' => $dispositivo->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $dispositivo->update(['last_used_at' => now()]);
        DeviceEvent::create(['device_id' => $dispositivo->id, 'event_type' => 'used', 'occurred_at' => now()]);
    }

    private function intento(int $sesionId, int $estudianteId, string $resultado, ?int $distancia): void
    {
        DB::table('attendance_scan_attempts')->insert([
            'session_id' => $sesionId, 'student_id' => $estudianteId, 'result' => $resultado, 'distance_m' => $distancia, 'occurred_at' => now(),
        ]);
    }

    private function abierta(object $sesion): bool
    {
        return $sesion->status === 'open' && now()->lt($this->vence($sesion));
    }

    private function vence(object $sesion): Carbon
    {
        return Carbon::parse($sesion->opened_at)->addMinutes((int) $sesion->qr_validity_minutes);
    }

    private function svg(string $contenido): string
    {
        return (new QRCode(new QROptions(['outputBase64' => false, 'svgAddXmlHeader' => false, 'eccLevel' => 'M'])))->render($contenido);
    }

    /**
     * Distancia a la mejor área y si cae dentro de alguna: vale cualquier área activa (RF26) y la de la sesión, aunque
     * se haya desactivado después. Sin área en la sesión (virtual) o sin ubicación no hay distancia y queda fuera.
     * La mejor área es la que deja más margen dentro de su radio, o la más cercana si ninguna contiene el punto.
     * En un círculo la distancia es al centro; en un polígono, al borde, y vale 0 si el punto está dentro.
     *
     * @return array{0: int|null, 1: bool}
     */
    private function evaluarAreas(object $sesion, ?float $lat, ?float $lng): array
    {
        if ($lat === null || $lng === null || ! $sesion->area_id) {
            return [null, false];
        }

        $mejor = null;
        $areas = DB::table('attendance_areas')
            ->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $sesion->area_id))
            ->get(['area_type', 'center_lat', 'center_lng', 'radius_m', 'polygon']);
        foreach ($areas as $area) {
            if ($area->area_type === 'polygon') {
                $vertices = json_decode((string) $area->polygon, true);
                if (! is_array($vertices) || count($vertices) < 3) {
                    continue;
                }
                // Sin redondear el margen: a 0,4 m del borde, por fuera, el punto sigue fuera.
                $margen = $this->geocerca->distancia($lat, $lng, $vertices);
                $distancia = (int) ceil($margen);
            } else {
                $distancia = (int) round($this->distanciaMetros($lat, $lng, (float) $area->center_lat, (float) $area->center_lng));
                $margen = $distancia - (int) $area->radius_m;
            }
            if ($mejor === null || $margen < $mejor['margen']) {
                $mejor = ['distancia' => $distancia, 'margen' => $margen];
            }
        }

        return $mejor === null ? [null, false] : [$mejor['distancia'], $mejor['margen'] <= 0];
    }

    /**
     * Distancia en metros entre dos puntos (haversine).
     */
    private function distanciaMetros(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lng2 - $lng1) / 2) ** 2;

        return 6_371_000 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function parametro(string $clave, int $porDefecto): int
    {
        return (int) (json_decode((string) DB::table('system_settings')->where('key', $clave)->value('value'), true) ?? $porDefecto);
    }
}
