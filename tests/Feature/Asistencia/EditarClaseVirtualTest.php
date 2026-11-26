<?php

use App\Modules\Asistencia\Livewire\ClasesVirtuales;
use App\Modules\Asistencia\Services\CalendarFalso;
use App\Modules\Asistencia\Services\CalendarGateway;
use App\Modules\Asistencia\Services\ReporteMeetService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Editar y cancelar una clase virtual (RF21, ADR-090): lo que se puede cambiar depende del estado de la clase, y el
 * evento de Google Calendar se actualiza o se borra (contra `CalendarFalso`).
 */
beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-23 10:00'));
    $this->calendario = new CalendarFalso;
    app()->instance(CalendarGateway::class, $this->calendario);
});

/** Programa una clase el 30/10 a las 07:00, de 60 min, y devuelve su id. */
function claseProgramada(array $e): int
{
    actingAs($e['docente']);

    return app(ReporteMeetService::class)->programar($e['docente'], $e['seccion'], 'Recursividad', '2026-10-30 07:00', 60, null);
}

function claseGuardada(int $id): object
{
    return DB::table('video_sessions')->find($id);
}

it('CP-RF21-10 una clase programada se edita entera y su evento de Calendar se actualiza', function () {
    $e = escenario();
    $id = claseProgramada($e);
    $evento = claseGuardada($id)->google_event_id;

    app(ReporteMeetService::class)->editar($e['docente'], $e['seccion'], $id, 'Árboles binarios', '2026-10-31 09:30', 100, 'https://meet.google.com/nue-vona-mee');

    $clase = claseGuardada($id);
    expect($clase->topic)->toBe('Árboles binarios')
        ->and(Carbon::parse($clase->starts_at)->format('Y-m-d H:i'))->toBe('2026-10-31 09:30')
        ->and((int) $clase->duration_minutes)->toBe(100)
        ->and($clase->meet_url)->toBe('https://meet.google.com/nue-vona-mee')
        ->and($this->calendario->actualizados)->toHaveCount(1)
        ->and($this->calendario->actualizados[0])->toMatchArray(['evento' => $evento, 'titulo' => 'Árboles binarios', 'minutos' => 100]);
});

it('CP-RF21-10 al editar una clase programada valida igual que al programarla', function () {
    $e = escenario();
    $id = claseProgramada($e);
    $meet = app(ReporteMeetService::class);

    expect(fn () => $meet->editar($e['docente'], $e['seccion'], $id, 'Repaso', '2026-10-30 07:00', 400, null))->toThrow(ValidationException::class)
        ->and(fn () => $meet->editar($e['docente'], $e['seccion'], $id, 'Repaso', '2026-10-30 07:00', 60, 'https://zoom.us/j/1'))->toThrow(ValidationException::class)
        ->and(fn () => $meet->editar($e['docente'], $e['seccion'], $id, '  ', '2026-10-30 07:00', 60, null))->toThrow(ValidationException::class)
        ->and(claseGuardada($id)->topic)->toBe('Recursividad');
});

it('CP-RF21-10 una clase en curso cambia tema y enlace y se alarga, pero no cambia su inicio ni se acorta', function () {
    $e = escenario();
    $id = claseProgramada($e);
    $this->travelTo(Carbon::parse('2026-10-30 07:20'));
    $meet = app(ReporteMeetService::class);

    expect(fn () => $meet->editar($e['docente'], $e['seccion'], $id, 'Recursividad', '2026-10-30 07:00', 50, null))->toThrow(ValidationException::class);

    $meet->editar($e['docente'], $e['seccion'], $id, 'Recursividad II', '2026-10-30 08:00', 90, 'https://meet.google.com/abc-defg-xyz');

    $clase = claseGuardada($id);
    expect($clase->topic)->toBe('Recursividad II')
        ->and(Carbon::parse($clase->starts_at)->format('Y-m-d H:i'))->toBe('2026-10-30 07:00')
        ->and((int) $clase->duration_minutes)->toBe(90)
        ->and($clase->meet_url)->toBe('https://meet.google.com/abc-defg-xyz')
        ->and($clase->status)->toBe('in_progress');
});

it('CP-RF21-10 una clase finalizada solo cambia el tema: la fecha y la duración son la base del reporte (ADR-003)', function () {
    $e = escenario();
    $id = claseProgramada($e);
    $antes = claseGuardada($id);
    $this->travelTo(Carbon::parse('2026-10-30 12:00'));

    app(ReporteMeetService::class)->editar($e['docente'], $e['seccion'], $id, 'Recursividad (repaso)', '2026-11-02 07:00', 200, 'https://meet.google.com/otr-oenl-ace');

    $clase = claseGuardada($id);
    expect($clase->topic)->toBe('Recursividad (repaso)')
        ->and($clase->starts_at)->toBe($antes->starts_at)
        ->and((int) $clase->duration_minutes)->toBe(60)
        ->and($clase->meet_url)->toBe($antes->meet_url);
});

it('CP-RF21-10 el docente abre la clase en el editor desde la pantalla y guarda los cambios', function () {
    $e = escenario();
    $id = claseProgramada($e);

    Livewire::test(ClasesVirtuales::class, ['seccionId' => $e['seccion']])
        ->call('editar', $id)
        ->assertSet('editandoId', $id)
        ->assertSet('tema', 'Recursividad')
        ->assertSet('inicio', '2026-10-30T07:00')
        ->assertSee(__('Editar clase virtual'))
        ->set('tema', 'Pilas y colas')
        ->set('duracion', 120)
        ->call('programar')
        ->assertHasNoErrors()
        ->assertSet('editandoId', null);

    expect(claseGuardada($id)->topic)->toBe('Pilas y colas')
        ->and((int) claseGuardada($id)->duration_minutes)->toBe(120)
        ->and(DB::table('video_sessions')->count())->toBe(1);
});

it('CP-RF21-11 una clase programada se cancela: se borra y su evento de Calendar se borra', function () {
    $e = escenario();
    $id = claseProgramada($e);
    $evento = claseGuardada($id)->google_event_id;

    Livewire::test(ClasesVirtuales::class, ['seccionId' => $e['seccion']])->call('cancelar', $id)->assertHasNoErrors();

    expect(DB::table('video_sessions')->where('id', $id)->exists())->toBeFalse()
        ->and($this->calendario->cancelados)->toBe([['docente' => $e['docente']->id, 'evento' => $evento]]);
});

it('CP-RF21-11 una clase en curso o finalizada no se cancela', function (string $hora) {
    $e = escenario();
    $id = claseProgramada($e);
    $this->travelTo(Carbon::parse($hora));

    expect(fn () => app(ReporteMeetService::class)->cancelar($e['docente'], $e['seccion'], $id))->toThrow(ValidationException::class)
        ->and(DB::table('video_sessions')->where('id', $id)->exists())->toBeTrue()
        ->and($this->calendario->cancelados)->toBe([]);
})->with(['en curso' => '2026-10-30 07:20', 'finalizada' => '2026-10-30 12:00']);

it('CP-RF21-10 CP-RF21-11 rechaza editar y cancelar a un docente de otra sección y a un estudiante', function (string $quien) {
    $e = escenario();
    $id = claseProgramada($e);
    $actor = $quien === 'docente ajeno' ? docenteAjeno($e) : $e['estudiante'];
    actingAs($actor);
    $meet = app(ReporteMeetService::class);

    expect(fn () => $meet->editar($actor, $e['seccion'], $id, 'Otro', '2026-10-30 07:00', 60, null))->toThrow(AuthorizationException::class)
        ->and(fn () => $meet->cancelar($actor, $e['seccion'], $id))->toThrow(AuthorizationException::class)
        ->and(claseGuardada($id)->topic)->toBe('Recursividad');
})->with(['docente ajeno', 'estudiante']);
