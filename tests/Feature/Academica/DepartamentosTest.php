<?php

use App\Modules\Academica\Livewire\Estructura;
use App\Modules\Academica\Services\CatalogoService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/*
| Departamentos del catálogo (RF07): el Administrador (asignaturas.gestionar G) los crea, edita su código y nombre
| y los elimina solo si nada los usa. No hay desactivación: `departments` solo tiene code y name en el ER.
*/

function departamentos(): CatalogoService
{
    return app(CatalogoService::class);
}

/** Un departamento sin carreras, asignaturas ni personas. */
function departamentoSuelto(string $codigo = 'DCE', string $nombre = 'Ciencias Económicas'): int
{
    return DbContext::system(fn () => DB::table('departments')->insertGetId([
        'code' => $codigo, 'name' => $nombre, 'created_at' => now(), 'updated_at' => now(),
    ]));
}

it('CP-RF07-08 crea un departamento con el código en mayúsculas', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(Estructura::class)->call('nuevoDepartamento')
        ->set('departamentoCodigo', ' dce ')->set('departamentoNombre', 'Ciencias Económicas')
        ->call('guardarDepartamento')->assertHasNoErrors()->assertDispatched('aviso', tipo: 'exito', mensaje: 'Departamento creado.');

    expect(DB::table('departments')->where('code', 'DCE')->value('name'))->toBe('Ciencias Económicas');
});

it('CP-RF07-08 rechaza un código de departamento que ya existe, sin distinguir mayúsculas', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(Estructura::class)->call('nuevoDepartamento')
        ->set('departamentoCodigo', 'dia')->set('departamentoNombre', 'Otro')
        ->call('guardarDepartamento')->assertHasErrors(['departamentoCodigo']);

    expect(DB::table('departments')->count())->toBe(2);
});

it('CP-RF07-08 exige el código y el nombre del departamento', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(Estructura::class)->call('nuevoDepartamento')->call('guardarDepartamento')
        ->assertHasErrors(['departamentoCodigo' => 'required', 'departamentoNombre' => 'required']);
});

it('CP-RF07-09 edita el código y el nombre de un departamento', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(Estructura::class)->call('editarDepartamento', $e['dia'])
        ->assertSet('departamentoCodigo', 'DIA')
        ->set('departamentoCodigo', 'dia2')->set('departamentoNombre', 'Ingeniería')
        ->call('guardarDepartamento')->assertHasNoErrors()->assertDispatched('aviso', tipo: 'exito', mensaje: 'Departamento actualizado.');

    $fila = DB::table('departments')->where('id', $e['dia'])->first();
    expect($fila->code)->toBe('DIA2')->and($fila->name)->toBe('Ingeniería')
        ->and(DB::table('careers')->where('id', $e['isi'])->value('department_id'))->toBe($e['dia']);
});

it('CP-RF07-09 al editar rechaza el código de otro departamento pero admite el propio', function () {
    $e = escenario();

    expect(fn () => departamentos()->guardarDepartamento($e['administrador'], $e['dia'], 'dcnm', 'Ingeniería'))
        ->toThrow(ValidationException::class, 'Ya existe un departamento con ese código.');

    departamentos()->guardarDepartamento($e['administrador'], $e['dia'], 'dia', 'Ingeniería');
    expect(DB::table('departments')->where('id', $e['dia'])->value('name'))->toBe('Ingeniería');
});

it('CP-RF07-09 audita el alta y la edición del departamento, y no audita guardar sin cambios', function () {
    $e = escenario();
    actingAs($e['administrador']);

    $id = departamentos()->guardarDepartamento($e['administrador'], null, 'DCE', 'Económicas');
    departamentos()->guardarDepartamento($e['administrador'], $id, 'DCE', 'Económicas');
    departamentos()->guardarDepartamento($e['administrador'], $id, 'DCE', 'Ciencias Económicas');

    DbContext::auditScope(all: true);
    $filas = DB::table('audit_logs')->where('entity_type', 'departments')->where('entity_id', $id)->where('actor_user_id', $e['administrador']->id)
        ->orderBy('id')->get(['action', 'old_values', 'new_values']);

    expect($filas->pluck('action')->all())->toBe(['insert', 'update'])
        ->and(json_decode($filas[1]->old_values, true)['name'])->toBe('Económicas')
        ->and(json_decode($filas[1]->new_values, true)['name'])->toBe('Ciencias Económicas');
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'Requiere PostgreSQL');

it('CP-RF07-10 no elimina un departamento con dependencias y nombra cuáles tiene', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(Estructura::class)->call('eliminarDepartamento', $e['dia'])
        ->assertDispatched('aviso', tipo: 'error', mensaje: 'No se puede eliminar el departamento: tiene carreras, asignaturas, docentes, jefaturas, plazos de matrícula.');

    expect(DB::table('departments')->where('id', $e['dia'])->exists())->toBeTrue();
});

it('CP-RF07-10 no elimina un departamento que solo conserva una jefatura ya cerrada', function () {
    $e = escenario();
    $id = departamentoSuelto();
    DbContext::system(fn () => DB::table('department_heads')->insert([
        'user_id' => $e['jefe']->id, 'department_id' => $id, 'valid_from' => '2024-01-01', 'valid_to' => '2024-12-31',
        'created_at' => now(), 'updated_at' => now(),
    ]));

    expect(fn () => departamentos()->eliminarDepartamento($e['administrador'], $id))
        ->toThrow(ValidationException::class, 'No se puede eliminar el departamento: tiene jefaturas.');
});

it('CP-RF07-10 no elimina un departamento con historial en la auditoría', function () {
    $e = escenario();
    actingAs($e['administrador']);
    $id = departamentos()->guardarDepartamento($e['administrador'], null, 'DCE', 'Económicas');
    // La carrera deja en audit_logs una fila con department_id; aunque se borre, la auditoría la conserva.
    DbContext::run(function () use ($id): void {
        $carrera = DB::table('careers')->insertGetId(['department_id' => $id, 'code' => 'LAE', 'name' => 'Administración', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('careers')->where('id', $carrera)->delete();
    });

    expect(fn () => departamentos()->eliminarDepartamento($e['administrador'], $id))
        ->toThrow(ValidationException::class, 'tiene historial en la auditoría');
    expect(DB::table('departments')->where('id', $id)->exists())->toBeTrue();
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'Requiere PostgreSQL');

it('CP-RF07-11 elimina un departamento que nada usa', function () {
    $e = escenario();
    $id = departamentoSuelto();
    actingAs($e['administrador']);

    Livewire::test(Estructura::class)->call('eliminarDepartamento', $id)
        ->assertDispatched('aviso', tipo: 'exito', mensaje: 'Departamento eliminado.');

    expect(DB::table('departments')->where('id', $id)->exists())->toBeFalse();
});

it('CP-RF07-12 deja la estructura al Administrador y se la niega al Jefe', function () {
    $e = escenario();

    actingAs($e['administrador']);
    get(route('catalogo.estructura'))->assertOk()->assertSee('Nuevo departamento');

    actingAs($e['jefe']);
    get(route('catalogo.estructura'))->assertForbidden();
});

it('CP-RF07-12 el Service rechaza al Jefe al crear, editar o eliminar un departamento', function () {
    $e = escenario();
    $id = departamentoSuelto();

    expect(fn () => departamentos()->guardarDepartamento($e['jefe'], null, 'DX', 'Nuevo'))->toThrow(AuthorizationException::class)
        ->and(fn () => departamentos()->guardarDepartamento($e['jefe'], $e['dia'], 'DIA', 'Otro nombre'))->toThrow(AuthorizationException::class)
        ->and(fn () => departamentos()->eliminarDepartamento($e['jefe'], $id))->toThrow(AuthorizationException::class);

    expect(DB::table('departments')->where('id', $id)->exists())->toBeTrue()
        ->and(DB::table('departments')->where('id', $e['dia'])->value('name'))->toBe('Ingeniería y Arquitectura');
});
