<?php

use App\Models\User;
use App\Modules\Academica\Jobs\SincronizarEventoDePeriodo;
use App\Modules\Academica\Livewire\CalendarioEvaluaciones;
use App\Modules\Academica\Services\CalendarioPeriodoService;
use App\Modules\Asistencia\Services\CalendarGateway;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Calendario de evaluaciones del período y Google Calendar (RF06, CP-RF06-04, ADR-068): el Administrador define las
 * fechas globales, el Jefe las de su departamento y el Coordinador las de su carrera, y cada quien las sincroniza en
 * **su propio** calendario con el OAuth de ADR-018, invitando solo a los involucrados de ese ámbito.
 * Google se simula con `Http::fake`.
 */
beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-23 10:00'));
    config([
        'services.google.client_id' => 'cliente-prueba.apps.googleusercontent.com',
        'services.google.client_secret' => 'secreto-prueba',
        'services.google.calendar.enabled' => true,
    ]);
});

function calendarioPeriodo(): CalendarioPeriodoService
{
    return app(CalendarioPeriodoService::class);
}

function conectarCalendarDe(User $usuario): void
{
    DbContext::system(fn () => $usuario->forceFill(['google_calendar_token' => 'refresh-admin'])->save());
}

/**
 * @param  array<string, mixed>  $respuestaDelEvento
 */
function googleDelPeriodo(array $respuestaDelEvento = ['id' => 'evt-periodo'], int $estado = 200): void
{
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'acceso-admin']),
        'www.googleapis.com/calendar/*' => Http::response($respuestaDelEvento, $estado),
    ]);
}

function referenciaDe(int $evento): ?object
{
    return DB::table('google_sync_refs')->where('entity_type', 'period_calendar_event')->where('entity_id', $evento)->first();
}

it('CP-RF06-04 al sincronizar, cada fecha llega al Google Calendar de los docentes y estudiantes del período', function () {
    $e = escenario();
    conectarCalendarDe($e['administrador']);
    googleDelPeriodo();
    actingAs($e['administrador']);
    $parcial = calendarioPeriodo()->agregar($e['administrador'], $e['periodo'], 'Segundo parcial', '2026-11-10 08:00', '2026-11-14 18:00');
    $cierre = calendarioPeriodo()->agregar($e['administrador'], $e['periodo'], 'Cierre de notas', '2026-12-11', null);

    expect(calendarioPeriodo()->sincronizar($e['administrador'], $e['periodo']))->toBe(2);

    $invitados = collect([$e['docente'], $e['estudiante']])->map(fn (User $u) => ['email' => $u->institutional_email])->sortBy('email')->values()->all();
    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_contains($r->url(), '/calendars/primary/events') && str_contains($r->url(), 'sendUpdates=all')
        && $r->hasHeader('Authorization', 'Bearer acceso-admin')
        && $r['summary'] === 'Segundo parcial' && $r['start']['dateTime'] === '2026-11-10T08:00:00-06:00' && $r['end']['dateTime'] === '2026-11-14T18:00:00-06:00'
        && $r['attendees'] === $invitados && $r['guestsCanSeeOtherGuests'] === false);
    Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_contains($r->url(), '/events') && $r['summary'] === 'Cierre de notas'
        && $r['start'] === ['date' => '2026-12-11'] && $r['end'] === ['date' => '2026-12-12']);
    expect(referenciaDe($parcial))->sync_status->toBe('synced')->external_id->toBe('evt-periodo')
        ->and(referenciaDe($cierre)->sync_status)->toBe('synced');
});

it('CP-RF06-04 sincronizar de nuevo actualiza el evento en vez de duplicarlo, y lo recrea si se borró en Google', function () {
    $e = escenario();
    conectarCalendarDe($e['administrador']);
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'acceso-admin']),
        'www.googleapis.com/calendar/*' => Http::sequence()->push(['id' => 'evt-periodo'])->push(['id' => 'evt-periodo'])
            ->push(['error' => ['code' => 404]], 404)->push(['id' => 'evt-nuevo']),
    ]);
    actingAs($e['administrador']);
    $parcial = calendarioPeriodo()->agregar($e['administrador'], $e['periodo'], 'Segundo parcial', '2026-11-10 08:00', null);

    calendarioPeriodo()->sincronizar($e['administrador'], $e['periodo']);
    calendarioPeriodo()->sincronizar($e['administrador'], $e['periodo']);
    calendarioPeriodo()->sincronizar($e['administrador'], $e['periodo']);

    $llamadas = Http::recorded(fn (Request $r) => str_contains($r->url(), '/events'))->map(fn (array $par) => $par[0]->method())->values()->all();
    expect($llamadas)->toBe(['POST', 'PATCH', 'PATCH', 'POST'])
        ->and(referenciaDe($parcial)->external_id)->toBe('evt-nuevo');
});

it('CP-RF06-04 sin Google Calendar conectado o habilitado no sincroniza y lo explica', function () {
    $e = escenario();
    Http::fake();
    actingAs($e['administrador']);
    calendarioPeriodo()->agregar($e['administrador'], $e['periodo'], 'Segundo parcial', '2026-11-10 08:00', null);

    expect(fn () => calendarioPeriodo()->sincronizar($e['administrador'], $e['periodo']))->toThrow(ValidationException::class, 'Conecte su Google Calendar');
    config(['services.google.calendar.enabled' => false]);
    expect(fn () => calendarioPeriodo()->sincronizar($e['administrador'], $e['periodo']))->toThrow(ValidationException::class, 'no está habilitada');
    Http::assertNothingSent();
});

it('CP-RF06-04 un error de Google deja la fecha como fallida con el motivo, para reintentar', function () {
    $e = escenario();
    conectarCalendarDe($e['administrador']);
    actingAs($e['administrador']);
    $parcial = calendarioPeriodo()->agregar($e['administrador'], $e['periodo'], 'Segundo parcial', '2026-11-10 08:00', null);
    Queue::fake();
    calendarioPeriodo()->sincronizar($e['administrador'], $e['periodo']);
    expect(referenciaDe($parcial)->sync_status)->toBe('pending');
    googleDelPeriodo(['error' => ['message' => 'Rate Limit Exceeded']], 403);

    expect(fn () => (new SincronizarEventoDePeriodo($parcial, $e['administrador']->id))->handle(app(CalendarGateway::class)))->toThrow(Exception::class);
    expect(referenciaDe($parcial))->sync_status->toBe('failed')->last_error->toContain('403');
});

it('CP-RF06-04 eliminar una fecha sincronizada borra su evento en Google y avisa a los invitados', function () {
    $e = escenario();
    conectarCalendarDe($e['administrador']);
    googleDelPeriodo();
    actingAs($e['administrador']);
    $parcial = calendarioPeriodo()->agregar($e['administrador'], $e['periodo'], 'Segundo parcial', '2026-11-10 08:00', null);
    calendarioPeriodo()->sincronizar($e['administrador'], $e['periodo']);

    calendarioPeriodo()->eliminar($e['administrador'], $parcial);

    Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_contains($r->url(), '/events/evt-periodo') && str_contains($r->url(), 'sendUpdates=all'));
    expect(DB::table('period_calendar_events')->find($parcial))->toBeNull()->and(referenciaDe($parcial))->toBeNull();
});

it('CP-RF06-04 rechaza fechas fuera del período, un fin anterior al inicio y cambios en un período cerrado', function () {
    $e = escenario();
    actingAs($e['administrador']);

    expect(fn () => calendarioPeriodo()->agregar($e['administrador'], $e['periodo'], 'Fuera', '2027-01-15 08:00', null))->toThrow(ValidationException::class, 'dentro del período')
        ->and(fn () => calendarioPeriodo()->agregar($e['administrador'], $e['periodo'], 'Al revés', '2026-11-10 08:00', '2026-11-10 07:00'))->toThrow(ValidationException::class, 'posterior');

    DbContext::system(fn () => DB::table('academic_periods')->where('id', $e['periodo'])->update(['status' => 'closed']));
    expect(fn () => calendarioPeriodo()->agregar($e['administrador'], $e['periodo'], 'Tarde', '2026-11-10 08:00', null))->toThrow(ValidationException::class, 'cerrado');
});

it('CP-RF06-04 quien no gestiona el calendario no lo ve ni lo toca', function (string $rol) {
    $e = escenario();
    actingAs($e[$rol]);

    expect(fn () => calendarioPeriodo()->agregar($e[$rol], $e['periodo'], 'Parcial', '2026-11-10 08:00', null))->toThrow(AuthorizationException::class)
        ->and(fn () => calendarioPeriodo()->eventos($e[$rol], $e['periodo']))->toThrow(AuthorizationException::class);
})->with(['docente', 'estudiante', 'soporte']);

it('CP-RF06-04 el Administrador agrega fechas desde la página del calendario y ve su estado en Google Calendar', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(CalendarioEvaluaciones::class, ['periodo' => $e['periodo']])
        ->set('tituloFecha', 'Primer parcial')->set('inicioFecha', '2026-11-03T08:00')->call('agregarFecha')->assertHasNoErrors()
        ->set('tituloFecha', 'Fuera')->set('inicioFecha', '2027-03-01T08:00')->call('agregarFecha')->assertHasErrors('inicioFecha')
        ->assertSee('Primer parcial')->assertSee('Sin sincronizar')->assertSee('Ir a Mi perfil › Google Calendar');

    actingAs($e['administrador'])->get(route('perfil.google-calendar'))->assertOk()->assertSeeText('Conectar con Google');
});

it('CP-RF06-09 el Jefe fija las fechas de su departamento y el Coordinador las de su carrera, cada una en su propio calendario', function () {
    $e = escenario();
    conectarCalendarDe($e['jefe']);
    conectarCalendarDe($e['coordinador']);
    googleDelPeriodo();

    actingAs($e['jefe']);
    $delDepartamento = calendarioPeriodo()->agregar($e['jefe'], $e['periodo'], 'Parcial de Ingeniería', '2026-11-10 08:00', null, 'departamento:'.$e['dia']);
    actingAs($e['coordinador']);
    $deLaCarrera = calendarioPeriodo()->agregar($e['coordinador'], $e['periodo'], 'Defensa de cátedra ISI', '2026-11-20 08:00', null, 'carrera:'.$e['isi']);

    expect(DB::table('period_calendar_events')->find($delDepartamento))->department_id->toBe($e['dia'])->career_id->toBeNull()
        ->and(DB::table('period_calendar_events')->find($deLaCarrera))->career_id->toBe($e['isi'])->department_id->toBeNull();

    // Cada quien sincroniza solo lo suyo: el Coordinador no arrastra la fecha del departamento.
    actingAs($e['coordinador']);
    expect(calendarioPeriodo()->sincronizar($e['coordinador'], $e['periodo']))->toBe(1);
    expect(referenciaDe($deLaCarrera)->sync_status)->toBe('synced')->and(referenciaDe($delDepartamento))->toBeNull();
    $resumenes = Http::recorded(fn (Request $r) => str_contains($r->url(), '/events'))->map(fn (array $par) => $par[0]->data()['summary'] ?? null)->values()->all();
    expect($resumenes)->toBe(['Defensa de cátedra ISI']);
});

it('CP-RF06-09 cada quien ve las fechas globales pero solo modifica las de su ámbito', function () {
    $e = escenario();
    actingAs($e['administrador']);
    $global = calendarioPeriodo()->agregar($e['administrador'], $e['periodo'], 'Cierre de notas', '2026-12-11', null);
    actingAs($e['jefe']);
    $delDepartamento = calendarioPeriodo()->agregar($e['jefe'], $e['periodo'], 'Parcial de Ingeniería', '2026-11-10 08:00', null, 'departamento:'.$e['dia']);

    $delJefe = calendarioPeriodo()->eventos($e['jefe'], $e['periodo'])->keyBy('id');
    expect($delJefe)->toHaveCount(2)
        ->and($delJefe[$global]->editable)->toBeFalse()
        ->and($delJefe[$global]->ambito)->toBe('Todo el período')
        ->and($delJefe[$delDepartamento]->editable)->toBeTrue();

    // El Administrador ve la del departamento, pero no la toca: no es su ámbito.
    actingAs($e['administrador']);
    $delAdmin = calendarioPeriodo()->eventos($e['administrador'], $e['periodo'])->keyBy('id');
    expect($delAdmin[$delDepartamento]->editable)->toBeFalse()->and($delAdmin[$global]->editable)->toBeTrue();

    actingAs($e['jefe']);
    expect(fn () => calendarioPeriodo()->eliminar($e['jefe'], $global))->toThrow(AuthorizationException::class, 'otro ámbito');
    expect(DB::table('period_calendar_events')->find($global))->not->toBeNull();
});

it('CP-RF06-09 nadie crea fechas en un ámbito que no gestiona', function () {
    $e = escenario();
    actingAs($e['coordinador']);

    expect(fn () => calendarioPeriodo()->agregar($e['coordinador'], $e['periodo'], 'Ajena', '2026-11-10 08:00', null, 'departamento:'.$e['dia']))
        ->toThrow(ValidationException::class, 'ámbito que usted gestione')
        ->and(fn () => calendarioPeriodo()->agregar($e['coordinador'], $e['periodo'], 'Ajena', '2026-11-10 08:00', null, 'global'))
        ->toThrow(ValidationException::class, 'ámbito que usted gestione');
    expect(DB::table('period_calendar_events')->count())->toBe(0);
});

it('CP-RF06-09 una fecha del departamento solo invita a los involucrados de ese departamento', function () {
    $e = escenario();
    // Un docente de Ciencias Naturales, fuera del departamento del Jefe.
    $ajeno = usuarioCon('docente');
    DbContext::system(fn () => DB::table('teaching_assignments')->insert([
        'section_id' => $e['otra'], 'teacher_id' => $ajeno->id, 'is_responsible' => true, 'started_at' => '2026-07-01', 'created_at' => now(), 'updated_at' => now(),
    ]));
    conectarCalendarDe($e['jefe']);
    googleDelPeriodo();
    actingAs($e['jefe']);
    calendarioPeriodo()->agregar($e['jefe'], $e['periodo'], 'Parcial de Ingeniería', '2026-11-10 08:00', null, 'departamento:'.$e['dia']);

    calendarioPeriodo()->sincronizar($e['jefe'], $e['periodo']);

    $invitados = collect([$e['docente'], $e['estudiante']])->map(fn (User $u) => ['email' => $u->institutional_email])->sortBy('email')->values()->all();
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/events') && $r->method() === 'POST' && ($r->data()['attendees'] ?? null) === $invitados);
    expect(CalendarioPeriodoService::involucrados($e['periodo']))->toContain($ajeno->institutional_email)
        ->and(CalendarioPeriodoService::involucrados($e['periodo'], $e['dia']))->not->toContain($ajeno->institutional_email);
});

it('CP-RF06-09 el Jefe y el Coordinador entran a la página del calendario y a Mi perfil › Google Calendar', function (string $rol) {
    $e = escenario();
    actingAs($e[$rol]);

    actingAs($e[$rol])->get(route('periodos.calendario'))->assertOk()->assertSeeText('Calendario de evaluaciones');
    actingAs($e[$rol])->get(route('perfil.google-calendar'))->assertOk();
})->with(['jefe', 'coordinador']);

it('CP-RF06-09 el docente no entra a la página del calendario de evaluaciones', function () {
    $e = escenario();

    actingAs($e['docente'])->get(route('periodos.calendario'))->assertForbidden();
});
