<?php

use App\Modules\Academica\Livewire\Carreras;
use App\Modules\Academica\Livewire\Departamentos;
use App\Modules\Academica\Services\CarreraService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * El Administrador navega Departamentos → Carreras → Carrera con alcance global (RF07, ADR-083).
 */

/** Carrera de Matemática en DCNM, el departamento que no dirige el Jefe del escenario. */
function carreraDeDcnm(array $e): int
{
    return DbContext::system(fn () => DB::table('careers')->insertGetId([
        'department_id' => $e['dcnm'], 'code' => 'LMA', 'name' => 'Licenciatura en Matemática', 'created_at' => now(), 'updated_at' => now(),
    ]));
}

it('CP-RF07-22 el Administrador ve todos los departamentos con su jefe y sus conteos', function () {
    $e = escenario();
    carreraDeDcnm($e);

    $departamentos = app(CarreraService::class)->departamentos($e['administrador'])->keyBy('code');
    expect($departamentos->keys()->all())->toBe(['DCNM', 'DIA'])
        ->and($departamentos['DIA']->jefe)->toBe($e['jefe']->first_name.' '.$e['jefe']->last_name)
        ->and($departamentos['DIA']->carreras)->toBe(1)
        ->and($departamentos['DIA']->grupos)->toBe(1)
        ->and($departamentos['DCNM']->jefe)->toBeNull()
        ->and($departamentos['DCNM']->carreras)->toBe(1);

    actingAs($e['administrador'])->get(route('departamentos'))->assertOk()
        ->assertSee('Ciencias Naturales y Matemática')->assertSee(route('carreras', ['departamento' => $e['dcnm']]), false);
});

it('CP-RF07-22 filtra las carreras del departamento elegido y abre el detalle de cualquiera', function () {
    $e = escenario();
    $lma = carreraDeDcnm($e);
    actingAs($e['administrador']);

    Livewire::withQueryParams(['departamento' => $e['dcnm']])->test(Carreras::class)
        ->assertSee('Licenciatura en Matemática')
        ->assertDontSee('Ingeniería de Sistemas Informáticos');

    $this->get(route('carreras.show', ['carrera' => $lma]))->assertOk()->assertSee('Licenciatura en Matemática');
});

it('CP-RF07-22 el Jefe y el Coordinador no entran a Departamentos y el filtro no amplía su alcance', function () {
    $e = escenario();
    carreraDeDcnm($e);

    foreach (['jefe', 'coordinador'] as $rol) {
        expect(fn () => app(CarreraService::class)->departamentos($e[$rol]))->toThrow(AuthorizationException::class);
        actingAs($e[$rol])->get(route('departamentos'))->assertForbidden();
    }

    actingAs($e['jefe']);
    expect(app(CarreraService::class)->listar($e['jefe'], $e['dcnm'])->pluck('code')->all())->toBe(['ISI']);
    Livewire::withQueryParams(['departamento' => $e['dcnm']])->test(Carreras::class)
        ->assertSee('Ingeniería de Sistemas Informáticos')
        ->assertDontSee('Licenciatura en Matemática');
});

it('CP-RF07-22 el Administrador crea, edita y elimina departamentos desde Departamentos', function () {
    $e = escenario();
    actingAs($e['administrador']);

    $pantalla = Livewire::test(Departamentos::class)
        ->assertSee(__('Nuevo departamento'))
        ->call('nuevoDepartamento')
        ->set('departamentoCodigo', 'dce')
        ->set('departamentoNombre', 'Departamento de Ciencias Económicas')
        ->call('guardarDepartamento')
        ->assertHasNoErrors()
        ->assertSee('Departamento de Ciencias Económicas');
    $id = (int) DB::table('departments')->where('code', 'DCE')->value('id');
    expect($id)->toBeGreaterThan(0);

    $pantalla->call('editarDepartamento', $id)
        ->assertSet('departamentoCodigo', 'DCE')
        ->set('departamentoNombre', 'Departamento de Economía')
        ->call('guardarDepartamento')
        ->assertHasNoErrors();
    expect(DB::table('departments')->where('id', $id)->value('name'))->toBe('Departamento de Economía');

    $pantalla->call('nuevoDepartamento')->set('departamentoCodigo', 'DIA')->set('departamentoNombre', 'Otro')
        ->call('guardarDepartamento')->assertHasErrors('departamentoCodigo');

    $pantalla->call('eliminarDepartamento', $id);
    expect(DB::table('departments')->where('id', $id)->exists())->toBeFalse();
});
