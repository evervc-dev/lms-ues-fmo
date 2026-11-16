<?php

use App\Modules\Academica\Livewire\Catalogo;
use App\Modules\Academica\Services\CargaAcademicaService;
use App\Modules\Academica\Services\CatalogoService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
| Catálogo de asignaturas (RF07): eliminar solo lo que nada usa, desactivar lo demás (CP-RF07-02) y
| filtrar por departamento, créditos o nombre (CP-RF07-04). Lo mantiene el Administrador (asignaturas.gestionar G).
*/

function catalogo(): CatalogoService
{
    return app(CatalogoService::class);
}

/** Una asignatura sin secciones, planes ni prerrequisitos. */
function asignaturaSuelta(int $departamento, string $codigo = 'ELE101', int $creditos = 3): int
{
    return DbContext::system(fn () => DB::table('subjects')->insertGetId([
        'code' => $codigo, 'name' => 'Electiva '.$codigo, 'credits' => $creditos, 'department_id' => $departamento, 'created_at' => now(), 'updated_at' => now(),
    ]));
}

it('CP-RF07-02 impide eliminar la asignatura que tiene secciones y sugiere desactivarla', function () {
    $e = escenario();
    actingAs($e['administrador']);

    expect(fn () => catalogo()->eliminarAsignatura($e['administrador'], $e['prn']))
        ->toThrow(ValidationException::class, 'Desactívela');

    expect(DB::table('subjects')->where('id', $e['prn'])->exists())->toBeTrue();
});

it('CP-RF07-02 impide eliminar la asignatura sin secciones que otra cosa usa', function (string $uso) {
    $e = escenario();
    $asignatura = asignaturaSuelta($e['dia']);
    DbContext::system(fn () => match ($uso) {
        'plan de estudios' => DB::table('career_subjects')->insert(['study_plan_id' => $e['plan'], 'subject_id' => $asignatura, 'cycle' => 3]),
        'prerrequisito' => DB::table('subject_prerequisites')->insert(['subject_id' => $e['prn'], 'prerequisite_id' => $asignatura]),
    });
    actingAs($e['administrador']);

    expect(fn () => catalogo()->eliminarAsignatura($e['administrador'], $asignatura))->toThrow(ValidationException::class);

    expect(DB::table('subjects')->where('id', $asignatura)->exists())->toBeTrue();
})->with(['plan de estudios', 'prerrequisito']);

it('CP-RF07-02 elimina la asignatura que nada usa junto con sus propios prerrequisitos', function () {
    $e = escenario();
    $asignatura = asignaturaSuelta($e['dia']);
    DbContext::system(fn () => DB::table('subject_prerequisites')->insert(['subject_id' => $asignatura, 'prerequisite_id' => $e['prn']]));
    actingAs($e['administrador']);

    catalogo()->eliminarAsignatura($e['administrador'], $asignatura);

    expect(DB::table('subjects')->where('id', $asignatura)->exists())->toBeFalse()
        ->and(DB::table('subject_prerequisites')->where('subject_id', $asignatura)->exists())->toBeFalse()
        ->and(DB::table('subjects')->where('id', $e['prn'])->exists())->toBeTrue();
});

it('CP-RF07-02 la asignatura desactivada deja de ofrecerse al crear secciones y conserva las que tiene', function () {
    $e = escenario();
    actingAs($e['administrador']);
    $carga = app(CargaAcademicaService::class);

    catalogo()->cambiarEstadoAsignatura($e['administrador'], $e['prn'], false);

    expect($carga->asignaturas($e['coordinador'])->pluck('code')->all())->not->toContain('PRN115')
        ->and(DB::table('sections')->where('id', $e['seccion'])->exists())->toBeTrue();

    catalogo()->cambiarEstadoAsignatura($e['administrador'], $e['prn'], true);

    expect($carga->asignaturas($e['coordinador'])->pluck('code')->all())->toContain('PRN115');
});

it('CP-RF07-02 avisa en pantalla por qué no se puede eliminar y desactiva desde el catálogo', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(Catalogo::class)
        ->call('eliminar', $e['prn'])
        ->assertDispatched('aviso', tipo: 'error', mensaje: __('No se puede eliminar: tiene :usos. Desactívela para que no se programe en nuevos períodos.', ['usos' => 'secciones, planes de estudio']))
        ->call('alternarEstado', $e['prn'])
        ->assertSee('Inactiva')->assertSee('Reactivar');

    expect((bool) DB::table('subjects')->where('id', $e['prn'])->value('is_active'))->toBeFalse();
});

it('CP-RF07-02 impide eliminar y desactivar asignaturas a quien no administra el catálogo', function (string $rol) {
    $e = escenario();
    $asignatura = asignaturaSuelta($e['dia']);

    expect(fn () => catalogo()->eliminarAsignatura($e[$rol], $asignatura))->toThrow(AuthorizationException::class)
        ->and(fn () => catalogo()->cambiarEstadoAsignatura($e[$rol], $asignatura, false))->toThrow(AuthorizationException::class);

    expect((bool) DB::table('subjects')->where('id', $asignatura)->value('is_active'))->toBeTrue();
})->with(['coordinador', 'jefe']);

it('CP-RF07-01 solo quien administra el catálogo crea asignaturas', function (string $rol) {
    $e = escenario();

    expect(fn () => catalogo()->guardarAsignatura($e[$rol], null, ['codigo' => 'ELE200', 'nombre' => 'Electiva', 'creditos' => 3, 'departamento' => $e['dia']]))
        ->toThrow(AuthorizationException::class);

    expect(DB::table('subjects')->where('code', 'ELE200')->exists())->toBeFalse();
})->with(['coordinador', 'jefe']);

it('CP-RF07-04 filtra el catálogo por créditos, combinado con el departamento y el nombre', function () {
    $e = escenario();
    asignaturaSuelta($e['dia'], 'ELE101', 3);
    asignaturaSuelta($e['dcnm'], 'ELE102', 3);

    $codigos = fn (array $filtros) => catalogo()->asignaturas($e['administrador'], $filtros)->pluck('code')->all();

    expect($codigos(['creditos' => 3]))->toEqualCanonicalizing(['ELE101', 'ELE102'])
        ->and($codigos(['creditos' => 4]))->toEqualCanonicalizing(['PRN115', 'MAT115'])
        ->and($codigos(['creditos' => 3, 'departamento' => $e['dia']]))->toBe(['ELE101'])
        ->and($codigos(['creditos' => 4, 'buscar' => 'progra']))->toBe(['PRN115']);
});

it('CP-RF07-04 filtra por créditos desde la pantalla y lo guarda en la URL', function () {
    $e = escenario();
    asignaturaSuelta($e['dia'], 'ELE101', 3);
    actingAs($e['administrador']);

    Livewire::withQueryParams(['creditos' => '3'])->test(Catalogo::class)
        ->assertSet('filtroCreditos', '3')
        ->assertViewHas('asignaturas', fn ($pagina) => collect($pagina->items())->pluck('code')->all() === ['ELE101']);
});

/* ------------------------------------------------------------------ CP-RF07-06 (auditoría sin duplicados) */

it('CP-RF07-06 al editar solo agrega y quita los prerrequisitos que cambian', function () {
    $e = escenario();
    $otra = asignaturaSuelta($e['dia'], 'ELE102');
    actingAs($e['administrador']);
    $datos = ['codigo' => 'ELE200', 'nombre' => 'Electiva', 'creditos' => 3, 'departamento' => $e['dia']];

    $id = catalogo()->guardarAsignatura($e['administrador'], null, $datos + ['prerrequisitos' => [$e['prn'], $e['mat']]]);
    catalogo()->guardarAsignatura($e['administrador'], $id, $datos + ['prerrequisitos' => [$e['prn'], $otra]]);

    expect(DB::table('subject_prerequisites')->where('subject_id', $id)->orderBy('prerequisite_id')->pluck('prerequisite_id')->map(fn ($p) => (int) $p)->all())
        ->toBe(collect([$e['prn'], $otra])->sort()->values()->all())
        ->and(DB::table('subjects')->where('id', $id)->value('name'))->toBe('Electiva');
});

it('CP-RF07-06 audita el alta, la edición y los prerrequisitos de la asignatura una sola vez', function () {
    $e = escenario();
    actingAs($e['administrador']);
    $datos = ['codigo' => 'ELE200', 'nombre' => 'Electiva', 'creditos' => 3, 'departamento' => $e['dia'], 'prerrequisitos' => [$e['prn'], $e['mat']]];

    $id = catalogo()->guardarAsignatura($e['administrador'], null, $datos);
    catalogo()->guardarAsignatura($e['administrador'], $id, $datos);
    catalogo()->guardarAsignatura($e['administrador'], $id, ['nombre' => 'Electiva técnica', 'prerrequisitos' => [$e['prn']]] + $datos);

    DbContext::auditScope(all: true);
    $materia = DB::table('audit_logs')->where('entity_type', 'subjects')->where('entity_id', $id)->where('actor_user_id', $e['administrador']->id)->orderBy('id')->get(['action', 'old_values', 'new_values']);
    $prerrequisitos = DB::table('audit_logs')->where('entity_type', 'subject_prerequisites')->where('actor_user_id', $e['administrador']->id)->pluck('action');

    // Alta: un insert. Guardar sin cambios: nada. Renombrar y quitar MAT115: un update y un delete.
    expect($materia->pluck('action')->all())->toBe(['insert', 'update'])
        ->and(json_decode($materia[1]->old_values, true)['name'])->toBe('Electiva')
        ->and(json_decode($materia[1]->new_values, true)['name'])->toBe('Electiva técnica')
        ->and($prerrequisitos->sort()->values()->all())->toBe(['delete', 'insert', 'insert']);
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'Requiere PostgreSQL');

/* ------------------------------------------------------------- CP-RF07-05 y CP-RF07-07 (búsqueda y casillas) */

/** La asignatura con tildes de CP-RF07-05. */
function asignaturaConTildes(int $departamento): int
{
    return DbContext::system(fn () => DB::table('subjects')->insertGetId([
        'code' => 'ACC115', 'name' => 'Administración de Centros de Cómputo', 'credits' => 4, 'department_id' => $departamento,
        'created_at' => now(), 'updated_at' => now(),
    ]));
}

it('CP-RF07-05 encuentra «Administración de Centros de Cómputo» buscando sin tildes', function (string $texto, bool $encuentra) {
    $e = escenario();
    asignaturaConTildes($e['dia']);
    actingAs($e['administrador']);

    $codigos = catalogo()->asignaturas($e['administrador'], ['buscar' => $texto])->pluck('code')->all();

    $encuentra
        ? expect($codigos)->toContain('ACC115')
        : expect($codigos)->not->toContain('ACC115');
})->with([
    ['administracion', true],
    ['Administración', true],
    ['computo', true],
    ['ACC', true],
    ['biologia', false],
]);

it('CP-RF07-07 elige varios prerrequisitos con casillas, sin la tecla Ctrl', function () {
    $e = escenario();
    actingAs($e['administrador']);

    $pantalla = Livewire::test(Catalogo::class)
        ->call('nueva')
        ->set('codigo', 'ACC115')
        ->set('nombre', 'Administración de Centros de Cómputo')
        ->set('creditos', 4)
        ->set('departamentoId', $e['dia'])
        ->set('prerrequisitos', [$e['prn'], $e['mat']])
        ->call('guardar')
        ->assertHasNoErrors();

    $id = (int) DB::table('subjects')->where('code', 'ACC115')->value('id');
    expect(DB::table('subject_prerequisites')->where('subject_id', $id)->pluck('prerequisite_id')->map(fn ($p) => (int) $p)->sort()->values()->all())
        ->toBe(collect([$e['prn'], $e['mat']])->sort()->values()->all());

    // La pantalla ofrece casillas, no un desplegable múltiple que exigiría la tecla Ctrl.
    $pantalla->assertSeeHtml('type="checkbox"')->assertSeeHtml('wire:model="prerrequisitos"')->assertDontSeeHtml('<select multiple');
});
