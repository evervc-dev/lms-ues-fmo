<?php

use App\Modules\Academica\Events\HorarioCambiado;
use App\Modules\Academica\Livewire\CargaAcademica;
use App\Modules\Academica\Services\CargaAcademicaService;
use App\Modules\Academica\Services\HorarioService;
use App\Modules\Comunicacion\Notifications\Aviso;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
| Cambios de horario o aula de una sección (RF08, CP-RF08-03): agregar o quitar un bloque y cambiar su aula
| avisa a los estudiantes matriculados y a los docentes vigentes (evento schedule.changed, cola notificaciones).
| Programa el horario quien tiene secciones.gestionar: el Coordinador en su carrera y el Administrador.
*/

function carga(): CargaAcademicaService
{
    return app(CargaAcademicaService::class);
}

/** El bloque de horario que el escenario le da a la sección (lunes 07:00–08:40 en A-101). */
function bloqueDelEscenario(array $e): int
{
    return (int) DB::table('section_schedules')->where('section_id', $e['seccion'])->value('id');
}

/**
 * Comprueba que el aviso de cambio de horario llegó al estudiante y al docente de la sección, con el detalle,
 * y que no llegó a nadie más del escenario.
 */
function avisoDeHorarioA(array $e, string $detalle): void
{
    foreach ([$e['estudiante'], $e['docente']] as $destinatario) {
        Notification::assertSentTo($destinatario, Aviso::class, fn (Aviso $aviso) => $aviso->evento === 'schedule.changed'
            && str_contains($aviso->titulo, 'PRN115-01') && str_contains($aviso->cuerpo, $detalle) && $aviso->queue === 'notificaciones');
    }
    foreach ([$e['coordinador'], $e['jefe'], $e['administrador']] as $otro) {
        Notification::assertNotSentTo($otro, Aviso::class);
    }
}

it('CP-RF08-03 avisa a los estudiantes y docentes de la sección cuando se agrega un horario', function () {
    Notification::fake();
    $e = escenario();
    actingAs($e['coordinador']);

    carga()->agregarHorario($e['coordinador'], $e['seccion'], 3, '07:00', '08:40', $e['aula']);

    avisoDeHorarioA($e, 'Mié 07:00–08:40 · A-101');
});

it('CP-RF08-03 quita un bloque del horario y avisa a la sección', function () {
    Notification::fake();
    $e = escenario();
    actingAs($e['coordinador']);

    carga()->quitarHorario($e['coordinador'], $e['seccion'], bloqueDelEscenario($e));

    expect(DB::table('section_schedules')->where('section_id', $e['seccion'])->exists())->toBeFalse();
    avisoDeHorarioA($e, 'Se quitó el horario Lun 07:00–08:40 · A-101');
});

it('CP-RF08-03 cambia el aula de un bloque y avisa a la sección con el aula nueva', function () {
    Notification::fake();
    $e = escenario();
    $nueva = DbContext::system(fn () => DB::table('classrooms')->insertGetId(['code' => 'B-21', 'capacity' => 40, 'created_at' => now(), 'updated_at' => now()]));
    actingAs($e['administrador']);

    carga()->cambiarAula($e['administrador'], $e['seccion'], bloqueDelEscenario($e), $nueva);

    expect((int) DB::table('section_schedules')->where('id', bloqueDelEscenario($e))->value('classroom_id'))->toBe($nueva);
    avisoDeHorarioA($e, 'ahora es B-21');
});

it('CP-RF08-03 rechaza cambiar a un aula ocupada a esa hora, sin cambiar nada ni avisar', function () {
    Event::fake([HorarioCambiado::class]);
    $e = escenario();
    $ocupada = DbContext::system(function () use ($e) {
        $aula = DB::table('classrooms')->insertGetId(['code' => 'B-21', 'capacity' => 40, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('section_schedules')->insert(['section_id' => $e['otra'], 'period_id' => $e['periodo'], 'classroom_id' => $aula, 'weekday' => 1,
            'start_time' => '08:00', 'end_time' => '09:40', 'created_at' => now(), 'updated_at' => now()]);

        return $aula;
    });
    actingAs($e['administrador']);

    expect(fn () => carga()->cambiarAula($e['administrador'], $e['seccion'], bloqueDelEscenario($e), $ocupada))
        ->toThrow(ValidationException::class, 'MAT115');

    expect((int) DB::table('section_schedules')->where('id', bloqueDelEscenario($e))->value('classroom_id'))->toBe($e['aula']);
    Event::assertNotDispatched(HorarioCambiado::class);
});

it('CP-RF08-03 impide quitar o cambiar el horario a quien no programa esa sección', function (string $rol) {
    Event::fake([HorarioCambiado::class]);
    $e = escenario();
    $bloque = bloqueDelEscenario($e);

    expect(fn () => carga()->quitarHorario($e[$rol], $e['seccion'], $bloque))->toThrow(AuthorizationException::class)
        ->and(fn () => carga()->cambiarAula($e[$rol], $e['seccion'], $bloque, null))->toThrow(AuthorizationException::class);

    expect((int) DB::table('section_schedules')->where('id', $bloque)->value('classroom_id'))->toBe($e['aula']);
    Event::assertNotDispatched(HorarioCambiado::class);
})->with(['docente']); // El Jefe sí programa las de su departamento (ADR-073); fuera de él, CP-RF08-04.

it('CP-RF08-03 impide al Coordinador tocar el horario de una sección fuera de su carrera', function () {
    $e = escenario();
    $bloque = DbContext::system(fn () => DB::table('section_schedules')->insertGetId(['section_id' => $e['otra'], 'period_id' => $e['periodo'],
        'classroom_id' => null, 'weekday' => 2, 'start_time' => '10:00', 'end_time' => '11:40', 'created_at' => now(), 'updated_at' => now()]));

    expect(fn () => carga()->quitarHorario($e['coordinador'], $e['otra'], $bloque))->toThrow(AuthorizationException::class);

    expect(DB::table('section_schedules')->where('id', $bloque)->exists())->toBeTrue();
});

it('CP-RF08-03 muestra los bloques en el modal de horario y los quita o cambia de aula desde la pantalla', function () {
    Notification::fake();
    $e = escenario();
    actingAs($e['coordinador']);

    $pantalla = Livewire::test(CargaAcademica::class)->call('abrir', 'horario', $e['seccion'])
        ->assertSee('Lun 07:00–08:40 · A-101')->assertSee('Quitar');

    $pantalla->call('cambiarAula', bloqueDelEscenario($e), '')->assertDispatched('aviso', tipo: 'exito');
    expect(DB::table('section_schedules')->where('id', bloqueDelEscenario($e))->value('classroom_id'))->toBeNull();

    $pantalla->call('quitarHorario', bloqueDelEscenario($e))->assertDispatched('aviso', tipo: 'exito');
    expect(DB::table('section_schedules')->where('section_id', $e['seccion'])->exists())->toBeFalse();
    // El segundo aviso llega a los pocos segundos: se agrupa con el primero en una sola notificación (RF23).
    $aviso = DB::table('notifications')->where('notifiable_id', $e['estudiante']->id)->where('event_code', 'schedule.changed')->sole();
    expect(json_decode($aviso->data, true)['count'])->toBe(2);
});

it('CP-RF08-03 rechaza un bloque que choca en la misma aula con otro bloque del mismo grupo', function () {
    $e = escenario();
    actingAs($e['administrador']);

    // El escenario ya tiene PRN115 · 01 el lunes de 07:00 a 08:40 en A-101.
    expect(fn () => carga()->agregarHorario($e['administrador'], $e['seccion'], 1, '07:00', '08:40', $e['aula']))
        ->toThrow(ValidationException::class, 'PRN115');
    expect(DB::table('section_schedules')->where('section_id', $e['seccion'])->count())->toBe(1);
});

it('CP-RF08-03 traduce el choque de aula de la base a un error del campo aula, no a un error 500', function () {
    $e = escenario();
    actingAs($e['administrador']);
    // Simula dos envíos a la vez: la validación previa no ve el choque y decide el EXCLUDE de PostgreSQL.
    $horarios = Mockery::mock(HorarioService::class)->makePartial();
    $horarios->shouldReceive('chocaAula')->andReturnNull();
    app()->instance(HorarioService::class, $horarios);

    try {
        app(CargaAcademicaService::class)->agregarHorario($e['administrador'], $e['otra'], 1, '07:00', '08:00', $e['aula']);
        $this->fail('Debía rechazar el choque de aula.');
    } catch (ValidationException $excepcion) {
        expect($excepcion->errors())->toBe(['aula' => [__('El aula ya está ocupada en ese horario.')]]);
    }
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'El EXCLUDE de aulas solo existe en PostgreSQL.');

it('CP-RF08-03 la carga académica marca el choque de aula entre dos grupos sin consultar bloque por bloque', function () {
    $e = escenario();
    // MAT115 · 01 en la misma aula y a la misma hora que PRN115 · 01 (lunes 07:00–08:40 en A-101). SQLite no tiene el EXCLUDE.
    DbContext::system(fn () => DB::table('section_schedules')->insert(['section_id' => $e['otra'], 'period_id' => $e['periodo'], 'classroom_id' => $e['aula'],
        'weekday' => 1, 'start_time' => '08:00', 'end_time' => '09:40', 'created_at' => now(), 'updated_at' => now()]));

    $secciones = app(CargaAcademicaService::class)->secciones($e['administrador'], $e['periodo'])->keyBy('code');

    expect($secciones['PRN115']->estado)->toBe('choque')
        ->and($secciones['PRN115']->choque)->toBe('MAT115 · 01')
        ->and($secciones['MAT115']->choque)->toBe('PRN115 · 01')
        ->and($secciones['PRN115']->horario)->toBe(['Lun 07:00–08:40 · A-101']);
})->skip(fn () => DB::connection()->getDriverName() === 'pgsql', 'En PostgreSQL el EXCLUDE no deja guardar el choque.');
