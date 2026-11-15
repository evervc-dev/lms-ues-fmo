<?php

use App\Modules\Identidad\Livewire\EscenarioDetalle;
use App\Modules\Identidad\Livewire\Escenarios;
use App\Modules\Identidad\Services\EscenarioService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/**
 * Lo mismo que `baseDeEscenarios()` del otro archivo: con `pest --parallel` cada archivo corre en su
 * propio proceso, así que las funciones no se comparten entre archivos.
 *
 * @return array<string, mixed>
 */
function entornoDePantalla(): array
{
    $e = escenario();

    return DbContext::system(function () use ($e) {
        $ahora = now();
        $t = fn (array $fila) => $fila + ['created_at' => $ahora, 'updated_at' => $ahora];

        foreach (['BAD115' => 'Bases de Datos I', 'ESD115' => 'Estructuras de Datos', 'RED115' => 'Redes', 'DPW115' => 'Desarrollo Web', 'DSI115' => 'Diseño de Sistemas I', 'ISI115' => 'Introducción', 'IDI115' => 'Inglés I', 'PSI115' => 'Psicología'] as $codigo => $nombre) {
            $id = DB::table('subjects')->insertGetId($t(['code' => $codigo, 'name' => $nombre, 'credits' => 4, 'department_id' => $e['dia']]));
            DB::table('career_subjects')->insert(['study_plan_id' => $e['plan'], 'subject_id' => $id, 'cycle' => 1]);
        }

        foreach ([[1, 30, '2026-07-27', '2026-09-06'], [2, 30, '2026-09-07', '2026-10-18'], [3, 40, '2026-10-19', '2026-12-11']] as [$numero, $peso, $desde, $hasta]) {
            DB::table('grading_component_templates')->insert($t(['period_id' => $e['periodo'], 'number' => $numero, 'name' => "Cómputo {$numero}", 'starts_on' => $desde, 'ends_on' => $hasta, 'weight_percent' => $peso]));
        }

        DB::table('attendance_areas')->insert($t(['name' => 'Campus', 'area_type' => 'circle', 'center_lat' => 13.4393, 'center_lng' => -88.1597, 'radius_m' => 450, 'is_active' => true]));

        return $e;
    });
}

beforeEach(function () {
    config()->set('lms.dev_login', true);
    config()->set('lms.escenarios_clave', 'clave-de-prueba');
});

it('el wizard no existe sin LMS_DEV_LOGIN', function () {
    config()->set('lms.dev_login', false);

    get(route('dev.escenarios'))->assertNotFound();
});

it('el inicio de sesión enlaza el wizard cuando el entorno de pruebas está activo', function () {
    get(route('login'))->assertOk()->assertSee('Escenarios de prueba')->assertSee(route('dev.escenarios'));

    config()->set('lms.dev_login', false);
    get(route('login'))->assertOk()->assertDontSee('Escenarios de prueba');
});

it('pide la clave del entorno antes de dejar preparar', function () {
    entornoDePantalla();

    Livewire::test(Escenarios::class)
        ->assertSet('autorizado', false)
        ->call('elegirFlujo', 'asistencia-qr')
        ->call('preparar')
        ->assertHasErrors('clave')
        ->set('clave', 'la-que-no-es')
        ->call('autorizar')
        ->assertHasErrors('clave')
        ->set('clave', 'clave-de-prueba')
        ->call('autorizar')
        ->assertSet('autorizado', true)
        ->assertHasNoErrors();

    expect(DB::table('test_scenarios')->count())->toBe(0);
});

it('prepara un escenario desde el wizard y lleva a su página', function () {
    $e = entornoDePantalla();

    Livewire::test(Escenarios::class)
        ->set('clave', 'clave-de-prueba')->call('autorizar')
        ->call('elegirFlujo', 'asistencia-qr')
        ->assertSet('paso', 2)
        ->call('alternar', 'docente', $e['docente']->id)
        ->call('alternar', 'estudiante', $e['estudiante']->id)
        ->set('relleno', 2)
        ->call('irAlPaso', 3)
        ->call('preparar')
        ->assertHasNoErrors()
        ->assertRedirect();

    $escenario = DB::table('test_scenarios')->first();

    expect($escenario->flow)->toBe('asistencia-qr')
        ->and(DB::table('test_scenario_actors')->where('scenario_id', $escenario->id)->count())->toBe(2);
});

it('con tope de uno, elegir a otra persona sustituye a la anterior', function () {
    $e = entornoDePantalla();

    Livewire::test(Escenarios::class)
        ->set('clave', 'clave-de-prueba')->call('autorizar')
        ->call('elegirFlujo', 'asistencia-qr')
        ->call('alternar', 'docente', $e['docente']->id)
        ->call('alternar', 'docente', $e['coordinador']->id)
        ->assertSet('reparto.docente', [$e['coordinador']->id]);
});

it('la página del escenario muestra enlaces y checklist, y la ve un invitado', function () {
    $e = entornoDePantalla();
    $escenario = app(EscenarioService::class)->preparar('asistencia-qr', ['docente' => [$e['docente']->id], 'estudiante' => [$e['estudiante']->id]], relleno: 1);

    get(route('dev.escenarios.show', ['codigo' => $escenario->code]))
        ->assertOk()
        ->assertSee($escenario->code)
        ->assertSee('Toma de asistencia con QR')
        ->assertSee('CP-RF20-01')
        ->assertSee('Inicie sesión con su cuenta');

    get(route('dev.escenarios.show', ['codigo' => 'EZZZZ']))->assertNotFound();
});

it('marcar un caso exige sesión y queda a nombre de quien lo marcó', function () {
    $e = entornoDePantalla();
    $escenario = app(EscenarioService::class)->preparar('cuestionario', ['docente' => [$e['docente']->id], 'estudiante' => [$e['estudiante']->id]], relleno: 0);
    $caso = DB::table('test_scenario_checks')->where('scenario_id', $escenario->id)->orderBy('position')->first();

    // Sin sesión, no se marca.
    Livewire::test(EscenarioDetalle::class, ['codigo' => $escenario->code])
        ->call('marcar', $caso->id, 'pass')
        ->assertHasErrors('checklist');

    expect(DB::table('test_scenario_checks')->where('id', $caso->id)->value('status'))->toBe('pending');

    actingAs($e['docente']);

    Livewire::test(EscenarioDetalle::class, ['codigo' => $escenario->code])
        ->call('abrirNota', $caso->id)
        ->set('nota', 'El cronómetro se reinicia al recargar.')
        ->call('marcar', $caso->id, 'fail')
        ->assertHasNoErrors();

    $marcado = DB::table('test_scenario_checks')->where('id', $caso->id)->first();

    expect($marcado->status)->toBe('fail')
        ->and($marcado->note)->toBe('El cronómetro se reinicia al recargar.')
        ->and((int) $marcado->checked_by)->toBe($e['docente']->id)
        ->and($marcado->checked_at)->not->toBeNull();
});

it('solo un Administrador puede limpiar desde la página', function () {
    $e = entornoDePantalla();
    $escenario = app(EscenarioService::class)->preparar('cuestionario', ['docente' => [$e['docente']->id], 'estudiante' => [$e['estudiante']->id]], relleno: 0);

    actingAs($e['docente']);
    Livewire::test(EscenarioDetalle::class, ['codigo' => $escenario->code])->call('limpiar')->assertHasErrors('checklist');
    expect(DB::table('test_scenarios')->where('id', $escenario->id)->value('status'))->toBe('ready');

    actingAs($e['administrador']);
    Livewire::test(EscenarioDetalle::class, ['codigo' => $escenario->code])->call('limpiar')->assertRedirect(route('dev.escenarios'));
    expect(DB::table('test_scenarios')->where('id', $escenario->id)->value('status'))->toBe('cleaned');
});

it('sin clave configurada, solo un Administrador con sesión prepara', function () {
    config()->set('lms.escenarios_clave', '');
    $e = entornoDePantalla();

    Livewire::test(Escenarios::class)->assertSet('autorizado', false);

    actingAs($e['docente']);
    Livewire::test(Escenarios::class)->assertSet('autorizado', false);

    actingAs($e['administrador']);
    Livewire::test(Escenarios::class)->assertSet('autorizado', true);
});

it('el comando prepara, lista y limpia', function () {
    entornoDePantalla();

    $this->artisan('escenario preparar cuestionario --docente=vc21033 --estudiante=rr19118 --relleno=2')
        ->assertSuccessful();

    $escenario = DB::table('test_scenarios')->first();

    expect($escenario->flow)->toBe('cuestionario')
        // Las cuentas de los testers se crearon con su correo institucional real.
        ->and(DB::table('users')->where('institutional_email', 'vc21033@ues.edu.sv')->exists())->toBeTrue();

    $this->artisan('escenario listar')->expectsOutputToContain($escenario->code)->assertSuccessful();
    $this->artisan("escenario limpiar {$escenario->code}")->assertSuccessful();

    expect(DB::table('test_scenarios')->where('id', $escenario->id)->value('status'))->toBe('cleaned');
});

it('el comando rechaza un DUE que no está en el catálogo', function () {
    entornoDePantalla();

    $this->artisan('escenario preparar cuestionario --docente=xx00000 --estudiante=rr19118')->assertFailed();

    expect(DB::table('test_scenarios')->count())->toBe(0);
});
