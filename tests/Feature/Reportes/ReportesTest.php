<?php

use App\Modules\Reportes\Jobs\EnviarReportesProgramados;
use App\Modules\Reportes\Livewire\DesempenoDocente;
use App\Modules\Reportes\Notifications\ReporteProgramado;
use App\Modules\Reportes\Services\DesempenoService;
use App\Modules\Reportes\Services\ReporteService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
| Reportes programados por correo (RF25) y desempeño docente por período (RF33).
*/

it('CP-RF25-02 genera el reporte programado en segundo plano y lo envía por correo con el CSV de su ámbito', function () {
    Notification::fake();
    $e = escenario();
    $this->travelTo('2026-10-21 10:00'); // miércoles
    actingAs($e['coordinador']);
    app(ReporteService::class)->programar($e['coordinador'], 'asistencia', 'weekly');
    expect(DB::table('scheduled_reports')->value('next_run_at'))->toStartWith('2026-10-26 07:00');

    $this->travelTo('2026-10-26 07:05');
    (new EnviarReportesProgramados)->handle(app(ReporteService::class));

    Notification::assertSentTo($e['coordinador'], ReporteProgramado::class, function (ReporteProgramado $reporte, array $canales) {
        return $canales === ['mail'] && $reporte->queue === 'notificaciones'
            && str_contains($reporte->csv, 'PRN115') && ! str_contains($reporte->csv, 'MAT115');
    });
    expect(DB::table('scheduled_reports')->value('next_run_at'))->toStartWith('2026-11-02 07:00');
});

it('CP-RF25-02 no envía dos veces el mismo reporte aunque el job se repita', function () {
    Notification::fake();
    $e = escenario();
    $this->travelTo('2026-10-21 10:00');
    actingAs($e['coordinador']);
    app(ReporteService::class)->programar($e['coordinador'], 'notas', 'monthly');

    $this->travelTo('2026-11-01 07:30');
    $reportes = app(ReporteService::class);
    expect($reportes->enviarProgramados())->toBe(1)
        ->and($reportes->enviarProgramados())->toBe(0);

    Notification::assertSentToTimes($e['coordinador'], ReporteProgramado::class, 1);
    expect(DB::table('scheduled_reports')->value('next_run_at'))->toStartWith('2026-12-01 07:00');
});

it('CP-RF25-02 no envía el reporte a quien ya no puede verlo', function () {
    Notification::fake();
    $e = escenario();
    $this->travelTo('2026-10-21 10:00');
    actingAs($e['coordinador']);
    app(ReporteService::class)->programar($e['coordinador'], 'asistencia', 'weekly');
    DbContext::system(fn () => DB::table('users')->where('id', $e['coordinador']->id)->update(['is_active' => false]));

    $this->travelTo('2026-10-26 07:05');

    expect(app(ReporteService::class)->enviarProgramados())->toBe(0);
    Notification::assertNothingSent();
});

it('CP-RF25-02 el docente no programa reportes', function () {
    $e = escenario();

    expect(fn () => app(ReporteService::class)->programar($e['docente'], 'asistencia', 'weekly'))->toThrow(AuthorizationException::class);

    expect(DB::table('scheduled_reports')->count())->toBe(0);
});

it('CP-RF33-01 el Jefe filtra los indicadores de su departamento por período', function () {
    $e = escenario();
    $docente = usuarioCon('docente');
    $anterior = DbContext::system(function () use ($e, $docente) {
        $periodo = DB::table('academic_periods')->insertGetId(['name' => '2026-I', 'start_date' => '2026-02-02', 'end_date' => '2026-06-19', 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()]);
        $seccion = DB::table('sections')->insertGetId(['ulid' => (string) str()->ulid(), 'period_id' => $periodo, 'subject_id' => $e['prn'], 'code' => '01',
            'max_capacity' => 40, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('users')->where('id', $docente->id)->update(['last_name' => 'Anterior']);
        DB::table('teacher_profiles')->insert(['user_id' => $docente->id, 'department_id' => $e['dia'], 'created_at' => now(), 'updated_at' => now()]);
        DB::table('teaching_assignments')->insert(['section_id' => $seccion, 'teacher_id' => $docente->id, 'is_responsible' => true, 'started_at' => '2026-02-02',
            'created_at' => now(), 'updated_at' => now()]);

        return $periodo;
    });
    $desempeno = app(DesempenoService::class);

    expect($desempeno->indicadores($e['jefe'])->pluck('last_name')->all())->toBe([$e['docente']->last_name])
        ->and($desempeno->indicadores($e['jefe'], $anterior)->pluck('last_name')->all())->toBe(['Anterior'])
        ->and($desempeno->periodos()->pluck('name')->all())->toBe(['2026-II', '2026-I']);

    actingAs($e['jefe']);
    Livewire::withQueryParams(['periodo' => $anterior])->test(DesempenoDocente::class)->assertSee('Anterior')->assertDontSee($e['docente']->last_name)
        ->set('periodo', null)->assertSee($e['docente']->last_name)->assertDontSee('Anterior');
});

it('CP-RF25-04 escribe las fechas de los reportes como DD/MM/AAAA', function () {
    $e = escenario();
    DbContext::system(fn () => DB::table('academic_alerts')->insert([
        'enrollment_id' => $e['matricula'], 'alert_type' => 'grade_risk', 'opened_at' => '2026-03-09 08:30', 'closed_at' => null,
    ]));
    $reportes = app(ReporteService::class);

    $riesgo = $reportes->generar($e['coordinador'], 'bajo_rendimiento');
    $fecha = $riesgo['filas']->first()[5];

    expect($riesgo['columnas'][5])->toBe('Desde')
        ->and($fecha)->toBe('09/03/2026')
        ->and($fecha)->toMatch('/^\d{2}\/\d{2}\/\d{4}$/');

    // Ningún reporte escribe fechas en ISO (RNF11): guarda de regresión para los cuatro tipos.
    foreach (array_keys(ReporteService::TIPOS) as $tipo) {
        foreach ($reportes->generar($e['coordinador'], $tipo)['filas'] as $fila) {
            foreach ($fila as $celda) {
                expect((string) $celda)->not->toMatch('/^\d{4}-\d{2}-\d{2}/');
            }
        }
    }
});

it('CP-RF25-05 no programa dos veces el mismo reporte con la misma frecuencia', function () {
    $e = escenario();
    $reportes = app(ReporteService::class);
    actingAs($e['coordinador']);

    $reportes->programar($e['coordinador'], 'asistencia', 'weekly');

    try {
        $reportes->programar($e['coordinador'], 'asistencia', 'weekly');
        $this->fail('Se esperaba el rechazo del envío repetido.');
    } catch (ValidationException $error) {
        expect($error->errors()['tipo'][0])->toBe('Ya tiene programado «Asistencia por sección» con esa frecuencia. Cancele el envío de la lista si quiere cambiarlo.');
    }

    expect(DB::table('scheduled_reports')->count())->toBe(1);

    // El mismo reporte con otra frecuencia sí es un envío distinto.
    $reportes->programar($e['coordinador'], 'asistencia', 'monthly');

    expect(DB::table('scheduled_reports')->where('user_id', $e['coordinador']->id)->orderBy('frequency')->pluck('frequency')->all())->toBe(['monthly', 'weekly']);
});

it('CP-RF25-05 el índice único rechaza el envío duplicado aunque la lectura previa no lo vea', function () {
    $e = escenario();
    actingAs($e['coordinador']);
    app(ReporteService::class)->programar($e['coordinador'], 'notas', 'weekly');

    expect(fn () => DbContext::system(fn () => DB::table('scheduled_reports')->insert([
        'user_id' => $e['coordinador']->id, 'report_type' => 'notas', 'filters' => json_encode([]), 'frequency' => 'weekly',
        'next_run_at' => now()->addWeek(), 'created_at' => now(), 'updated_at' => now(),
    ])))->toThrow(UniqueConstraintViolationException::class)
        ->and(DB::table('scheduled_reports')->count())->toBe(1);
});
