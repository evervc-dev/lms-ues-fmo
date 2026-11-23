<?php

use App\Modules\Academica\Livewire\Departamentos;
use App\Modules\Academica\Livewire\Periodos;
use App\Modules\Academica\Services\CarreraService;
use App\Modules\Academica\Services\CatalogoService;
use App\Modules\Academica\Services\PeriodoService;
use App\Modules\Identidad\Services\AsignacionService;
use App\Modules\Reportes\Services\ReporteService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Archivar períodos, departamentos, carreras y asignaturas (RF06, RF07, ADR-092): lo archivado sale de las listas y
 * los selectores y no admite cambios; se ve aparte y se puede desarchivar. Se rechaza archivar lo que aún se usa.
 */
function cat(): CatalogoService
{
    return app(CatalogoService::class);
}

/** Departamento vacío (sin carreras, asignaturas ni jefatura), creado por el Administrador. */
function departamentoVacio(array $e, string $codigo = 'DQX'): int
{
    actingAs($e['administrador']);

    return cat()->guardarDepartamento($e['administrador'], null, $codigo, 'Departamento '.$codigo);
}

function periodoCerrado(): int
{
    return DbContext::system(fn () => DB::table('academic_periods')->insertGetId([
        'name' => '2025-I', 'start_date' => '2025-02-01', 'end_date' => '2025-06-30', 'status' => 'closed', 'created_at' => now(), 'updated_at' => now(),
    ]));
}

it('CP-RF06-14 el Administrador archiva un período cerrado: sale de la lista y de los informes, y se desarchiva', function () {
    $e = escenario();
    $cerrado = periodoCerrado();
    actingAs($e['administrador']);
    $periodos = app(PeriodoService::class);

    $periodos->archivar($e['administrador'], $cerrado, true);

    expect($periodos->listar($e['administrador'])->pluck('id')->all())->not->toContain($cerrado)
        ->and($periodos->listar($e['administrador'], true)->pluck('id')->all())->toBe([$cerrado])
        ->and(app(ReporteService::class)->periodos()->pluck('id')->all())->not->toContain($cerrado);
    Livewire::test(Periodos::class)->set('archivados', true)->assertSee('2025-I')->call('archivar', $cerrado, false);
    expect(DB::table('academic_periods')->where('id', $cerrado)->value('archived_at'))->toBeNull();
});

it('CP-RF06-14 no archiva un período que no está cerrado', function () {
    $e = escenario();
    actingAs($e['administrador']);

    expect(fn () => app(PeriodoService::class)->archivar($e['administrador'], $e['periodo'], true))->toThrow(ValidationException::class)
        ->and(DB::table('academic_periods')->where('id', $e['periodo'])->value('archived_at'))->toBeNull();
});

it('CP-RF07-28 no archiva un departamento con carreras, asignaturas activas o jefatura vigente, y lo dice', function () {
    $e = escenario();
    actingAs($e['administrador']);

    try {
        cat()->archivarDepartamento($e['administrador'], $e['dia'], true);
        $this->fail('Debía rechazarlo.');
    } catch (ValidationException $error) {
        expect($error->errors()['departamento'][0])->toContain('carreras sin archivar')->toContain('asignaturas activas')->toContain('una jefatura vigente');
    }
    expect(DB::table('departments')->where('id', $e['dia'])->value('archived_at'))->toBeNull();
});

it('CP-RF07-28 el departamento archivado sale de las listas y los selectores, y no admite carreras ni asignaturas nuevas', function () {
    $e = escenario();
    $vacio = departamentoVacio($e);

    Livewire::test(Departamentos::class)->call('archivarDepartamento', $vacio, true)->assertDontSee('Departamento DQX')
        ->set('archivados', true)->assertSee('Departamento DQX')->assertSee('Desarchivar');

    expect(app(CarreraService::class)->departamentos($e['administrador'])->pluck('id')->all())->not->toContain($vacio)
        ->and(cat()->departamentosGestionables($e['administrador'])->pluck('id')->all())->not->toContain($vacio)
        ->and(app(AsignacionService::class)->opciones($e['administrador'])['departamentos']->pluck('id')->all())->not->toContain($vacio)
        ->and(fn () => cat()->crearCarrera($e['administrador'], $vacio, 'LQX', 'Licenciatura QX', 'Plan 26'))->toThrow(ValidationException::class, 'Elija un departamento válido')
        ->and(fn () => cat()->guardarAsignatura($e['administrador'], null, ['codigo' => 'QX101', 'nombre' => 'QX', 'creditos' => 4, 'departamento' => $vacio,
            'planes' => [['plan' => $e['plan'], 'ciclo' => 1]]]))->toThrow(ValidationException::class, 'El departamento está archivado');

    cat()->archivarDepartamento($e['administrador'], $vacio, false);
    expect(app(CarreraService::class)->departamentos($e['administrador'])->pluck('id')->all())->toContain($vacio);
});

it('CP-RF07-29 no archiva una carrera con estudiantes activos o coordinación vigente', function () {
    $e = escenario();
    actingAs($e['administrador']);

    try {
        cat()->archivarCarrera($e['administrador'], $e['isi'], true);
        $this->fail('Debía rechazarlo.');
    } catch (ValidationException $error) {
        expect($error->errors()['carrera'][0])->toContain('estudiantes activos')->toContain('una coordinación vigente');
    }
});

it('CP-RF07-29 la carrera archivada sale de las listas y los selectores y no admite planes ni asignaturas', function () {
    $e = escenario();
    $vacio = departamentoVacio($e);
    $carrera = cat()->crearCarrera($e['administrador'], $vacio, 'LQX', 'Licenciatura QX', 'Plan 26');
    $plan = (int) DB::table('study_plans')->where('career_id', $carrera)->value('id');

    cat()->archivarCarrera($e['administrador'], $carrera, true);

    expect(app(CarreraService::class)->listar($e['administrador'])->pluck('id')->all())->not->toContain($carrera)
        ->and(app(CarreraService::class)->listar($e['administrador'], null, true)->pluck('id')->all())->toBe([$carrera])
        ->and(cat()->carrerasConPlanes($e['administrador'])->pluck('id')->all())->not->toContain($carrera)
        ->and(app(AsignacionService::class)->opciones($e['administrador'])['carreras']->pluck('id')->all())->not->toContain($carrera)
        ->and(fn () => cat()->agregarPlan($e['administrador'], $carrera, 'Plan 27'))->toThrow(ValidationException::class, 'está archivada')
        ->and(fn () => cat()->guardarAsignatura($e['administrador'], null, ['codigo' => 'QX101', 'nombre' => 'QX', 'creditos' => 4, 'departamento' => $e['dia'],
            'planes' => [['plan' => $plan, 'ciclo' => 1]]]))->toThrow(ValidationException::class, 'está archivada');

    // Con su departamento archivado no se desarchiva: primero el departamento.
    cat()->archivarDepartamento($e['administrador'], $vacio, true);
    expect(fn () => cat()->archivarCarrera($e['administrador'], $carrera, false))->toThrow(ValidationException::class);
    cat()->archivarDepartamento($e['administrador'], $vacio, false);
    cat()->archivarCarrera($e['administrador'], $carrera, false);
    expect(DB::table('careers')->where('id', $carrera)->value('archived_at'))->toBeNull();
});

it('CP-RF07-30 solo el Administrador archiva períodos, departamentos y carreras', function (string $rol) {
    $e = escenario();
    $cerrado = periodoCerrado();
    $vacio = departamentoVacio($e);
    actingAs($e[$rol]);

    expect(fn () => cat()->archivarDepartamento($e[$rol], $vacio, true))->toThrow(AuthorizationException::class)
        ->and(fn () => cat()->archivarCarrera($e[$rol], $e['isi'], true))->toThrow(AuthorizationException::class)
        ->and(fn () => app(PeriodoService::class)->archivar($e[$rol], $cerrado, true))->toThrow(AuthorizationException::class)
        ->and(DB::table('departments')->where('id', $vacio)->value('archived_at'))->toBeNull();
})->with(['jefe', 'coordinador', 'docente', 'estudiante']);
