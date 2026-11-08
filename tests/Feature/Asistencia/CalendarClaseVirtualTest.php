<?php

use App\Modules\Asistencia\Jobs\CrearEventoDeClase;
use App\Modules\Asistencia\Services\CalendarDesactivado;
use App\Modules\Asistencia\Services\CalendarFalso;
use App\Modules\Asistencia\Services\CalendarGateway;
use App\Modules\Asistencia\Services\ReporteMeetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Evento de Google Calendar de una clase virtual (RF21, CP-RF21-01), contra `CalendarFalso`: la integración real
 * espera el cliente OAuth de TI (ADR-018).
 */
beforeEach(fn () => $this->travelTo(Carbon::parse('2026-10-23 10:00')));

it('CP-RF21-01 al programar una clase virtual se crea el evento con enlace de Meet e invitación a los matriculados', function () {
    $e = escenario();
    $calendario = new CalendarFalso;
    app()->instance(CalendarGateway::class, $calendario);
    actingAs($e['docente']);

    $id = app(ReporteMeetService::class)->programar($e['docente'], $e['seccion'], 'Recursividad', '2026-10-30 07:00', 90, null);

    expect($calendario->creados)->toHaveCount(1)
        ->and($calendario->creados[0])->toMatchArray(['docente' => $e['docente']->id, 'titulo' => 'Recursividad', 'minutos' => 90,
            'invitados' => [$e['estudiante']->institutional_email]]);
    $clase = DB::table('video_sessions')->find($id);
    expect($clase->google_event_id)->toStartWith('evento-')->and($clase->meet_url)->toBe('https://meet.google.com/abc-defg-hij');
});

it('CP-RF21-01 el evento va en la cola «google» y conserva el enlace de Meet que escribió el docente', function () {
    $e = escenario();
    Queue::fake();
    actingAs($e['docente']);

    $id = app(ReporteMeetService::class)->programar($e['docente'], $e['seccion'], 'Repaso', '2026-10-30 07:00', 60, 'https://meet.google.com/xyz-abcd-efg');

    Queue::assertPushedOn('google', CrearEventoDeClase::class, fn (CrearEventoDeClase $job) => $job->claseId === $id);

    app()->instance(CalendarGateway::class, new CalendarFalso);
    (new CrearEventoDeClase($id, $e['docente']->id))->handle(app(CalendarGateway::class));
    expect(DB::table('video_sessions')->where('id', $id)->value('meet_url'))->toBe('https://meet.google.com/xyz-abcd-efg');
});

it('CP-RF21-01 el job es idempotente: si la clase ya tiene evento no crea otro', function () {
    $e = escenario();
    $calendario = new CalendarFalso;
    app()->instance(CalendarGateway::class, $calendario);
    actingAs($e['docente']);
    $id = app(ReporteMeetService::class)->programar($e['docente'], $e['seccion'], 'Recursividad', '2026-10-30 07:00', 90, null);
    $evento = DB::table('video_sessions')->where('id', $id)->value('google_event_id');

    (new CrearEventoDeClase($id, $e['docente']->id))->handle($calendario);

    expect($calendario->creados)->toHaveCount(1)->and(DB::table('video_sessions')->where('id', $id)->value('google_event_id'))->toBe($evento);
});

it('CP-RF21-01 sin integración configurada no se crea ningún evento ni se inventa un enlace', function () {
    $e = escenario();
    actingAs($e['docente']);

    expect(app(CalendarGateway::class))->toBeInstanceOf(CalendarDesactivado::class);
    $id = app(ReporteMeetService::class)->programar($e['docente'], $e['seccion'], 'Recursividad', '2026-10-30 07:00', 90, null);

    expect(DB::table('video_sessions')->find($id))->google_event_id->toBeNull()->meet_url->toBeNull();
});
