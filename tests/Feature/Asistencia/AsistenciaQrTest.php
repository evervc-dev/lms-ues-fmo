<?php

use App\Models\User;
use App\Modules\Asistencia\Jobs\PurgarTokensQr;
use App\Modules\Asistencia\Livewire\DiagnosticoAsistencia;
use App\Modules\Asistencia\Livewire\EscanearQr;
use App\Modules\Asistencia\Livewire\ProyectarQr;
use App\Modules\Asistencia\Services\AsistenciaQrService;
use App\Modules\Asistencia\Services\AsistenciaService;
use App\Modules\Asistencia\Services\DiagnosticoService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/** Centro del área de asistencia de las pruebas (campus) y su radio en metros. */
const QR_LAT = 13.4393;

const QR_LNG = -88.1597;

const QR_RADIO = 100;

beforeEach(function () {
    // Lunes 19/10/2026 08:00:00 en El Salvador: inicio de una ventana de rotación de 10 y de 15 s.
    $this->travelTo(Carbon::parse('2026-10-19 08:00:00'));
});

/**
 * Escenario de RF20: estudiante matriculado con su teléfono activo, área circular activa y sesión presencial abierta.
 *
 * @return array<string, mixed>
 */
function escenarioQr(int $minutos = 10): array
{
    $e = escenario();
    $e['area'] = areaQr();
    $e['token'] = 'token-del-telefono';
    $e['dispositivo'] = telefonoActivo($e['estudiante'], $e['token']);
    $e['sesion'] = abrirSesionQr($e, $minutos);

    return $e;
}

function areaQr(): int
{
    return DbContext::system(fn () => DB::table('attendance_areas')->insertGetId([
        'name' => 'Campus FMO', 'area_type' => 'circle', 'center_lat' => QR_LAT, 'center_lng' => QR_LNG,
        'radius_m' => QR_RADIO, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]));
}

/**
 * Dispositivo activo con el hash del token, como lo deja DispositivoService::registrar().
 */
function telefonoActivo(User $estudiante, string $token): int
{
    return DbContext::system(fn () => DB::table('student_devices')->insertGetId([
        'student_id' => $estudiante->id, 'credential_id' => hash('sha256', $token), 'status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ]));
}

/**
 * @param  array<string, mixed>  $e
 */
function abrirSesionQr(array $e, int $minutos = 10): string
{
    actingAs($e['docente']);

    return app(AsistenciaService::class)->abrir($e['docente'], $e['seccion'], $minutos);
}

function servicioQr(): AsistenciaQrService
{
    return app(AsistenciaQrService::class);
}

/**
 * Proyecta el QR (emite el token de la ventana actual en qr_tokens) y devuelve el contenido que leería la cámara.
 *
 * @param  array<string, mixed>  $e
 */
function qrProyectado(array $e): string
{
    actingAs($e['docente']);
    servicioQr()->proyeccion($e['docente'], $e['seccion'], $e['sesion']);
    $rotacion = (int) DB::table('attendance_sessions')->where('ulid', $e['sesion'])->value('qr_rotation_seconds');
    $token = hash_hmac('sha256', 'qr|'.$e['sesion'].'|'.intdiv(now()->getTimestamp(), $rotacion), (string) config('app.key'));

    return servicioQr()->contenido($e['sesion'], $token);
}

/**
 * El estudiante escanea desde el centro del área (o desde donde se indique) con su teléfono.
 * `'mismo'` en la cookie o el localStorage significa el token del teléfono del escenario.
 *
 * @param  array<string, mixed>  $e
 * @return array{resultado: string, mensaje: string, intentosRestantes: int, cookie: ?string, local: ?string}
 */
function escanear(array $e, string $qr, ?float $lat = QR_LAT, ?float $lng = QR_LNG, ?User $estudiante = null, ?string $cookie = 'mismo', ?string $local = 'mismo', ?float $precision = 12.4): array
{
    $estudiante ??= $e['estudiante'];
    actingAs($estudiante);

    return servicioQr()->marcar($estudiante, $qr, $lat, $lng, $precision,
        $cookie === 'mismo' ? $e['token'] : $cookie, $local === 'mismo' ? $e['token'] : $local);
}

/**
 * @param  array<string, mixed>  $e
 * @return array<int, string>
 */
function resultadosGuardados(array $e, ?User $estudiante = null): array
{
    return DB::table('attendance_scan_attempts')->where('student_id', ($estudiante ?? $e['estudiante'])->id)->orderBy('id')->pluck('result')->all();
}

it('CP-RF20-01 rechaza un token que ya rotó con «Código vencido; escanee el QR actual»', function () {
    $e = escenarioQr();
    $qr = qrProyectado($e);
    $this->travel(20)->seconds();

    $respuesta = escanear($e, $qr);

    expect($respuesta['resultado'])->toBe('token_expired')
        ->and($respuesta['mensaje'])->toBe('Código vencido; escanee el QR actual')
        ->and(resultadosGuardados($e))->toBe(['token_expired'])
        ->and(DB::table('attendance_records')->count())->toBe(0);
});

it('CP-RF20-01 acepta el token de la ventana inmediatamente anterior', function () {
    $e = escenarioQr();
    $this->travel(2)->seconds();
    $qr = qrProyectado($e);
    $this->travel(10)->seconds();

    expect(escanear($e, $qr)['resultado'])->toBe('ok');
});

it('CP-RF20-01 acepta el token vigente', function () {
    $e = escenarioQr();
    $qr = qrProyectado($e);
    $this->travel(3)->seconds();

    $respuesta = escanear($e, $qr);

    expect($respuesta['resultado'])->toBe('ok')
        ->and($respuesta['mensaje'])->toBe('Asistencia registrada.');
});

it('CP-RF20-01 rechaza un token que nunca se proyectó', function () {
    $e = escenarioQr();
    $inventado = servicioQr()->contenido($e['sesion'], hash('sha256', 'cualquier cosa'));

    expect(escanear($e, $inventado)['resultado'])->toBe('token_expired');
});

it('CP-RF20-02 rechaza el escaneo con «La sesión de asistencia está cerrada» cuando terminó su vigencia', function () {
    $e = escenarioQr(5);
    $qr = qrProyectado($e);
    $this->travel(301)->seconds();

    $respuesta = escanear($e, $qr);

    expect($respuesta['resultado'])->toBe('session_closed')
        ->and($respuesta['mensaje'])->toBe('La sesión de asistencia está cerrada')
        ->and(resultadosGuardados($e))->toBe(['session_closed']);
});

it('CP-RF20-02 rechaza el escaneo cuando el docente cerró la sesión antes de que venciera', function () {
    $e = escenarioQr();
    $qr = qrProyectado($e);
    app(AsistenciaService::class)->cerrar($e['docente'], $e['seccion'], $e['sesion']);

    expect(escanear($e, $qr)['resultado'])->toBe('session_closed');
});

it('CP-RF20-03 rechaza el cuarto escaneo e indica pedir un código temporal', function () {
    $e = escenarioQr();
    $lejos = QR_LAT + 0.01;

    $restantes = [];
    foreach (range(1, 3) as $intento) {
        $respuesta = escanear($e, qrProyectado($e), $lejos);
        expect($respuesta['resultado'])->toBe('out_of_range');
        $restantes[] = $respuesta['intentosRestantes'];
    }
    $cuarto = escanear($e, qrProyectado($e));

    expect($restantes)->toBe([2, 1, 0])
        ->and($cuarto['resultado'])->toBe('scan_limit')
        ->and($cuarto['mensaje'])->toContain('3 intentos')->toContain('código temporal')
        ->and($cuarto['intentosRestantes'])->toBe(0)
        ->and(resultadosGuardados($e))->toBe(['out_of_range', 'out_of_range', 'out_of_range', 'scan_limit'])
        ->and(DB::table('attendance_records')->count())->toBe(0);
});

it('CP-RF20-03 toma el máximo de intentos de system_settings', function () {
    $e = escenarioQr();
    DbContext::system(fn () => DB::table('system_settings')->where('key', 'max_scans_per_student')->update(['value' => json_encode(1)]));

    escanear($e, qrProyectado($e), QR_LAT + 0.01);
    $segundo = escanear($e, qrProyectado($e));

    expect($segundo['resultado'])->toBe('scan_limit')
        ->and($segundo['mensaje'])->toContain('1 intentos');
});

it('CP-RF20-04 rechaza abrir la sesión con una vigencia fuera de 5 a 15 minutos', function (int $minutos) {
    $e = escenario();
    actingAs($e['docente']);

    try {
        app(AsistenciaService::class)->abrir($e['docente'], $e['seccion'], $minutos);
        $this->fail('Debió rechazar la vigencia.');
    } catch (ValidationException $excepcion) {
        expect($excepcion->errors())->toHaveKey('minutos');
    }
    expect(DB::table('attendance_sessions')->count())->toBe(0);
})->with([4, 16]);

it('CP-RF20-04 abre la sesión con 5 y con 15 minutos y la rotación de system_settings', function () {
    $e = escenario();
    DbContext::system(fn () => DB::table('system_settings')->where('key', 'qr_rotation_seconds_default')->update(['value' => json_encode(15)]));
    actingAs($e['docente']);
    $servicio = app(AsistenciaService::class);

    $corta = $servicio->abrir($e['docente'], $e['seccion'], 5);
    $servicio->cerrar($e['docente'], $e['seccion'], $corta);
    $larga = $servicio->abrir($e['docente'], $e['seccion'], 15);

    $sesiones = DB::table('attendance_sessions')->orderBy('id')->get(['ulid', 'qr_validity_minutes', 'qr_rotation_seconds', 'status']);
    expect($sesiones->pluck('ulid')->all())->toBe([$corta, $larga])
        ->and($sesiones->pluck('qr_validity_minutes')->map(fn ($m) => (int) $m)->all())->toBe([5, 15])
        ->and($sesiones->pluck('qr_rotation_seconds')->map(fn ($s) => (int) $s)->all())->toBe([15, 15])
        ->and($sesiones->last()->status)->toBe('open');

    servicioQr()->proyeccion($e['docente'], $e['seccion'], $larga);
    $token = hash_hmac('sha256', 'qr|'.$larga.'|'.intdiv(now()->getTimestamp(), 15), (string) config('app.key'));
    expect(DB::table('qr_tokens')->where('token_hash', hash('sha256', $token))->exists())->toBeTrue();
});

it('CP-RF20-05 rechaza un segundo escaneo de quien ya registró asistencia', function () {
    $e = escenarioQr();
    expect(escanear($e, qrProyectado($e))['resultado'])->toBe('ok');

    $segundo = escanear($e, qrProyectado($e));

    expect($segundo['resultado'])->toBe('already_recorded')
        ->and($segundo['mensaje'])->toBe('Ya registró su asistencia en esta sesión.')
        ->and(DB::table('attendance_records')->where('enrollment_id', $e['matricula'])->count())->toBe(1)
        ->and(resultadosGuardados($e))->toBe(['ok', 'already_recorded']);
});

it('CP-RF20-06 rechaza el escaneo sin ubicación, indica activar el GPS o pedir un código temporal y guarda el intento', function () {
    $e = escenarioQr();

    $respuesta = escanear($e, qrProyectado($e), null, null);

    expect($respuesta['resultado'])->toBe('no_location')
        ->and($respuesta['mensaje'])->toContain('Active el GPS')->toContain('código temporal');
    $intento = DB::table('attendance_scan_attempts')->sole();
    expect($intento->result)->toBe('no_location')
        ->and($intento->distance_m)->toBeNull()
        ->and((int) $intento->student_id)->toBe($e['estudiante']->id);
});

it('CP-RF20-07 CP-RF26-02 rechaza una ubicación fuera del área con «Fuera del área de asistencia» y guarda la distancia', function () {
    $e = escenarioQr();

    // 0,01° de latitud son unos 1 112 m: muy fuera del radio de 100 m.
    $respuesta = escanear($e, qrProyectado($e), QR_LAT + 0.01, QR_LNG);

    expect($respuesta['resultado'])->toBe('out_of_range')
        ->and($respuesta['mensaje'])->toBe('Fuera del área de asistencia');
    $intento = DB::table('attendance_scan_attempts')->sole();
    expect($intento->result)->toBe('out_of_range')
        ->and((int) $intento->distance_m)->toBeGreaterThan(1100)->toBeLessThan(1125)
        ->and(DB::table('attendance_records')->count())->toBe(0);
});

it('CP-RF20-07 acepta una ubicación dentro de cualquier área activa, no solo la de la sesión', function () {
    $e = escenarioQr();
    DbContext::system(fn () => DB::table('attendance_areas')->insert([
        'name' => 'Prueba', 'area_type' => 'circle', 'center_lat' => 13.82197, 'center_lng' => -87.95553,
        'radius_m' => 5000, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]));

    $respuesta = escanear($e, qrProyectado($e), 13.82079, -87.9575);

    expect($respuesta['resultado'])->toBe('ok')
        ->and((int) DB::table('attendance_records')->sole()->distance_m)->toBeLessThan(300);
});

it('CP-RF20-07 rechaza una ubicación que no cae en ninguna área activa y guarda la distancia a la más cercana', function () {
    $e = escenarioQr();
    DbContext::system(fn () => DB::table('attendance_areas')->insert([
        'name' => 'Lejana', 'area_type' => 'circle', 'center_lat' => 13.82197, 'center_lng' => -87.95553,
        'radius_m' => 100, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]));

    $respuesta = escanear($e, qrProyectado($e), QR_LAT + 0.01, QR_LNG);

    expect($respuesta['resultado'])->toBe('out_of_range')
        ->and((int) DB::table('attendance_scan_attempts')->sole()->distance_m)->toBeLessThan(1125);
});

it('CP-RF20-07 acepta una ubicación dentro del radio y guarda la distancia', function () {
    $e = escenarioQr();

    // 0,0005° de latitud son unos 56 m.
    $respuesta = escanear($e, qrProyectado($e), QR_LAT + 0.0005, QR_LNG);

    expect($respuesta['resultado'])->toBe('ok')
        ->and((int) DB::table('attendance_records')->value('distance_m'))->toBeGreaterThan(50)->toBeLessThan(62);
});

it('CP-RF20-08 rechaza un token de dispositivo inválido, de otro estudiante o revocado', function (string $caso) {
    $e = escenarioQr();
    [$cookie, $local] = match ($caso) {
        'incorrecto' => ['token-equivocado', 'otro-equivocado'],
        'sin token' => [null, null],
        'de otro estudiante' => (function () {
            telefonoActivo(usuarioCon('estudiante'), 'token-de-un-companero');

            return ['token-de-un-companero', 'token-de-un-companero'];
        })(),
        'revocado' => (function () use ($e) {
            DbContext::system(fn () => DB::table('student_devices')->where('id', $e['dispositivo'])->update(['status' => 'revoked', 'revoked_at' => now()]));

            return [$e['token'], $e['token']];
        })(),
    };

    $respuesta = escanear($e, qrProyectado($e), cookie: $cookie, local: $local);

    expect($respuesta['resultado'])->toBe('invalid_device')
        ->and($respuesta['cookie'])->toBeNull()
        ->and($respuesta['local'])->toBeNull()
        ->and(resultadosGuardados($e))->toBe(['invalid_device'])
        ->and(DB::table('attendance_records')->count())->toBe(0)
        ->and(DB::table('device_events')->where('event_type', 'used')->count())->toBe(0);
})->with(['incorrecto', 'sin token', 'de otro estudiante', 'revocado']);

it('CP-RF20-08 registra la asistencia, actualiza last_used_at y escribe el evento used', function () {
    $e = escenarioQr();
    expect(DB::table('student_devices')->where('id', $e['dispositivo'])->value('last_used_at'))->toBeNull();

    $respuesta = escanear($e, qrProyectado($e), precision: 12.4);

    expect($respuesta['resultado'])->toBe('ok')
        ->and($respuesta['cookie'])->toBeNull()
        ->and($respuesta['local'])->toBeNull();
    $registro = DB::table('attendance_records')->where('enrollment_id', $e['matricula'])->sole();
    expect($registro->status)->toBe('present')
        ->and($registro->method)->toBe('qr')
        ->and((int) $registro->distance_m)->toBe(0)
        ->and((int) $registro->accuracy_m)->toBe(12)
        ->and((int) $registro->device_id)->toBe($e['dispositivo'])
        ->and(DB::table('student_devices')->where('id', $e['dispositivo'])->value('last_used_at'))->not->toBeNull()
        ->and(DB::table('device_events')->where('device_id', $e['dispositivo'])->pluck('event_type')->all())->toBe(['used'])
        ->and(resultadosGuardados($e))->toBe(['ok']);
});

it('CP-RF20-08 acepta solo la cookie y devuelve el token para reescribir el localStorage', function () {
    $e = escenarioQr();

    $respuesta = escanear($e, qrProyectado($e), cookie: $e['token'], local: null);

    expect($respuesta['resultado'])->toBe('ok')
        ->and($respuesta['local'])->toBe($e['token'])
        ->and($respuesta['cookie'])->toBeNull();
});

it('CP-RF20-08 acepta solo el localStorage y devuelve el token para reescribir la cookie', function () {
    $e = escenarioQr();

    $respuesta = escanear($e, qrProyectado($e), cookie: 'cookie-de-otro-telefono', local: $e['token']);

    expect($respuesta['resultado'])->toBe('ok')
        ->and($respuesta['cookie'])->toBe($e['token'])
        ->and($respuesta['local'])->toBeNull();
});

it('CP-RF20-08 registra presente aunque el escaneo llegue al final de la vigencia (sin tardanza con QR)', function () {
    $e = escenarioQr(5);
    $this->travel(290)->seconds();

    expect(escanear($e, qrProyectado($e))['resultado'])->toBe('ok')
        ->and(DB::table('attendance_records')->value('status'))->toBe('present');
});

it('CP-RF20-09 rechaza a un estudiante no matriculado en la sección', function () {
    $e = escenarioQr();
    $ajeno = usuarioCon('estudiante');
    telefonoActivo($ajeno, 'token-del-ajeno');

    $respuesta = escanear($e, qrProyectado($e), estudiante: $ajeno, cookie: 'token-del-ajeno', local: 'token-del-ajeno');

    expect($respuesta['resultado'])->toBe('not_enrolled')
        ->and($respuesta['mensaje'])->toBe('No está matriculado en esta sección.')
        ->and(resultadosGuardados($e, $ajeno))->toBe(['not_enrolled'])
        ->and(DB::table('attendance_records')->count())->toBe(0);
});

it('CP-RF20-09 rechaza a un estudiante con la matrícula retirada', function () {
    $e = escenarioQr();
    DbContext::system(fn () => DB::table('enrollments')->where('id', $e['matricula'])->update(['status' => 'withdrawn']));

    expect(escanear($e, qrProyectado($e))['resultado'])->toBe('not_enrolled');
});

it('CP-RF20-10 guarda cada intento, aceptado o rechazado, con su motivo exacto', function () {
    $e = escenarioQr();

    escanear($e, qrProyectado($e), null, null);
    escanear($e, qrProyectado($e), QR_LAT + 0.01);
    escanear($e, qrProyectado($e));

    $intentos = DB::table('attendance_scan_attempts')->orderBy('id')->get();
    expect($intentos->pluck('result')->all())->toBe(['no_location', 'out_of_range', 'ok'])
        ->and($intentos->pluck('session_id')->unique()->map(fn ($id) => (int) $id)->values()->all())
        ->toBe([(int) DB::table('attendance_sessions')->where('ulid', $e['sesion'])->value('id')]);
});

it('CP-RF20-10 muestra al docente los rechazos de la sesión', function () {
    $e = escenarioQr();
    escanear($e, qrProyectado($e), null, null);
    escanear($e, qrProyectado($e), null, null);
    actingAs($e['docente']);

    $sesion = app(AsistenciaService::class)->sesion($e['docente'], $e['seccion'], $e['sesion']);

    expect($sesion['rechazos'])->toHaveCount(2)
        ->and($sesion['rechazos']->pluck('result')->unique()->all())->toBe(['no_location'])
        ->and($sesion['rechazos']->first()->last_name)->toBe($e['estudiante']->last_name);
});

it('CP-RF20-10 no incluye los intentos aceptados entre los rechazos que ve el docente', function () {
    $e = escenarioQr();
    escanear($e, qrProyectado($e), QR_LAT + 0.01);
    escanear($e, qrProyectado($e));
    actingAs($e['docente']);

    $sesion = app(AsistenciaService::class)->sesion($e['docente'], $e['seccion'], $e['sesion']);

    expect($sesion['rechazos']->pluck('result')->all())->toBe(['out_of_range']);
});

it('CP-RF20-11 muestra a Soporte los intentos con su motivo y sin coordenadas', function () {
    $e = escenarioQr();
    escanear($e, qrProyectado($e), null, null);
    actingAs($e['soporte']);

    $intentos = app(DiagnosticoService::class)->intentos($e['soporte'], ['buscar' => 'GS21004']);
    $deHoy = app(DiagnosticoService::class)->intentos($e['soporte']);

    expect($intentos)->toHaveCount(1)
        ->and($intentos->first()->result)->toBe('no_location')
        ->and(DiagnosticoService::MOTIVOS[$intentos->first()->result])->toBe('Sin ubicación')
        ->and($deHoy->pluck('result')->all())->toBe(['no_location'])
        ->and(array_keys((array) $intentos->first()))->not->toContain('lat')->not->toContain('lng')
        ->not->toContain('center_lat')->not->toContain('center_lng');
});

it('CP-RF20-11 tiene un texto de motivo para cada resultado posible del intento', function () {
    expect(array_keys(DiagnosticoService::MOTIVOS))->toEqualCanonicalizing([
        'ok', 'no_location', 'token_expired', 'session_closed', 'not_enrolled', 'already_recorded', 'out_of_range', 'invalid_device', 'scan_limit',
    ])->and(array_keys(AsistenciaQrService::MENSAJES))->toEqualCanonicalizing(array_keys(DiagnosticoService::MOTIVOS));
});

it('CP-RF20-11 impide el diagnóstico de asistencia al Docente y al Estudiante', function (string $rol) {
    $e = escenario();

    expect(fn () => app(DiagnosticoService::class)->intentos($e[$rol]))->toThrow(AuthorizationException::class);
})->with(['docente', 'estudiante']);

it('CP-RF20-12 registra con código temporal a un estudiante sin teléfono', function () {
    $e = escenario();
    areaQr();
    $sesion = abrirSesionQr($e);
    $codigo = app(AsistenciaService::class)->generarCodigo($e['docente'], $e['seccion'], $sesion, $e['matricula']);
    actingAs($e['estudiante']);

    expect(app(AsistenciaService::class)->usarCodigo($e['estudiante'], $codigo))->toBe('PRN115');

    $registro = DB::table('attendance_records')->where('enrollment_id', $e['matricula'])->sole();
    expect($registro->method)->toBe('code')
        ->and($registro->status)->toBe('present')
        ->and($registro->device_id)->toBeNull()
        ->and(DB::table('student_devices')->where('student_id', $e['estudiante']->id)->count())->toBe(0);
});

it('impide marcar con QR a un docente sin guardar el intento', function () {
    $e = escenarioQr();
    $qr = qrProyectado($e);

    expect(fn () => servicioQr()->marcar($e['docente'], $qr, QR_LAT, QR_LNG, 10.0, null, null))->toThrow(AuthorizationException::class);
    expect(DB::table('attendance_scan_attempts')->count())->toBe(0);
});

it('impide proyectar el QR a un docente de otra sección y a un estudiante', function (string $quien) {
    $e = escenarioQr();
    $usuario = $quien === 'otro docente' ? usuarioCon('docente') : $e['estudiante'];
    actingAs($usuario);

    expect(fn () => servicioQr()->proyeccion($usuario, $e['seccion'], $e['sesion']))->toThrow(AuthorizationException::class);
    expect(DB::table('qr_tokens')->count())->toBe(0);
})->with(['otro docente', 'estudiante']);

it('proyecta el QR al docente de la sección con el conteo de presentes y los segundos restantes, un token por ventana', function () {
    $e = escenarioQr();
    actingAs($e['docente']);

    $proyeccion = servicioQr()->proyeccion($e['docente'], $e['seccion'], $e['sesion']);

    expect($proyeccion['abierta'])->toBeTrue()
        ->and($proyeccion['svg'])->toContain('<svg')
        ->and($proyeccion['presentes'])->toBe(0)
        ->and($proyeccion['segundos'])->toBe(600)
        ->and(DB::table('qr_tokens')->count())->toBe(1);

    servicioQr()->proyeccion($e['docente'], $e['seccion'], $e['sesion']);
    expect(DB::table('qr_tokens')->count())->toBe(1);

    $this->travel(10)->seconds();
    servicioQr()->proyeccion($e['docente'], $e['seccion'], $e['sesion']);
    expect(DB::table('qr_tokens')->count())->toBe(2);
});

it('rechaza un QR mal formado o de una sesión que no existe', function (string $qr) {
    $e = escenarioQr();
    actingAs($e['estudiante']);

    expect(fn () => servicioQr()->marcar($e['estudiante'], $qr, QR_LAT, QR_LNG, 10.0, $e['token'], $e['token']))->toThrow(ValidationException::class);
    expect(DB::table('attendance_scan_attempts')->count())->toBe(0);
})->with([
    'texto cualquiera' => 'hola',
    'url sin qr' => 'https://lms.ues.edu.sv/asistencia/escanear',
    'token corto' => '01J0000000000000000000000A.abc',
    'sesión inexistente' => '01J0000000000000000000000A.'.str_repeat('a', 64),
]);

it('purga los tokens del QR vencidos hace más de 7 días y conserva los recientes', function () {
    $e = escenarioQr();
    $sesionId = (int) DB::table('attendance_sessions')->where('ulid', $e['sesion'])->value('id');
    DbContext::system(fn () => DB::table('qr_tokens')->insert([
        ['session_id' => $sesionId, 'token_hash' => hash('sha256', 'viejo'), 'expires_at' => now()->subDays(8), 'created_at' => now()->subDays(8)],
        ['session_id' => $sesionId, 'token_hash' => hash('sha256', 'reciente'), 'expires_at' => now()->subDay(), 'created_at' => now()->subDay()],
    ]));

    expect(servicioQr()->purgarTokens())->toBe(1)
        ->and(DB::table('qr_tokens')->pluck('token_hash')->all())->toBe([hash('sha256', 'reciente')]);

    (new PurgarTokensQr)->handle(servicioQr());
    expect(DB::table('qr_tokens')->count())->toBe(1)
        ->and((new PurgarTokensQr)->queue)->toBe('default');
});

it('impide modificar o borrar los intentos de escaneo y los eventos del dispositivo', function (string $tabla) {
    $e = escenarioQr();
    escanear($e, qrProyectado($e));

    expect(fn () => DbContext::system(fn () => DB::table($tabla)->update(['occurred_at' => now()->subDay()])))->toThrow(QueryException::class);
    expect(fn () => DbContext::system(fn () => DB::table($tabla)->delete()))->toThrow(QueryException::class);
})->with(['attendance_scan_attempts', 'device_events'])
    ->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'La inmutabilidad por triggers solo existe en PostgreSQL.');

it('audita el registro de asistencia por QR con el evento attendance.qr y el estudiante como actor', function () {
    $e = escenarioQr();
    escanear($e, qrProyectado($e));

    DbContext::auditScope(all: true);
    $auditoria = DB::table('audit_logs')->where('entity_type', 'attendance_records')->where('action', 'insert')->get(['actor_user_id', 'event_type']);
    expect($auditoria)->toHaveCount(1)
        ->and($auditoria->first()->event_type)->toBe('attendance.qr')
        ->and((int) $auditoria->first()->actor_user_id)->toBe($e['estudiante']->id);
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'La auditoría por triggers solo existe en PostgreSQL.');

it('CP-RF20-04 proyecta el QR de la sesión al docente de la sección y no a un estudiante', function () {
    $e = escenarioQr();

    actingAs($e['docente'])->get(route('secciones.asistencia.proyectar', ['seccion' => $e['ulid'], 'sesion' => $e['sesion']]))
        ->assertOk()->assertSee('<svg', false)->assertSee(__('Presentes'))->assertDontSee($e['estudiante']->last_name);
    actingAs($e['estudiante'])->get(route('secciones.asistencia.proyectar', ['seccion' => $e['ulid'], 'sesion' => $e['sesion']]))->assertForbidden();
});

it('CP-RF20-02 la proyección muestra la sesión cerrada sin QR cuando venció', function () {
    $e = escenarioQr(5);
    $this->travel(301)->seconds();

    Livewire::actingAs($e['docente'])->test(ProyectarQr::class, ['seccion' => $e['ulid'], 'sesion' => $e['sesion']])
        ->assertSee(__('La sesión de asistencia está cerrada'))->assertDontSee(__('Código QR de asistencia'));
});

it('CP-RF20-08 marca desde la pantalla con la cookie y devuelve el token al localStorage que faltaba', function () {
    $e = escenarioQr();
    $qr = qrProyectado($e);

    Livewire::withCookie('device_token', $e['token'])->actingAs($e['estudiante'])->test(EscanearQr::class)
        ->call('marcar', $qr, QR_LAT, QR_LNG, 10.0, null)
        ->assertHasNoErrors()
        ->assertSet('resultado.resultado', 'ok')
        ->assertDispatched('dispositivo-registrado', token: $e['token'])
        ->assertSee(__('Asistencia registrada'));
});

it('CP-RF20-08 marca desde la pantalla con la copia de localStorage y vuelve a escribir la cookie', function () {
    $e = escenarioQr();
    $qr = qrProyectado($e);

    Livewire::actingAs($e['estudiante'])->test(EscanearQr::class)
        ->call('marcar', $qr, QR_LAT, QR_LNG, 10.0, $e['token'])
        ->assertSet('resultado.resultado', 'ok')
        ->assertNotDispatched('dispositivo-registrado');

    $cookie = Cookie::queued('device_token');
    expect($cookie?->getValue())->toBe($e['token'])
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->isHttpOnly())->toBeTrue();
});

it('CP-RF20-07 la pantalla muestra el rechazo con los intentos que le quedan', function () {
    $e = escenarioQr();
    $qr = qrProyectado($e);

    Livewire::actingAs($e['estudiante'])->test(EscanearQr::class)
        ->call('marcar', $qr, QR_LAT + 0.01, QR_LNG, 10.0, $e['token'])
        ->assertSet('resultado.resultado', 'out_of_range')
        ->assertSee(__('Fuera del área de asistencia'))
        ->assertSee(__('Le quedan :n intentos.', ['n' => 2]));
});

it('rechaza en la pantalla coordenadas imposibles y un QR que no es de asistencia', function () {
    $e = escenarioQr();

    Livewire::actingAs($e['estudiante'])->test(EscanearQr::class)
        ->call('marcar', qrProyectado($e), 200.0, QR_LNG, 10.0, $e['token'])->assertHasErrors(['latitud']);
    Livewire::actingAs($e['estudiante'])->test(EscanearQr::class)
        ->call('marcar', 'https://ejemplo.com/otra-cosa', QR_LAT, QR_LNG, 10.0, $e['token'])->assertHasErrors(['qr']);
    expect(resultadosGuardados($e))->toBe([]);
});

it('entrega al lector el texto para mostrar las coordenadas de la ubicación', function () {
    $e = escenario();

    Livewire::actingAs($e['estudiante'])->test(EscanearQr::class)->assertSee('Lat :lat, Lng :lng', false);
});

it('toma el QR de la URL cuando la cámara nativa abre la página', function () {
    $e = escenarioQr();
    $qr = qrProyectado($e);
    $valor = (string) str($qr)->after('qr=')->pipe('urldecode');

    Livewire::withQueryParams(['qr' => $valor])->actingAs($e['estudiante'])->test(EscanearQr::class)
        ->assertSet('qrInicial', $valor)->assertSee(__('Registrar asistencia'));
});

it('CP-RF20-05 tras un registro aceptado con rechazos previos responde «ya registró», no «sin intentos»', function () {
    $e = escenarioQr();
    $qr = qrProyectado($e);
    escanear($e, $qr, QR_LAT + 0.01);
    escanear($e, $qr, QR_LAT + 0.01);
    expect(escanear($e, $qr)['resultado'])->toBe('ok');

    expect(escanear($e, $qr)['resultado'])->toBe('already_recorded');
});

it('CP-RF20-04 acota la rotación del parámetro a 5–30 segundos al abrir la sesión', function (int $valor, int $esperado) {
    $e = escenario();
    DbContext::system(fn () => DB::table('system_settings')->updateOrInsert(['key' => 'qr_rotation_seconds_default'], ['value' => json_encode($valor)]));

    $ulid = app(AsistenciaService::class)->abrir($e['docente'], $e['seccion'], 10);

    expect((int) DB::table('attendance_sessions')->where('ulid', $ulid)->value('qr_rotation_seconds'))->toBe($esperado);
})->with([[0, 5], [90, 30]]);

/**
 * Intento de un estudiante con nombre conocido, para probar la búsqueda y los filtros del diagnóstico.
 *
 * @param  array<string, mixed>  $e
 */
function intentoDe(array $e, string $nombre, string $apellido, string $resultado, ?Carbon $cuando = null): User
{
    $estudiante = usuarioCon('estudiante');

    return DbContext::system(function () use ($e, $estudiante, $nombre, $apellido, $resultado, $cuando) {
        DB::table('users')->where('id', $estudiante->id)->update(['first_name' => $nombre, 'last_name' => $apellido]);
        DB::table('attendance_scan_attempts')->insert([
            'session_id' => DB::table('attendance_sessions')->where('ulid', $e['sesion'])->value('id'),
            'student_id' => $estudiante->id, 'result' => $resultado, 'occurred_at' => $cuando ?? now(),
        ]);

        return $estudiante->refresh();
    });
}

it('CP-RF20-13 busca al estudiante por el inicio de cada palabra del nombre, no por cualquier parte', function () {
    $e = escenarioQr();
    intentoDe($e, 'Jan Carlos', 'Pérez', 'out_of_range');
    intentoDe($e, 'Alejandra', 'Gómez', 'token_expired');
    actingAs($e['soporte']);

    $jan = app(DiagnosticoService::class)->intentos($e['soporte'], ['buscar' => 'Jan']);

    expect($jan)->toHaveCount(1)
        ->and($jan->first()->first_name)->toBe('Jan Carlos')
        ->and(app(DiagnosticoService::class)->intentos($e['soporte'], ['buscar' => 'pérez jan']))->toHaveCount(1)
        ->and(app(DiagnosticoService::class)->intentos($e['soporte'], ['buscar' => 'ale']))->toHaveCount(1)
        ->and(app(DiagnosticoService::class)->intentos($e['soporte'], ['buscar' => '%']))->toHaveCount(0);
});

it('CP-RF20-13 filtra el diagnóstico por rango de fechas y por motivo', function () {
    $e = escenarioQr();
    intentoDe($e, 'Jan Carlos', 'Pérez', 'out_of_range');
    intentoDe($e, 'Alejandra', 'Gómez', 'token_expired', now()->subDays(3));
    actingAs($e['soporte']);
    $diagnostico = app(DiagnosticoService::class);

    expect($diagnostico->intentos($e['soporte'], []))->toHaveCount(1)
        ->and($diagnostico->intentos($e['soporte'], ['desde' => now()->subDays(5)->toDateString(), 'hasta' => now()->toDateString()]))->toHaveCount(2)
        ->and($diagnostico->intentos($e['soporte'], ['desde' => now()->subDays(5)->toDateString(), 'hasta' => now()->subDays(2)->toDateString()])->pluck('result')->all())->toBe(['token_expired'])
        ->and($diagnostico->intentos($e['soporte'], ['desde' => now()->subDays(5)->toDateString(), 'motivo' => 'out_of_range'])->pluck('result')->all())->toBe(['out_of_range']);
});

it('CP-RF20-13 exporta los intentos del filtro en CSV sin coordenadas', function () {
    $e = escenarioQr();
    intentoDe($e, 'Jan Carlos', 'Pérez', 'out_of_range');
    actingAs($e['soporte']);

    $reporte = app(DiagnosticoService::class)->exportar($e['soporte'], []);
    $csv = Livewire::test(DiagnosticoAsistencia::class)->call('exportar', 'csv');

    expect($reporte['columnas'])->not->toContain('Latitud')->not->toContain('Longitud')
        ->and($reporte['filas'])->toHaveCount(1)
        ->and($reporte['filas'][0])->toContain('Jan Carlos Pérez')->toContain('Fuera del área')
        ->and($csv->effects['download']['name'] ?? '')->toContain('diagnostico-asistencia-');
});

/** Cambia el área del escenario por un cuadrado de unos 200 m de lado con el mismo centro. */
function areaQrPoligono(array $e): void
{
    DbContext::system(fn () => DB::table('attendance_areas')->where('id', $e['area'])->update([
        'area_type' => 'polygon', 'radius_m' => null,
        'polygon' => json_encode([[QR_LAT - 0.0009, QR_LNG - 0.0009], [QR_LAT - 0.0009, QR_LNG + 0.0009], [QR_LAT + 0.0009, QR_LNG + 0.0009], [QR_LAT + 0.0009, QR_LNG - 0.0009]]),
    ]));
}

it('CP-RF26-02 acepta marcar con QR dentro de un área por polígono', function () {
    $e = escenarioQr();
    areaQrPoligono($e);

    // Cerca de una esquina: fuera de un círculo de 100 m, pero dentro del cuadrado.
    $respuesta = escanear($e, qrProyectado($e), QR_LAT + 0.0008, QR_LNG + 0.0008);

    expect($respuesta['resultado'])->toBe('ok')
        ->and((int) DB::table('attendance_records')->sole()->distance_m)->toBe(0);
});

it('CP-RF26-02 rechaza marcar fuera de un área por polígono y guarda la distancia al borde', function () {
    $e = escenarioQr();
    areaQrPoligono($e);

    // 0,0018° al norte del centro: unos 100 m más allá del lado superior.
    $respuesta = escanear($e, qrProyectado($e), QR_LAT + 0.0018, QR_LNG);

    expect($respuesta['resultado'])->toBe('out_of_range')
        ->and($respuesta['mensaje'])->toBe('Fuera del área de asistencia')
        ->and((int) DB::table('attendance_scan_attempts')->sole()->distance_m)->toBeGreaterThan(98)->toBeLessThan(103)
        ->and(DB::table('attendance_records')->count())->toBe(0);
});

it('CP-RF20-02 la proyección tiene una salida a la sesión, abierta o cerrada', function () {
    $e = escenarioQr(5);
    $volver = 'href="'.route('secciones.asistencia.sesion', ['seccion' => $e['ulid'], 'sesion' => $e['sesion']]).'"';

    Livewire::actingAs($e['docente'])->test(ProyectarQr::class, ['seccion' => $e['ulid'], 'sesion' => $e['sesion']])
        ->assertSee(__('Salir de la proyección'))->assertSeeHtml($volver);

    $this->travel(301)->seconds();
    Livewire::actingAs($e['docente'])->test(ProyectarQr::class, ['seccion' => $e['ulid'], 'sesion' => $e['sesion']])
        ->assertSee(__('La sesión de asistencia está cerrada'))->assertSeeHtml($volver);
});
