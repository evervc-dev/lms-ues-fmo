<?php

use App\Modules\Academica\Livewire\Oferta;
use App\Modules\Academica\Services\OfertaService;
use App\Modules\Calificaciones\Livewire\Cierre;
use App\Modules\Reportes\Livewire\Reportes;
use App\Modules\Reportes\Services\ReporteService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Correcciones del informe de QA del rol Jefe de Departamento (31/10/2026): la oferta dice la carrera y el
 * ciclo de cada asignatura, el cierre explica por qué no se puede autorizar, el panel aclara de quién son
 * sus cifras, los reportes dicen la carrera y se filtran por período (R-12) con gráfico (R-11), y la Ayuda
 * no usa emojis.
 */

/** Una segunda carrera, en el departamento de Matemática, con MAT115 en el ciclo 2 de su plan. */
function carreraDeMatematica(array $e): int
{
    return DbContext::system(function () use ($e) {
        $carrera = DB::table('careers')->insertGetId(['department_id' => $e['dcnm'], 'code' => 'LMA', 'name' => 'Licenciatura en Matemática', 'created_at' => now(), 'updated_at' => now()]);
        $plan = DB::table('study_plans')->insertGetId(['career_id' => $carrera, 'name' => 'Plan 20', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('career_subjects')->insert(['study_plan_id' => $plan, 'subject_id' => $e['mat'], 'cycle' => 2]);

        return $carrera;
    });
}

/* ------------------------------------------------------------------ Oferta por carrera y ciclo (RF10) */

it('la oferta muestra la carrera y el ciclo del plan de cada asignatura, y avisa la que no tiene plan', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(Oferta::class)
        ->assertSee('ISI · Ciclo 1')
        ->assertSee('Sin plan de estudios asignado');
});

it('la oferta filtra por carrera dentro del alcance', function () {
    $e = escenario();
    $lma = carreraDeMatematica($e);
    $oferta = app(OfertaService::class);

    expect($oferta->estadisticas($e['administrador'])['filas']->pluck('code')->all())->toBe(['MAT115', 'PRN115'])
        ->and($oferta->estadisticas($e['administrador'], $e['isi'])['filas']->pluck('code')->all())->toBe(['PRN115'])
        ->and($oferta->estadisticas($e['administrador'], $lma)['filas']->pluck('code')->all())->toBe(['MAT115'])
        ->and($oferta->estadisticas($e['administrador'])['carreras']->pluck('code')->all())->toBe(['ISI', 'LMA']);
});

it('el filtro de carrera solo ofrece las del alcance e ignora una carrera ajena', function () {
    $e = escenario();
    $lma = carreraDeMatematica($e);

    $delJefe = app(OfertaService::class)->estadisticas($e['jefe'], $lma);
    expect($delJefe['carreras']->pluck('code')->all())->toBe(['ISI'])
        ->and($delJefe['carrera'])->toBeNull()
        ->and($delJefe['filas']->pluck('code')->all())->toBe(['PRN115']);

    actingAs($e['coordinador']);
    Livewire::withQueryParams(['carrera' => (string) $lma])->test(Oferta::class)
        ->assertSet('filtroCarrera', '')
        ->assertDontSee('MAT115');
});

/* ------------------------------------------------------------------ Aviso del cierre (RF34) */

it('el cierre explica al Jefe cuántas actas faltan y cuáles mientras el botón está deshabilitado', function () {
    $e = escenario();
    actingAs($e['jefe']);

    Livewire::test(Cierre::class)
        ->assertSee('Todavía no puede autorizar el cierre')
        ->assertSee('Falta 1 acta por validar de 1.')
        ->assertSee('Secciones pendientes: PRN115-01.');
});

it('con todas las actas validadas el cierre ya no muestra el aviso', function () {
    $e = escenario();
    DbContext::system(fn () => DB::table('grade_sheets')->insert([
        'section_id' => $e['seccion'], 'status' => 'validated', 'published_by' => $e['docente']->id, 'published_at' => now(),
        'validated_by' => $e['coordinador']->id, 'validated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]));
    actingAs($e['jefe']);

    Livewire::test(Cierre::class)->assertDontSee('Todavía no puede autorizar el cierre');
});

/* ------------------------------------------------------------------ Etiquetas del panel (RF24) */

it('el panel del Jefe dice que la asistencia y la matrícula son de los estudiantes', function () {
    $e = escenario();

    actingAs($e['jefe'])->get(route('inicio'))->assertOk()
        ->assertSeeText(['Matrículas activas de estudiantes', 'Asistencia de estudiantes', 'no incluye la de los docentes', 'Secciones sin docente asignado'])
        ->assertDontSeeText('Matrícula del período');
});

/* ------------------------------------------------------------------ Reportes con carrera, período y gráfico (RF25) */

/** Un período cerrado con una sección de PRN115: se crea planificado y se cierra, como en la realidad. */
function periodoCerradoConSeccion(array $e): int
{
    return DbContext::system(function () use ($e) {
        $periodo = DB::table('academic_periods')->insertGetId(['name' => '2026-I', 'start_date' => '2026-02-02', 'end_date' => '2026-06-19', 'status' => 'planned', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sections')->insert(['ulid' => (string) str()->ulid(), 'period_id' => $periodo, 'subject_id' => $e['prn'], 'code' => '07', 'max_capacity' => 40, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('academic_periods')->where('id', $periodo)->update(['status' => 'closed']);

        return $periodo;
    });
}

it('CP-RF25-06 los reportes por sección dicen de qué carrera es cada asignatura, también si está en varias', function () {
    $e = escenario();
    $lma = carreraDeMatematica($e);
    DbContext::system(fn () => DB::table('career_subjects')->insert(['study_plan_id' => DB::table('study_plans')->where('career_id', $lma)->value('id'), 'subject_id' => $e['prn'], 'cycle' => 3]));

    $asistencia = app(ReporteService::class)->generar($e['administrador'], 'asistencia');

    expect($asistencia['columnas'][2])->toBe('Carrera')
        ->and($asistencia['filas']->mapWithKeys(fn (array $f) => [$f[0] => $f[2]])->all())->toBe(['MAT115 · 01' => 'LMA', 'PRN115 · 01' => 'ISI, LMA']);
});

it('CP-RF25-06 estudiantes en riesgo dice la carrera del estudiante', function () {
    $e = escenario();
    DbContext::system(fn () => DB::table('academic_alerts')->insert(['enrollment_id' => $e['matricula'], 'alert_type' => 'grade_risk', 'opened_at' => now(), 'closed_at' => null]));

    $riesgo = app(ReporteService::class)->generar($e['jefe'], 'bajo_rendimiento');

    expect($riesgo['columnas'][2])->toBe('Carrera')->and($riesgo['filas']->first()[2])->toBe('ISI');
});

it('CP-RF25-06 los reportes se filtran por carrera dentro del alcance e ignoran una carrera ajena', function () {
    $e = escenario();
    $lma = carreraDeMatematica($e);
    $reportes = app(ReporteService::class);

    expect($reportes->generar($e['administrador'], 'asistencia', carrera: $lma)['filas']->pluck(0)->all())->toBe(['MAT115 · 01'])
        ->and($reportes->generar($e['administrador'], 'asistencia', carrera: $e['isi'])['filas']->pluck(0)->all())->toBe(['PRN115 · 01']);

    $delJefe = $reportes->generar($e['jefe'], 'asistencia', carrera: $lma);
    expect($delJefe['carrera'])->toBeNull()->and($delJefe['filas']->pluck(0)->all())->toBe(['PRN115 · 01']);
});

it('CP-RF25-07 los reportes se consultan de un período cerrado y vuelven al en curso con un período no consultable', function () {
    $e = escenario();
    $cerrado = periodoCerradoConSeccion($e);
    $reportes = app(ReporteService::class);

    expect($reportes->generar($e['administrador'], 'asistencia', $cerrado)['filas']->pluck(0)->all())->toBe(['PRN115 · 07'])
        ->and($reportes->generar($e['administrador'], 'asistencia', 999_999)['periodo']->name)->toBe('2026-II')
        ->and($reportes->periodos()->pluck('name')->all())->toBe(['2026-II', '2026-I']);

    actingAs($e['administrador']);
    Livewire::withQueryParams(['periodo' => $cerrado])->test(Reportes::class)
        ->assertSee('PRN115 · 07')->assertDontSee('PRN115 · 01')
        ->set('periodo', null)->assertSee('PRN115 · 01');
});

it('CP-RF25-07 la exportación lleva el período y la carrera elegidos', function () {
    $e = escenario();
    $cerrado = periodoCerradoConSeccion($e);
    actingAs($e['administrador']);

    Livewire::withQueryParams(['periodo' => $cerrado, 'carrera' => $e['isi']])->test(Reportes::class)
        ->call('exportar', 'csv')->assertFileDownloaded('reporte-asistencia-'.now()->format('Y-m-d').'.csv');
    $csv = ReporteService::csv(app(ReporteService::class)->generar($e['administrador'], 'asistencia', $cerrado, $e['isi']));

    expect($csv)->toContain('PRN115 · 07')->not->toContain('PRN115 · 01');
});

it('CP-RF25-08 los reportes dibujan un gráfico de la medida principal, sin las filas sin dato', function () {
    $e = escenario();
    DbContext::system(fn () => DB::table('academic_alerts')->insert([
        ['enrollment_id' => $e['matricula'], 'alert_type' => 'grade_risk', 'opened_at' => now(), 'closed_at' => null],
        ['enrollment_id' => $e['matricula'], 'alert_type' => 'attendance_75', 'opened_at' => now(), 'closed_at' => null],
    ]));
    $reportes = app(ReporteService::class);

    $carga = ReporteService::grafico('carga_docente', $reportes->generar($e['administrador'], 'carga_docente')['filas']);
    expect($carga['etiquetas'])->toBe([$e['docente']->last_name.', '.$e['docente']->first_name])->and($carga['valores'])->toBe([1.0]);

    // Sin clases registradas no hay porcentaje: la sección no se dibuja como un 0 %.
    expect(ReporteService::grafico('asistencia', $reportes->generar($e['administrador'], 'asistencia')['filas'])['valores'])->toBe([]);

    $riesgo = ReporteService::grafico('bajo_rendimiento', $reportes->generar($e['administrador'], 'bajo_rendimiento')['filas']);
    expect(array_combine($riesgo['etiquetas'], $riesgo['valores']))->toEqual(['Riesgo de reprobar' => 1.0, 'Asistencia < 75 %' => 1.0]);

    actingAs($e['administrador']);
    Livewire::withQueryParams(['tipo' => 'carga_docente'])->test(Reportes::class)
        ->assertSeeHtml('graficoReporte')->assertSee('Estudiantes a cargo de cada docente');
});

it('CP-RF25-06 el docente ve la carrera en sus reportes pero no tiene filtro de carrera', function () {
    $e = escenario();
    actingAs($e['docente']);

    Livewire::test(Reportes::class)->assertSee('ISI')->assertDontSee('Todas las carreras de su alcance');
});

/* ------------------------------------------------------------------ Sin emojis en la Ayuda */

it('la Ayuda escribe los avisos de los manuales con palabras y sin emojis', function () {
    $e = escenario();

    actingAs($e['jefe'])->get(route('ayuda', ['manual' => 'bienvenida']))->assertOk()
        ->assertSee('Atención: Solo un teléfono')->assertDontSee(['⚠️', '💡', 'ℹ️', '⛔', '✅']);
});
