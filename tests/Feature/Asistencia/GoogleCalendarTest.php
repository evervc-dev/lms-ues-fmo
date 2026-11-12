<?php

use App\Models\User;
use App\Modules\Asistencia\Jobs\CancelarEventoDeClase;
use App\Modules\Asistencia\Services\AsistenciaService;
use App\Modules\Asistencia\Services\CalendarConexionService;
use App\Modules\Asistencia\Services\CalendarFalso;
use App\Modules\Asistencia\Services\CalendarGateway;
use App\Modules\Asistencia\Services\CalendarGoogle;
use App\Modules\Asistencia\Services\ReporteMeetService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

/**
 * Google Calendar real (RF21, ADR-018) contra respuestas falsas de Google (`Http::fake`): conexión OAuth del docente,
 * solo con cuentas @ues.edu.sv, y creación del evento con enlace de Meet.
 */
beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-23 10:00'));
    config([
        'services.google.client_id' => 'cliente-prueba.apps.googleusercontent.com',
        'services.google.client_secret' => 'secreto-prueba',
        'services.google.calendar.enabled' => true,
    ]);
});

/**
 * `id_token` sin firmar con los claims dados (el servicio solo lee la carga, que llega por TLS desde Google).
 *
 * @param  array<string, mixed>  $claims
 */
function idTokenDePrueba(array $claims): string
{
    $b64 = fn (array $datos) => rtrim(strtr(base64_encode((string) json_encode($datos)), '+/', '-_'), '=');

    return $b64(['alg' => 'none']).'.'.$b64($claims + ['iss' => 'https://accounts.google.com', 'aud' => 'cliente-prueba.apps.googleusercontent.com']).'.firma';
}

function conectarCalendar(User $docente): void
{
    DbContext::system(fn () => $docente->forceFill(['google_calendar_token' => 'refresh-de-prueba'])->save());
}

function fakeGoogle(): void
{
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'acceso-de-prueba', 'expires_in' => 3599]),
        'www.googleapis.com/calendar/*' => Http::response(['id' => 'evt123', 'hangoutLink' => 'https://meet.google.com/aaa-bbbb-ccc']),
    ]);
}

it('CP-RF21-01 con Calendar habilitado, la clase crea el evento con Meet en el calendario del docente e invita a los matriculados', function () {
    $e = escenario();
    conectarCalendar($e['docente']);
    fakeGoogle();
    actingAs($e['docente']);

    expect(app(CalendarGateway::class))->toBeInstanceOf(CalendarGoogle::class);
    $id = app(ReporteMeetService::class)->programar($e['docente'], $e['seccion'], 'Recursividad', '2026-10-30 07:00', 90, null);

    Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://www.googleapis.com/calendar/v3/calendars/primary/events')
        && str_contains($r->url(), 'conferenceDataVersion=1') && str_contains($r->url(), 'sendUpdates=all')
        && $r->hasHeader('Authorization', 'Bearer acceso-de-prueba')
        && $r['summary'] === 'Recursividad'
        && $r['start']['dateTime'] === '2026-10-30T07:00:00-06:00' && $r['end']['dateTime'] === '2026-10-30T08:30:00-06:00'
        && $r['attendees'] === [['email' => $e['estudiante']->institutional_email]]
        && $r['conferenceData']['createRequest']['conferenceSolutionKey']['type'] === 'hangoutsMeet');
    $clase = DB::table('video_sessions')->find($id);
    expect($clase->google_event_id)->toBe('evt123')->and($clase->meet_url)->toBe('https://meet.google.com/aaa-bbbb-ccc');
});

it('CP-RF21-01 solo invita correos @ues.edu.sv aunque la lista traiga otros', function () {
    $docente = usuarioCon('docente');
    conectarCalendar($docente);
    fakeGoogle();

    app(CalendarGoogle::class)->crearClase($docente, 'Repaso', now()->addDay(), 60, ['ab12345@ues.edu.sv', 'alguien@gmail.com', 'otro@ues.edu.sv.evil.com', 'AB12345@UES.EDU.SV']);

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/events') && $r['attendees'] === [['email' => 'ab12345@ues.edu.sv']]);
});

it('CP-RF21-01 si el docente no conectó su cuenta, la clase se programa sin evento y sin llamar a Google', function () {
    $e = escenario();
    Http::fake();
    actingAs($e['docente']);

    $id = app(ReporteMeetService::class)->programar($e['docente'], $e['seccion'], 'Recursividad', '2026-10-30 07:00', 90, null);

    Http::assertNothingSent();
    expect(DB::table('video_sessions')->find($id)->google_event_id)->toBeNull();
});

it('CP-RF21-01 si Google revocó el permiso, borra el token para pedirlo de nuevo y no crea el evento', function () {
    $docente = usuarioCon('docente');
    conectarCalendar($docente);
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);

    expect(app(CalendarGoogle::class)->crearClase($docente, 'Repaso', now()->addDay(), 60, []))->toBeNull()
        ->and($docente->fresh()->google_calendar_token)->toBeNull();
    Http::assertSentCount(1);
});

it('CP-RF21-01 conectar envía a Google con calendar.events, acceso sin conexión y el dominio @ues.edu.sv', function () {
    $docente = usuarioCon('docente');

    $respuesta = actingAs($docente)->get(route('perfil.google-calendar.conectar'));

    $url = (string) $respuesta->headers->get('Location');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
    expect($url)->toStartWith('https://accounts.google.com/o/oauth2/v2/auth')
        ->and($q)->toMatchArray(['hd' => 'ues.edu.sv', 'access_type' => 'offline', 'prompt' => 'consent', 'login_hint' => $docente->institutional_email,
            'redirect_uri' => url('/perfil/google-calendar/callback'), 'state' => session('google_calendar_state')])
        ->and($q['scope'])->toContain(CalendarConexionService::SCOPE);
});

it('CP-RF21-01 el callback guarda cifrado el refresh token de la misma cuenta institucional', function () {
    $docente = usuarioCon('docente');
    Http::fake(['oauth2.googleapis.com/token' => Http::response([
        'access_token' => 'a', 'refresh_token' => 'refresh-nuevo', 'scope' => 'openid '.CalendarConexionService::SCOPE.' https://www.googleapis.com/auth/userinfo.email',
        'id_token' => idTokenDePrueba(['email' => $docente->institutional_email, 'email_verified' => true, 'hd' => 'ues.edu.sv']),
    ])]);

    actingAs($docente)->withSession(['google_calendar_state' => 'estado-ok'])
        ->get(route('perfil.google-calendar.callback', ['state' => 'estado-ok', 'code' => 'codigo']))
        ->assertRedirect(route('perfil.google-calendar'))->assertSessionHasNoErrors();

    expect($docente->fresh()->google_calendar_token)->toBe('refresh-nuevo')
        ->and(DB::table('users')->where('id', $docente->id)->value('google_calendar_token'))->not->toBe('refresh-nuevo');
});

it('CP-RF21-01 el callback rechaza una cuenta fuera de @ues.edu.sv o distinta de la del docente', function (array $claims) {
    $docente = usuarioCon('docente');
    $claims = array_map(fn ($v) => $v === ':docente' ? $docente->institutional_email : $v, $claims);
    Http::fake(['oauth2.googleapis.com/token' => Http::response([
        'refresh_token' => 'r', 'scope' => CalendarConexionService::SCOPE, 'id_token' => idTokenDePrueba($claims),
    ])]);

    actingAs($docente)->withSession(['google_calendar_state' => 'estado-ok'])
        ->get(route('perfil.google-calendar.callback', ['state' => 'estado-ok', 'code' => 'codigo']))
        ->assertRedirect(route('perfil.google-calendar'))->assertSessionHasErrors('google');

    expect($docente->fresh()->google_calendar_token)->toBeNull();
})->with([
    'gmail' => [['email' => 'alguien@gmail.com', 'email_verified' => true]],
    'hd falso' => [['email' => 'alguien@gmail.com', 'email_verified' => true, 'hd' => 'ues.edu.sv']],
    'otra cuenta del dominio' => [['email' => 'otra99999@ues.edu.sv', 'email_verified' => true, 'hd' => 'ues.edu.sv']],
    'sin verificar' => [['email' => ':docente', 'email_verified' => false, 'hd' => 'ues.edu.sv']],
]);

it('CP-RF21-01 el callback con un state distinto no canjea el código', function () {
    $docente = usuarioCon('docente');
    Http::fake();

    actingAs($docente)->withSession(['google_calendar_state' => 'estado-ok'])
        ->get(route('perfil.google-calendar.callback', ['state' => 'otro', 'code' => 'codigo']))
        ->assertSessionHasErrors('google');

    Http::assertNothingSent();
});

it('CP-RF21-01 la página de Google Calendar es del docente y el estudiante recibe 403', function () {
    actingAs(usuarioCon('docente'))->get(route('perfil.google-calendar'))->assertOk()->assertSeeText(['Sin conectar', 'Conectar con Google']);
    actingAs(usuarioCon('docente'))->get(route('perfil'))->assertOk()->assertSeeTextInOrder(['Datos personales', 'Google Calendar']);
    actingAs(usuarioCon('estudiante'))->get(route('perfil'))->assertOk()->assertDontSeeText('Google Calendar');
    actingAs(usuarioCon('estudiante'))->get(route('perfil.google-calendar'))->assertForbidden();
    actingAs(usuarioCon('estudiante'))->get(route('perfil.google-calendar.conectar'))->assertForbidden();
});

it('CP-RF21-01 el docente desconecta y el token se revoca en Google', function () {
    $docente = usuarioCon('docente');
    conectarCalendar($docente);
    Http::fake(['oauth2.googleapis.com/revoke' => Http::response()]);

    actingAs($docente);
    app(CalendarConexionService::class)->desconectar($docente);

    Http::assertSent(fn (Request $r) => $r->url() === 'https://oauth2.googleapis.com/revoke' && $r['token'] === 'refresh-de-prueba');
    expect($docente->fresh()->google_calendar_token)->toBeNull();
});

it('google:calendar-prueba crea hoy un evento con Meet para una cuenta conectada', function () {
    $docente = usuarioCon('docente');
    conectarCalendar($docente);
    fakeGoogle();

    artisan('google:calendar-prueba', ['correo' => $docente->institutional_email])
        ->expectsOutputToContain('https://meet.google.com/aaa-bbbb-ccc')->assertSuccessful();

    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/events')
        && $r['start']['dateTime'] === '2026-10-23T10:30:00-06:00'
        && $r['attendees'] === [['email' => $docente->institutional_email]]);
});

it('google:calendar-prueba rechaza correos fuera de @ues.edu.sv y cuentas sin conectar', function () {
    Http::fake();
    $docente = usuarioCon('docente');

    artisan('google:calendar-prueba', ['correo' => 'alguien@gmail.com'])->assertFailed();
    artisan('google:calendar-prueba', ['correo' => $docente->institutional_email, '--invitar' => ['x@gmail.com']])->assertFailed();
    artisan('google:calendar-prueba', ['correo' => $docente->institutional_email])->expectsOutputToContain('no ha conectado')->assertFailed();

    Http::assertNothingSent();
});

it('CP-RF21-01 al aprobar la cancelación del día se borra el evento de la clase virtual y Google avisa a los invitados', function () {
    $e = escenario();
    conectarCalendar($e['docente']);
    fakeGoogle();
    actingAs($e['docente']);
    $servicio = app(ReporteMeetService::class);
    $cancelada = $servicio->programar($e['docente'], $e['seccion'], 'Recursividad', '2026-10-30 07:00', 90, null);
    $otroDia = $servicio->programar($e['docente'], $e['seccion'], 'Pilas', '2026-10-29 07:00', 90, null);
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'acceso-de-prueba']), 'www.googleapis.com/calendar/*' => Http::response(status: 204)]);

    app(AsistenciaService::class)->solicitarCancelacion($e['docente'], $e['seccion'], '2026-10-30', 'cancellation', 'Fumigación del edificio.');
    actingAs($e['coordinador']);
    app(AsistenciaService::class)->resolverCancelacion($e['coordinador'], (int) DB::table('class_cancellations')->value('id'), true);

    $borrados = Http::recorded(fn (Request $r) => $r->method() === 'DELETE');
    expect($borrados)->toHaveCount(1)
        ->and($borrados->first()[0]->url())->toContain('/events/evt123')->toContain('sendUpdates=all');
    expect(DB::table('video_sessions')->where('id', $cancelada)->value('google_event_id'))->toBeNull()
        ->and(DB::table('video_sessions')->where('id', $otroDia)->value('google_event_id'))->toBe('evt123');
});

it('CP-RF21-01 un cambio de modalidad no borra el evento de la clase virtual', function () {
    $e = escenario();
    $calendario = new CalendarFalso;
    app()->instance(CalendarGateway::class, $calendario);
    actingAs($e['docente']);
    app(ReporteMeetService::class)->programar($e['docente'], $e['seccion'], 'Recursividad', '2026-10-30 07:00', 90, null);

    app(AsistenciaService::class)->solicitarCancelacion($e['docente'], $e['seccion'], '2026-10-30', 'modality_change', 'Lluvia.', 'virtual');
    actingAs($e['coordinador']);
    app(AsistenciaService::class)->resolverCancelacion($e['coordinador'], (int) DB::table('class_cancellations')->value('id'), true);

    expect($calendario->cancelados)->toBe([]);
});

it('CP-RF21-01 el evento de la clase cancelada se busca en el calendario de cada docente vigente de la sección', function () {
    $e = escenario();
    $coDocente = usuarioCon('docente');
    DB::table('teaching_assignments')->insert(['section_id' => $e['seccion'], 'teacher_id' => $coDocente->id, 'is_responsible' => false, 'started_at' => '2026-07-01', 'created_at' => now(), 'updated_at' => now()]);
    conectarCalendar($e['docente']);
    conectarCalendar($coDocente);
    $clase = DbContext::system(fn () => DB::table('video_sessions')->insertGetId(['section_id' => $e['seccion'], 'topic' => 'Árboles', 'starts_at' => '2026-10-30 07:00',
        'duration_minutes' => 60, 'google_event_id' => 'evt-co', 'created_at' => now(), 'updated_at' => now()]));
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'acceso-de-prueba']),
        'www.googleapis.com/calendar/*' => Http::sequence()->push(['error' => ['code' => 404]], 404)->pushStatus(204),
    ]);

    (new CancelarEventoDeClase($clase))->handle(app(CalendarGateway::class));

    expect(Http::recorded(fn (Request $r) => $r->method() === 'DELETE'))->toHaveCount(2);
    expect(DB::table('video_sessions')->where('id', $clase)->value('google_event_id'))->toBeNull();
});
