<?php

use App\Models\User;
use App\Modules\Identidad\Livewire\Perfil;
use App\Modules\Identidad\Models\StudentDevice;
use App\Modules\Identidad\Notifications\DispositivoCambioAprobado;
use App\Modules\Identidad\Notifications\DispositivoCambioRechazado;
use App\Modules\Identidad\Services\DispositivoService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Inserta un dispositivo activo con el hash del token dado, como lo deja DispositivoService::registrar().
 */
function dispositivoActivo(User $estudiante, string $token): int
{
    return DbContext::system(fn () => DB::table('student_devices')->insertGetId([
        'student_id' => $estudiante->id, 'credential_id' => hash('sha256', $token), 'status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ]));
}

/**
 * Inserta una solicitud de cambio en el estado y fecha de resolución indicados.
 */
function solicitudDeCambio(User $estudiante, string $estado = 'pending', ?DateTimeInterface $resueltaEn = null): int
{
    return DbContext::system(fn () => DB::table('device_change_requests')->insertGetId([
        'student_id' => $estudiante->id, 'reason' => 'Perdí mi teléfono', 'status' => $estado,
        'resolved_at' => $resueltaEn, 'created_at' => now(), 'updated_at' => now(),
    ]));
}

function dispositivos(): DispositivoService
{
    return app(DispositivoService::class);
}

it('CP-RF27-01 deja el teléfono del estudiante sin dispositivo como su dispositivo activo y registra el evento', function () {
    $e = escenario();
    actingAs($e['estudiante']);

    $token = dispositivos()->registrar($e['estudiante']);

    $dispositivo = StudentDevice::query()->where('student_id', $e['estudiante']->id)->sole();
    expect($dispositivo->status)->toBe('active')
        ->and($token)->not->toBeEmpty()
        ->and(DB::table('device_events')->where('device_id', $dispositivo->id)->pluck('event_type')->all())->toBe(['registered']);
});

it('CP-RF27-01 guarda el SHA-256 del token y nunca el token en claro', function () {
    $e = escenario();
    actingAs($e['estudiante']);

    $token = dispositivos()->registrar($e['estudiante']);

    $guardado = DB::table('student_devices')->where('student_id', $e['estudiante']->id)->value('credential_id');
    expect($guardado)->toBe(hash('sha256', $token))->not->toBe($token);
});

it('CP-RF27-01 registra el teléfono desde el perfil del estudiante', function () {
    $e = escenario();
    actingAs($e['estudiante']);

    $componente = Livewire::test(Perfil::class, ['pestana' => 'dispositivo'])->call('registrarDispositivo')->assertHasNoErrors();

    $cookie = Cookie::queued('device_token');
    $token = $cookie?->getValue();
    expect($token)->not->toBeEmpty()
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax')
        ->and(DB::table('student_devices')->where('student_id', $e['estudiante']->id)->where('status', 'active')->value('credential_id'))->toBe(hash('sha256', $token));
    $componente->assertDispatched('dispositivo-registrado', token: $token);
});

it('CP-RF27-01 impide registrar el teléfono a Docente y Jefe de Departamento', function (string $rol) {
    $e = escenario();

    expect(fn () => dispositivos()->registrar($e[$rol]))->toThrow(AuthorizationException::class);
    expect(DB::table('student_devices')->count())->toBe(0);
})->with(['docente', 'jefe']);

it('CP-RF27-01 responde 403 si el Docente intenta registrar el teléfono desde el perfil', function () {
    $e = escenario();
    actingAs($e['docente']);

    Livewire::test(Perfil::class, ['pestana' => 'dispositivo'])->call('registrarDispositivo')->assertForbidden();

    expect(DB::table('student_devices')->count())->toBe(0);
});

it('CP-RF27-02 indica solicitar el cambio si el estudiante con dispositivo activo intenta registrar otro', function () {
    $e = escenario();
    dispositivoActivo($e['estudiante'], 'token-original');

    try {
        dispositivos()->registrar($e['estudiante']);
        $this->fail('Debió rechazar el segundo dispositivo.');
    } catch (ValidationException $excepcion) {
        expect($excepcion->errors()['dispositivo'][0])->toContain('solicite el cambio');
    }

    expect(DB::table('student_devices')->where('student_id', $e['estudiante']->id)->count())->toBe(1);
});

it('CP-RF27-02 muestra el aviso de solicitar el cambio en el perfil si ya tiene dispositivo activo', function () {
    $e = escenario();
    dispositivoActivo($e['estudiante'], 'token-original');
    actingAs($e['estudiante']);

    Livewire::test(Perfil::class, ['pestana' => 'dispositivo'])->call('registrarDispositivo')->assertHasErrors(['dispositivo']);

    expect(DB::table('student_devices')->where('student_id', $e['estudiante']->id)->count())->toBe(1);
});

it('CP-RF27-03 deja la solicitud de cambio con motivo pendiente para Soporte', function () {
    $e = escenario();

    dispositivos()->solicitarCambio($e['estudiante'], 'Perdí mi teléfono');

    $solicitud = DB::table('device_change_requests')->where('student_id', $e['estudiante']->id)->sole();
    expect($solicitud->status)->toBe('pending')
        ->and($solicitud->reason)->toBe('Perdí mi teléfono');
    expect(dispositivos()->solicitudes($e['soporte'])->pluck('student_id')->all())->toBe([$e['estudiante']->id]);
});

it('CP-RF27-03 impide solicitar el cambio a quien no es estudiante', function () {
    $e = escenario();

    expect(fn () => dispositivos()->solicitarCambio($e['docente'], 'Quiero cambiar'))->toThrow(AuthorizationException::class);
    expect(DB::table('device_change_requests')->count())->toBe(0);
});

it('CP-RF27-04 exige una justificación extra si el último cambio fue hace menos de 30 días', function () {
    $e = escenario();
    solicitudDeCambio($e['estudiante'], 'approved', now()->subDays(10));

    try {
        dispositivos()->solicitarCambio($e['estudiante'], 'Otra vez');
        $this->fail('Debió exigir la justificación.');
    } catch (ValidationException $excepcion) {
        expect($excepcion->errors())->toHaveKey('justificacion');
    }
    expect(DB::table('device_change_requests')->where('status', 'pending')->count())->toBe(0);

    dispositivos()->solicitarCambio($e['estudiante'], 'Otra vez', 'justificaciones-dispositivo/constancia.pdf');

    expect(DB::table('device_change_requests')->where('status', 'pending')->value('justification_path'))->toBe('justificaciones-dispositivo/constancia.pdf');
});

it('CP-RF27-04 no exige justificación cuando el último cambio fue hace más de 30 días', function () {
    $e = escenario();
    solicitudDeCambio($e['estudiante'], 'approved', now()->subDays(10));

    $this->travel(21)->days();
    dispositivos()->solicitarCambio($e['estudiante'], 'Cambio normal');

    expect(DB::table('device_change_requests')->where('status', 'pending')->where('reason', 'Cambio normal')->count())->toBe(1);
});

it('CP-RF27-05 revoca el dispositivo anterior, deja el evento revoked y avisa al estudiante cuando Soporte aprueba', function () {
    Notification::fake();
    $e = escenario();
    actingAs($e['estudiante']);
    $token = dispositivos()->registrar($e['estudiante']);
    $dispositivoId = StudentDevice::query()->where('student_id', $e['estudiante']->id)->value('id');
    $solicitud = solicitudDeCambio($e['estudiante']);

    actingAs($e['soporte']);
    dispositivos()->aprobar($e['soporte'], $solicitud);

    $anterior = DB::table('student_devices')->find($dispositivoId);
    expect($anterior->status)->toBe('revoked')
        ->and($anterior->revoked_by)->toBe($e['soporte']->id)
        ->and($anterior->revoked_at)->not->toBeNull()
        ->and(DB::table('device_events')->where('device_id', $dispositivoId)->pluck('event_type')->sort()->values()->all())->toBe(['registered', 'revoked'])
        ->and(DB::table('device_change_requests')->where('id', $solicitud)->value('status'))->toBe('approved')
        ->and(dispositivos()->validar($e['estudiante'], $token, $token))->toBeNull();

    Notification::assertSentTo($e['estudiante'], DispositivoCambioAprobado::class, fn ($aviso) => $aviso->queue === 'notificaciones');
    Notification::assertNotSentTo($e['soporte'], DispositivoCambioAprobado::class);
});

it('CP-RF27-05 permite registrar el teléfono nuevo después de aprobar el cambio', function () {
    Notification::fake();
    $e = escenario();
    actingAs($e['estudiante']);
    $anterior = dispositivos()->registrar($e['estudiante']);
    actingAs($e['soporte']);
    dispositivos()->aprobar($e['soporte'], solicitudDeCambio($e['estudiante']));

    actingAs($e['estudiante']);
    $nuevo = dispositivos()->registrar($e['estudiante']);

    expect(dispositivos()->validar($e['estudiante'], $nuevo, null))->not->toBeNull()
        ->and(dispositivos()->validar($e['estudiante'], $anterior, null))->toBeNull();
});

it('CP-RF27-05 rechaza el cambio, avisa con el motivo y conserva el dispositivo activo', function () {
    Notification::fake();
    $e = escenario();
    dispositivoActivo($e['estudiante'], 'token-original');
    $solicitud = solicitudDeCambio($e['estudiante']);

    dispositivos()->rechazar($e['soporte'], $solicitud, 'No coincide la identidad');

    expect(DB::table('device_change_requests')->where('id', $solicitud)->value('status'))->toBe('rejected')
        ->and(DB::table('student_devices')->where('student_id', $e['estudiante']->id)->value('status'))->toBe('active');
    Notification::assertSentTo($e['estudiante'], DispositivoCambioRechazado::class, fn ($aviso) => $aviso->motivo === 'No coincide la identidad'
        && str_contains($aviso->cuerpo, 'No coincide la identidad')
        && $aviso->queue === 'notificaciones');
    Notification::assertNotSentTo($e['estudiante'], DispositivoCambioAprobado::class);
});

it('CP-RF27-05 deja en la bandeja del estudiante el aviso con el motivo del rechazo', function () {
    Notification::fake();
    $e = escenario();
    $solicitud = solicitudDeCambio($e['estudiante']);

    dispositivos()->rechazar($e['soporte'], $solicitud, 'Falta evidencia');

    $fila = DB::table('notifications')->where('notifiable_id', $e['estudiante']->id)->where('event_code', 'device_request.resolved')->sole();
    expect(json_decode($fila->data, true)['body'])->toContain('Falta evidencia');
});

it('CP-RF27-05 impide al Estudiante y al Docente aprobar o rechazar solicitudes de cambio', function (string $rol) {
    Notification::fake();
    $e = escenario();
    $solicitud = solicitudDeCambio($e['estudiante']);

    expect(fn () => dispositivos()->aprobar($e[$rol], $solicitud))->toThrow(AuthorizationException::class);
    expect(fn () => dispositivos()->rechazar($e[$rol], $solicitud, 'Sin permiso'))->toThrow(AuthorizationException::class);

    expect(DB::table('device_change_requests')->where('id', $solicitud)->value('status'))->toBe('pending');
    Notification::assertNothingSent();
})->with(['estudiante', 'docente']);

it('CP-RF27-05 registra la aprobación en la auditoría', function () {
    Notification::fake();
    $e = escenario();
    dispositivoActivo($e['estudiante'], 'token-original');

    actingAs($e['soporte']);
    dispositivos()->aprobar($e['soporte'], solicitudDeCambio($e['estudiante']));

    DbContext::auditScope(all: true);
    $auditoria = DB::table('audit_logs')->where('actor_user_id', $e['soporte']->id)->get(['entity_type', 'action', 'event_type']);
    expect($auditoria->where('entity_type', 'student_devices')->where('action', 'update')->where('event_type', 'device.change_approved'))->not->toBeEmpty();
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'La auditoría por triggers solo existe en PostgreSQL.');

it('valida el dispositivo con el token de la cookie', function () {
    $e = escenario();
    dispositivoActivo($e['estudiante'], 'token-cookie');

    $dispositivo = dispositivos()->validar($e['estudiante'], 'token-cookie', null);

    expect($dispositivo)->toBeInstanceOf(StudentDevice::class)
        ->and($dispositivo->student_id)->toBe($e['estudiante']->id);
});

it('valida el dispositivo con el token de localStorage cuando la cookie falta', function () {
    $e = escenario();
    dispositivoActivo($e['estudiante'], 'token-local');

    expect(dispositivos()->validar($e['estudiante'], null, 'token-local'))->not->toBeNull()
        ->and(dispositivos()->validar($e['estudiante'], 'token-equivocado', 'token-local'))->not->toBeNull();
});

it('no valida el dispositivo sin token, con un token incorrecto o ya revocado', function (?string $cookie, ?string $local) {
    $e = escenario();
    dispositivoActivo($e['estudiante'], 'token-real');

    expect(dispositivos()->validar($e['estudiante'], $cookie, $local))->toBeNull();
})->with([
    'sin tokens' => [null, null],
    'tokens vacíos' => ['', ''],
    'token incorrecto' => ['token-equivocado', 'otro-equivocado'],
]);

it('no valida un dispositivo revocado aunque el token coincida', function () {
    $e = escenario();
    $id = dispositivoActivo($e['estudiante'], 'token-real');
    DbContext::system(fn () => DB::table('student_devices')->where('id', $id)->update(['status' => 'revoked', 'revoked_at' => now()]));

    expect(dispositivos()->validar($e['estudiante'], 'token-real', 'token-real'))->toBeNull();
});

it('no valida a otro estudiante con el token del dispositivo de un compañero', function () {
    $e = escenario();
    $otro = usuarioCon('estudiante');
    dispositivoActivo($e['estudiante'], 'token-de-a');
    dispositivoActivo($otro, 'token-de-b');

    expect(dispositivos()->validar($otro, 'token-de-a', 'token-de-a'))->toBeNull()
        ->and(dispositivos()->validar($otro, 'token-de-b', null)?->student_id)->toBe($otro->id);
});

it('CP-RF27-08 muestra el modal de alerta si el estudiante no tiene dispositivo registrado', function () {
    $e = escenario();

    actingAs($e['estudiante'])
        ->get(route('inicio'))
        ->assertOk()
        ->assertSeeText(['Registre su dispositivo de asistencia', 'Aún no tiene un dispositivo móvil de confianza registrado en su cuenta.', 'Registrar dispositivo', 'Más tarde'])
        ->assertSee(route('perfil.dispositivo'));
});

it('CP-RF27-08 no muestra el modal de alerta si el estudiante ya tiene un dispositivo activo', function () {
    $e = escenario();
    dispositivoActivo($e['estudiante'], 'token-existente');

    actingAs($e['estudiante'])
        ->get(route('inicio'))
        ->assertOk()
        ->assertDontSeeText('Registre su dispositivo de asistencia');
});

it('CP-RF27-08 no muestra el modal de alerta si el usuario no es estudiante', function () {
    $e = escenario();

    actingAs($e['docente'])
        ->get(route('inicio'))
        ->assertOk()
        ->assertDontSeeText('Registre su dispositivo de asistencia');
});

it('CP-RF27-08 no muestra el modal de alerta en la vista de perfil dispositivo', function () {
    $e = escenario();

    actingAs($e['estudiante'])
        ->get(route('perfil.dispositivo'))
        ->assertOk()
        ->assertDontSee('modal-alerta-dispositivo-titulo', false);
});
