<?php

use App\Modules\Asistencia\Services\AsistenciaService;
use App\Modules\Comunicacion\Notifications\Aviso;
use App\Modules\Reportes\Services\PanelService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
| Alertas de inasistencia (RF22, Flujo Alerta de inasistencias): por cómputo, bajo 85 % aviso preventivo, bajo
| 78 % aviso crítico y alerta al docente, bajo 75 % «en riesgo» en los paneles del docente y del Coordinador.
| Cada aviso sale una sola vez por umbral y cómputo; las justificadas y los días cancelados no cuentan como falta.
*/

/** Un cómputo que cubre todo septiembre: las sesiones de las pruebas caen en él. */
function alertasComputo(array $e): int
{
    return DbContext::system(fn () => DB::table('grading_components')->insertGetId(['section_id' => $e['seccion'], 'number' => 1, 'name' => 'Cómputo 1',
        'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30', 'weight_percent' => 100, 'created_at' => now(), 'updated_at' => now()]));
}

/**
 * Una clase por día desde el 1 de septiembre, con el estado del estudiante en cada una. Devuelve el ulid de cada sesión.
 *
 * @param  array<int, string>  $estados
 * @return array<int, string>
 */
function alertasClases(array $e, array $estados, int $desdeDia = 1): array
{
    $asistencia = app(AsistenciaService::class);
    $sesiones = [];
    foreach ($estados as $i => $estado) {
        test()->travelTo(sprintf('2026-09-%02d 08:00', $desdeDia + $i));
        actingAs($e['docente']);
        $sesion = $asistencia->abrir($e['docente'], $e['seccion'], 10);
        if ($estado !== 'absent') {
            $asistencia->marcar($e['docente'], $e['seccion'], $sesion, $e['matricula'], $estado);
        }
        $asistencia->cerrar($e['docente'], $e['seccion'], $sesion);
        $sesiones[] = $sesion;
    }
    test()->travelBack();

    return $sesiones;
}

/**
 * Tipos de alerta abiertos para la matrícula del escenario.
 *
 * @return array<int, string>
 */
function alertasAbiertas(array $e): array
{
    return DB::table('academic_alerts')->where('enrollment_id', $e['matricula'])->whereNull('closed_at')->orderBy('alert_type')->pluck('alert_type')->all();
}

function alertasAvisosAlEstudiante(array $e): int
{
    return Notification::sent($e['estudiante'], Aviso::class)->filter(fn (Aviso $aviso) => $aviso->evento === 'academic_alert.opened')->count();
}

it('CP-RF22-01 avisa al estudiante en la plataforma y por correo cuando su asistencia del cómputo baja de 85 %', function () {
    Notification::fake();
    $e = escenario();
    alertasComputo($e);

    // 4 de 5 = 80 %.
    alertasClases($e, ['present', 'present', 'present', 'present', 'absent']);

    expect(alertasAbiertas($e))->toBe(['attendance_85']);
    Notification::assertSentTo($e['estudiante'], Aviso::class, fn (Aviso $aviso, array $canales) => $aviso->evento === 'academic_alert.opened'
        && str_contains($aviso->titulo, 'Aviso de asistencia: PRN115') && str_contains($aviso->cuerpo, 'Cómputo 1') && str_contains($aviso->cuerpo, 'lleva 80 %')
        && $canales === ['broadcast', 'mail']);
    Notification::assertNotSentTo($e['docente'], Aviso::class);
});

it('CP-RF22-02 CP-RF22-06 bajo 78 % el aviso es crítico y el docente ve al estudiante en su panel', function () {
    Notification::fake();
    $e = escenario();
    alertasComputo($e);

    // 3 de 4 = 75 %: bajo 85 y bajo 78, pero no bajo el mínimo.
    alertasClases($e, ['present', 'present', 'present', 'absent']);

    // Cruzó 85 % y 78 % a la vez: un solo aviso, el crítico.
    expect(alertasAbiertas($e))->toBe(['attendance_78', 'attendance_85'])
        ->and(alertasAvisosAlEstudiante($e))->toBe(1);
    Notification::assertSentTo($e['estudiante'], Aviso::class, fn (Aviso $aviso) => str_contains($aviso->titulo, 'Asistencia crítica: PRN115') && str_contains($aviso->cuerpo, 'lleva 75 %'));

    $panel = app(PanelService::class)->docente($e['docente']);
    expect($panel['alertas']->pluck('alert_type')->all())->toBe(['attendance_78'])
        ->and($panel['alertas']->first()->last_name)->toBe($e['estudiante']->last_name);
});

it('CP-RF22-02 el preventivo de 85 % no aparece en el panel del docente', function () {
    $e = escenario();
    alertasComputo($e);

    alertasClases($e, ['present', 'present', 'present', 'present', 'absent']);

    expect(app(PanelService::class)->docente($e['docente'])['alertas'])->toBeEmpty();
});

it('CP-RF22-03 bajo 75 % queda «en riesgo» en los paneles del docente y del Coordinador', function () {
    $e = escenario();
    alertasComputo($e);

    // 2 de 3 = 66,7 %.
    alertasClases($e, ['present', 'present', 'absent']);

    expect(alertasAbiertas($e))->toBe(['attendance_75', 'attendance_78', 'attendance_85'])
        ->and(app(PanelService::class)->docente($e['docente'])['alertas']->pluck('alert_type')->sort()->values()->all())->toBe(['attendance_75', 'attendance_78'])
        ->and(app(PanelService::class)->coordinador($e['coordinador'])['riesgo'])->toBe(1);
});

it('CP-RF22-04 el aviso del mismo umbral y cómputo sale una sola vez, aunque la alerta se cierre y se vuelva a abrir', function () {
    Notification::fake();
    $e = escenario();
    alertasComputo($e);

    alertasClases($e, ['present', 'present', 'present', 'present', 'absent']);
    // Otra falta: sigue bajo 85 % (66,7 %) y abre las demás, pero el preventivo no se repite.
    alertasClases($e, ['absent'], desdeDia: 6);
    expect(Notification::sent($e['estudiante'], Aviso::class)->filter(fn (Aviso $a) => str_contains($a->titulo, 'Aviso de asistencia'))->count())->toBe(1);

    // Se recupera (cierra las alertas) y vuelve a bajar: ningún aviso nuevo.
    alertasClases($e, array_fill(0, 10, 'present'), desdeDia: 7);
    expect(alertasAbiertas($e))->toBe([]);
    $antes = alertasAvisosAlEstudiante($e);
    // 14 de 17 = 82,4 %: vuelve a bajar de 85 %.
    alertasClases($e, ['absent'], desdeDia: 17);

    expect(alertasAbiertas($e))->toBe(['attendance_85'])
        ->and(alertasAvisosAlEstudiante($e))->toBe($antes)
        ->and(DB::table('academic_alerts')->where('enrollment_id', $e['matricula'])->where('alert_type', 'attendance_85')->count())->toBe(2);
});

it('CP-RF22-05 una inasistencia justificada no cuenta como falta y cierra la alerta', function () {
    $e = escenario();
    alertasComputo($e);
    alertasClases($e, ['present', 'present', 'present', 'absent']);
    expect(alertasAbiertas($e))->toBe(['attendance_78', 'attendance_85']);
    $registro = (int) DB::table('attendance_records')->where('enrollment_id', $e['matricula'])->where('status', 'absent')->value('id');
    $justificacion = DbContext::system(fn () => DB::table('attendance_justifications')->insertGetId(['record_id' => $registro, 'reason' => 'Cita médica.',
        'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]));
    actingAs($e['docente']);

    app(AsistenciaService::class)->revisarJustificacion($e['docente'], $e['seccion'], $justificacion, true);

    expect(alertasAbiertas($e))->toBe([]);
});

it('CP-RF22-05 la falta de un día cancelado no cuenta y cierra la alerta', function () {
    $e = escenario();
    alertasComputo($e);
    alertasClases($e, ['present', 'present', 'present', 'absent']);
    actingAs($e['administrador']);

    app(AsistenciaService::class)->cancelarDirecto($e['administrador'], '2026-09-04', 'Asueto.');

    expect(alertasAbiertas($e))->toBe([]);
});

it('CP-RF22-05 con todo en orden no abre ninguna alerta', function () {
    $e = escenario();
    alertasComputo($e);

    alertasClases($e, ['present', 'present', 'late', 'present']);

    // 3,5 de 4 = 87,5 %: la tardanza vale 0,5.
    expect(alertasAbiertas($e))->toBe([]);
});
