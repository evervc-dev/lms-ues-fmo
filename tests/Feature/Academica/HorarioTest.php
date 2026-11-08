<?php

use App\Modules\Academica\Events\HorarioCambiado;
use App\Modules\Academica\Livewire\CargaAcademica;
use App\Modules\Academica\Services\CargaAcademicaService;
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
})->with(['jefe', 'docente']);

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
