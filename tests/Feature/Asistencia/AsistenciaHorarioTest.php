<?php

use App\Modules\Asistencia\Livewire\AsistenciaSeccion;
use App\Modules\Asistencia\Services\AsistenciaService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
 * Apertura de la asistencia según el horario del grupo teórico (RF19, ADR-075). El escenario() tiene un bloque el
 * lunes de 07:00 a 08:40; el 19/10/2026 es lunes y el 24/10/2026, sábado.
 */
beforeEach(function () {
    config(['lms.asistencia.bypass_horario' => false]);
    $this->e = escenario();
    actingAs($this->e['docente']);
});

function abrirA(array $e, string $momento, ?string $motivo = null): string
{
    test()->travelTo(Carbon::parse($momento));

    return app(AsistenciaService::class)->abrir($e['docente'], $e['seccion'], 10, 'in_person', $motivo);
}

it('CP-RF19-05 abre sin motivo dentro del horario, con 15 minutos de margen', function (string $momento) {
    expect(abrirA($this->e, $momento))->toBeString();
})->with(['al empezar' => '2026-10-19 07:00', '15 min antes' => '2026-10-19 06:45', '15 min después del fin' => '2026-10-19 08:55']);

it('CP-RF19-06 fuera del horario no abre sin motivo', function (string $momento) {
    expect(fn () => abrirA($this->e, $momento))->toThrow(ValidationException::class, 'fuera del horario');
    expect(DB::table('attendance_sessions')->count())->toBe(0);
})->with(['pasado el margen' => '2026-10-19 08:56', 'otro día' => '2026-10-20 07:30', 'sábado' => '2026-10-24 07:30']);

it('CP-RF19-06 fuera del horario abre con motivo, para adelantar o reponer una clase', function () {
    $ulid = abrirA($this->e, '2026-10-24 10:00', 'Clase adelantada');

    expect(DB::table('attendance_sessions')->where('ulid', $ulid)->value('status'))->toBe('open');
});

it('CP-RF19-06 la pantalla pide el motivo solo fuera del horario', function () {
    $this->travelTo(Carbon::parse('2026-10-19 07:10'));
    Livewire::test(AsistenciaSeccion::class, ['seccion' => $this->e['ulid']])->assertDontSee(__('Fuera del horario de clase'));

    $this->travelTo(Carbon::parse('2026-10-24 10:00'));
    Livewire::test(AsistenciaSeccion::class, ['seccion' => $this->e['ulid']])
        ->assertSee(__('Fuera del horario de clase'))
        ->call('abrir')->assertHasErrors(['motivoFueraHorario'])
        ->set('motivoFueraHorario', 'Reposición de clase')->call('abrir')->assertHasNoErrors()->assertRedirect();
});

it('CP-RF19-07 el motivo de abrir fuera del horario queda en la auditoría', function () {
    abrirA($this->e, '2026-10-24 10:00', 'Clase adelantada');
    DbContext::auditScope(all: true);

    $this->assertDatabaseHas('audit_logs', ['entity_type' => 'attendance_sessions', 'action' => 'insert', 'event_type' => 'attendance_session.opened',
        'reason' => 'Fuera de horario: Clase adelantada']);
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'La auditoría por triggers solo existe en PostgreSQL.');

it('ASIS_BYPASS_WEEKEND permite abrir sin motivo fuera del horario, salvo en producción', function () {
    config(['lms.asistencia.bypass_horario' => true]);
    expect(abrirA($this->e, '2026-10-24 10:00'))->toBeString();

    $this->app->detectEnvironment(fn () => 'production');
    expect(app(AsistenciaService::class)->dentroDelHorario($this->e['seccion']))->toBeFalse();
});
