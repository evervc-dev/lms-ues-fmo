<?php

use App\Modules\Asistencia\Services\AsistenciaQrService;
use App\Modules\Asistencia\Services\AsistenciaService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Flujo crítico 2 en navegador (Estrategia de pruebas, RF20): éxito, token rotado, sesión vencida, límite de
 * intentos, fuera del área y dispositivo no autorizado. El estudiante abre «Marcar asistencia» con el QR en la URL
 * (como si la cámara lo hubiera leído), con la ubicación simulada y el token de dispositivo en localStorage; así
 * corre el JavaScript del lector y la llamada real a Livewire. La cámara no se prueba: un navegador sin pantalla no
 * tiene una que apuntar al proyector.
 */
const NAV_QR_LAT = 13.4393;

const NAV_QR_LNG = -88.1597;

/**
 * @return array<string, mixed>
 */
function asistenciaQrEnNavegador(): array
{
    $e = escenario();
    DbContext::system(function () use ($e) {
        DB::table('attendance_areas')->insert(['name' => 'Campus FMO', 'area_type' => 'circle', 'center_lat' => NAV_QR_LAT, 'center_lng' => NAV_QR_LNG,
            'radius_m' => 100, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('student_devices')->insert(['student_id' => $e['estudiante']->id, 'credential_id' => hash('sha256', 'token-del-telefono'),
            'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    });
    test()->actingAs($e['docente']);
    $e['sesion'] = app(AsistenciaService::class)->abrir($e['docente'], $e['seccion'], 10);

    return $e;
}

/**
 * Contenido del QR que proyecta ahora la sesión (lo emite la pantalla del docente).
 *
 * @param  array<string, mixed>  $e
 */
function qrQueProyectaLaSesion(array $e): string
{
    test()->actingAs($e['docente']);
    $qr = app(AsistenciaQrService::class);
    $qr->proyeccion($e['docente'], $e['seccion'], $e['sesion']);
    $rotacion = (int) DB::table('attendance_sessions')->where('ulid', $e['sesion'])->value('qr_rotation_seconds');

    return $qr->contenido($e['sesion'], hash_hmac('sha256', 'qr|'.$e['sesion'].'|'.intdiv(now()->getTimestamp(), $rotacion), (string) config('app.key')));
}

/**
 * Abre «Marcar asistencia» como el estudiante con el QR en la URL, la ubicación dada y, si se indica, el token en
 * localStorage; pulsa «Registrar asistencia» y espera el resultado del servidor en la página.
 *
 * @param  array<string, mixed>  $e
 */
function marcarDesdeElNavegador(array $e, string $qr, string $esperado, float $lat = NAV_QR_LAT, ?string $tokenLocal = 'token-del-telefono'): mixed
{
    test()->actingAs($e['estudiante']);
    $pagina = visit(route('asistencia.escanear', ['qr' => (string) str($qr)->after('qr=')->pipe('urldecode')]))->geolocation($lat, NAV_QR_LNG);
    $pagina->script($tokenLocal === null ? 'localStorage.removeItem("device_token")' : 'localStorage.setItem("device_token", '.json_encode($tokenLocal).')');

    return $pagina->click('Registrar asistencia')->waitForText($esperado)->assertDontSee('undefined');
}

it('CP-RF20-08 registra la asistencia desde el navegador con la ubicación y el token del teléfono', function () {
    $e = asistenciaQrEnNavegador();

    marcarDesdeElNavegador($e, qrQueProyectaLaSesion($e), 'Asistencia registrada')
        ->assertSee('Registro en el servidor');

    expect(DB::table('attendance_records')->where('method', 'qr')->where('status', 'present')->count())->toBe(1)
        ->and(DB::table('attendance_scan_attempts')->pluck('result')->all())->toBe(['ok']);
});

it('CP-RF20-08 rechaza en el navegador un teléfono que no es el registrado', function () {
    $e = asistenciaQrEnNavegador();

    marcarDesdeElNavegador($e, qrQueProyectaLaSesion($e), 'Este teléfono no es su dispositivo registrado', tokenLocal: null);

    expect(DB::table('attendance_scan_attempts')->pluck('result')->all())->toBe(['invalid_device']);
});

it('CP-RF20-07 rechaza en el navegador una ubicación fuera del área', function () {
    $e = asistenciaQrEnNavegador();

    marcarDesdeElNavegador($e, qrQueProyectaLaSesion($e), 'Fuera del área de asistencia', lat: NAV_QR_LAT + 0.01)
        ->assertSee('Le quedan 2 intentos.');

    expect(DB::table('attendance_scan_attempts')->pluck('result')->all())->toBe(['out_of_range']);
});

it('CP-RF20-01 rechaza en el navegador un token que ya rotó', function () {
    $e = asistenciaQrEnNavegador();
    $sesionId = (int) DB::table('attendance_sessions')->where('ulid', $e['sesion'])->value('id');
    DB::table('qr_tokens')->insert(['session_id' => $sesionId, 'token_hash' => hash('sha256', str_repeat('a', 64)), 'expires_at' => now()->subSeconds(30), 'created_at' => now()->subSeconds(40)]);

    marcarDesdeElNavegador($e, app(AsistenciaQrService::class)->contenido($e['sesion'], str_repeat('a', 64)), 'Código vencido; escanee el QR actual');

    expect(DB::table('attendance_scan_attempts')->pluck('result')->all())->toBe(['token_expired']);
});

it('CP-RF20-02 rechaza en el navegador una sesión cuya vigencia terminó', function () {
    $e = asistenciaQrEnNavegador();
    $qr = qrQueProyectaLaSesion($e);
    DbContext::system(fn () => DB::table('attendance_sessions')->where('ulid', $e['sesion'])->update(['opened_at' => now()->subMinutes(11)]));

    marcarDesdeElNavegador($e, $qr, 'La sesión de asistencia está cerrada');

    expect(DB::table('attendance_scan_attempts')->pluck('result')->all())->toBe(['session_closed']);
});

it('CP-RF20-03 rechaza en el navegador al estudiante que ya usó sus 3 intentos', function () {
    $e = asistenciaQrEnNavegador();
    $sesionId = (int) DB::table('attendance_sessions')->where('ulid', $e['sesion'])->value('id');
    DB::table('attendance_scan_attempts')->insert(array_fill(0, 3, ['session_id' => $sesionId, 'student_id' => $e['estudiante']->id, 'result' => 'out_of_range', 'occurred_at' => now()]));

    marcarDesdeElNavegador($e, qrQueProyectaLaSesion($e), 'Ya usó sus 3 intentos en esta sesión')
        ->assertSee('código temporal');

    expect(DB::table('attendance_scan_attempts')->where('result', 'scan_limit')->count())->toBe(1);
});
