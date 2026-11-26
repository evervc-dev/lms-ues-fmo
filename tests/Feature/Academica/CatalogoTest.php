<?php

use App\Modules\Academica\Livewire\AsignaturaFormulario;
use App\Modules\Academica\Livewire\Catalogo;
use App\Modules\Academica\Services\CargaAcademicaService;
use App\Modules\Academica\Services\CatalogoService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/*
| Catálogo de asignaturas (RF07): eliminar solo lo que nada usa, desactivar lo demás (CP-RF07-02) y
| filtrar por departamento, créditos o nombre (CP-RF07-04). Lo mantiene el Administrador (asignaturas.gestionar G).
*/

function catalogo(): CatalogoService
{
    return app(CatalogoService::class);
}

/** Pone esas asignaturas en el plan del escenario: un prerrequisito debe ser del mismo pensum. */
function enElPlanDelEscenario(array $e, int ...$asignaturas): void
{
    DbContext::system(fn () => DB::table('career_subjects')->insertOrIgnore(array_map(fn (int $id) => ['study_plan_id' => $e['plan'], 'subject_id' => $id, 'cycle' => 2], $asignaturas)));
}

/** Una asignatura sin secciones, planes ni prerrequisitos. */
function asignaturaSuelta(int $departamento, string $codigo = 'ELE101', int $creditos = 3): int
{
    return DbContext::system(fn () => DB::table('subjects')->insertGetId([
        'code' => $codigo, 'name' => 'Electiva '.$codigo, 'credits' => $creditos, 'department_id' => $departamento, 'created_at' => now(), 'updated_at' => now(),
    ]));
}

it('CP-RF07-02 impide eliminar la asignatura que tiene secciones y sugiere archivarla', function () {
    $e = escenario();
    actingAs($e['administrador']);

    expect(fn () => catalogo()->eliminarAsignatura($e['administrador'], $e['prn']))
        ->toThrow(ValidationException::class, 'Archívela');

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

it('CP-RF07-02 no archiva una asignatura con grupos en un período que no está cerrado (ADR-092)', function () {
    $e = escenario();
    actingAs($e['administrador']);

    expect(fn () => catalogo()->cambiarEstadoAsignatura($e['administrador'], $e['prn'], false))->toThrow(ValidationException::class)
        ->and((bool) DB::table('subjects')->where('id', $e['prn'])->value('is_active'))->toBeTrue();
});

it('CP-RF07-02 la asignatura archivada deja de ofrecerse al crear secciones y conserva las que tiene', function () {
    $e = escenario();
    actingAs($e['administrador']);
    $carga = app(CargaAcademicaService::class);

    DbContext::system(fn () => DB::table('subjects')->where('id', $e['prn'])->update(['is_active' => false]));

    expect($carga->asignaturas($e['coordinador'])->pluck('code')->all())->not->toContain('PRN115')
        ->and(DB::table('sections')->where('id', $e['seccion'])->exists())->toBeTrue();

    catalogo()->cambiarEstadoAsignatura($e['administrador'], $e['prn'], true);

    expect($carga->asignaturas($e['coordinador'])->pluck('code')->all())->toContain('PRN115');
});

it('CP-RF07-02 avisa en pantalla por qué no se puede eliminar ni archivar desde el catálogo', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(Catalogo::class)
        ->call('eliminar', $e['prn'])
        ->assertDispatched('aviso', tipo: 'error', mensaje: __('No se puede eliminar: tiene :usos. Archívela para que no se programe en nuevos períodos.', ['usos' => 'secciones, planes de estudio']))
        ->call('alternarEstado', $e['prn'])
        ->assertDispatched('aviso', tipo: 'error', mensaje: __('No se puede archivar: tiene grupos teóricos en un período que no está cerrado.'));

    expect((bool) DB::table('subjects')->where('id', $e['prn'])->value('is_active'))->toBeTrue();
});

it('CP-RF07-02 impide eliminar y desactivar asignaturas a quien no administra el catálogo', function (string $rol, string $departamento) {
    $e = escenario();
    $asignatura = asignaturaSuelta($e[$departamento]);

    expect(fn () => catalogo()->eliminarAsignatura($e[$rol], $asignatura))->toThrow(AuthorizationException::class)
        ->and(fn () => catalogo()->cambiarEstadoAsignatura($e[$rol], $asignatura, false))->toThrow(AuthorizationException::class);

    expect((bool) DB::table('subjects')->where('id', $asignatura)->value('is_active'))->toBeTrue();
})->with(['coordinador' => ['coordinador', 'dia'], 'jefe de otro departamento' => ['jefe', 'dcnm']]);

it('CP-RF07-01 solo quien administra el catálogo crea asignaturas', function (string $rol, string $departamento) {
    $e = escenario();

    expect(fn () => catalogo()->guardarAsignatura($e[$rol], null, ['codigo' => 'ELE200', 'nombre' => 'Electiva', 'creditos' => 3, 'departamento' => $e[$departamento]]))
        ->toThrow(AuthorizationException::class);

    expect(DB::table('subjects')->where('code', 'ELE200')->exists())->toBeFalse();
})->with(['coordinador' => ['coordinador', 'dia'], 'jefe de otro departamento' => ['jefe', 'dcnm']]);

it('CP-RF07-04 filtra el catálogo por créditos, combinado con la carrera y el nombre', function () {
    $e = escenario();
    $ele101 = asignaturaSuelta($e['dia'], 'ELE101', 3);
    asignaturaSuelta($e['dcnm'], 'ELE102', 3);
    DbContext::system(fn () => DB::table('career_subjects')->insert(['study_plan_id' => $e['plan'], 'subject_id' => $ele101, 'cycle' => 5]));

    $codigos = fn (array $filtros) => catalogo()->asignaturas($e['administrador'], $filtros)->pluck('code')->all();

    expect($codigos(['creditos' => 3]))->toEqualCanonicalizing(['ELE101', 'ELE102'])
        ->and($codigos(['creditos' => 4]))->toEqualCanonicalizing(['PRN115', 'MAT115'])
        ->and($codigos(['creditos' => 3, 'carrera' => $e['isi']]))->toBe(['ELE101'])
        ->and($codigos(['carrera' => $e['isi']]))->toEqualCanonicalizing(['PRN115', 'ELE101'])
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
    enElPlanDelEscenario($e, $e['mat'], $otra);
    actingAs($e['administrador']);
    $datos = ['codigo' => 'ELE200', 'nombre' => 'Electiva', 'creditos' => 3, 'departamento' => $e['dia'], 'planes' => [['plan' => $e['plan'], 'ciclo' => 3]]];

    $id = catalogo()->guardarAsignatura($e['administrador'], null, $datos + ['prerrequisitos' => [$e['prn'], $e['mat']]]);
    catalogo()->guardarAsignatura($e['administrador'], $id, $datos + ['prerrequisitos' => [$e['prn'], $otra]]);

    expect(DB::table('subject_prerequisites')->where('subject_id', $id)->orderBy('prerequisite_id')->pluck('prerequisite_id')->map(fn ($p) => (int) $p)->all())
        ->toBe(collect([$e['prn'], $otra])->sort()->values()->all())
        ->and(DB::table('subjects')->where('id', $id)->value('name'))->toBe('Electiva');
});

it('CP-RF07-06 audita el alta, la edición y los prerrequisitos de la asignatura una sola vez', function () {
    $e = escenario();
    enElPlanDelEscenario($e, $e['mat']);
    actingAs($e['administrador']);
    $datos = ['codigo' => 'ELE200', 'nombre' => 'Electiva', 'creditos' => 3, 'departamento' => $e['dia'], 'planes' => [['plan' => $e['plan'], 'ciclo' => 3]], 'prerrequisitos' => [$e['prn'], $e['mat']]];

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
    enElPlanDelEscenario($e, $e['mat']);
    actingAs($e['administrador']);

    $pantalla = Livewire::test(AsignaturaFormulario::class)
        ->set('codigo', 'ACC115')
        ->set('nombre', 'Administración de Centros de Cómputo')
        ->set('creditos', 4)
        ->set('departamentoId', $e['dia'])
        ->set('prerrequisitos', [$e['prn'], $e['mat']])
        ->set('planes', [['carrera' => $e['isi'], 'plan' => $e['plan'], 'ciclo' => 4]])
        ->call('guardar')
        ->assertHasNoErrors();

    $id = (int) DB::table('subjects')->where('code', 'ACC115')->value('id');
    expect(DB::table('subject_prerequisites')->where('subject_id', $id)->pluck('prerequisite_id')->map(fn ($p) => (int) $p)->sort()->values()->all())
        ->toBe(collect([$e['prn'], $e['mat']])->sort()->values()->all());

    // La pantalla ofrece casillas, no un desplegable múltiple que exigiría la tecla Ctrl.
    $pantalla->assertSeeHtml('type="checkbox"')->assertSeeHtml('wire:model.live="prerrequisitos"')->assertDontSeeHtml('<select multiple');
});

/* ------------------------------------------------- CP-RF07-13 a CP-RF07-16 (el Jefe en su departamento, ADR-072) */

it('CP-RF07-13 muestra el Catálogo al Jefe con solo las asignaturas de su departamento', function () {
    $e = escenario();
    actingAs($e['jefe']);

    get(route('inicio'))->assertOk()->assertSee(route('catalogo'), false);

    // MAT115 (DCNM) no está en el listado, aunque sí entre los prerrequisitos elegibles, que son de cualquier departamento.
    Livewire::test(Catalogo::class)
        ->assertViewHas('asignaturas', fn ($asignaturas) => collect($asignaturas->items())->pluck('code')->all() === ['PRN115']);
    Livewire::test(AsignaturaFormulario::class)
        ->assertViewHas('departamentos', fn ($departamentos) => $departamentos->pluck('id')->all() === [$e['dia']]);
});

it('CP-RF07-14 deja al Jefe crear, editar, desactivar y eliminar asignaturas de su departamento', function () {
    $e = escenario();
    enElPlanDelEscenario($e, $e['mat']);
    actingAs($e['jefe']);
    $datos = ['codigo' => 'ELE200', 'nombre' => 'Electiva', 'creditos' => 3, 'departamento' => $e['dia'], 'planes' => [['plan' => $e['plan'], 'ciclo' => 3]]];

    $id = catalogo()->guardarAsignatura($e['jefe'], null, $datos);
    catalogo()->guardarAsignatura($e['jefe'], $id, ['nombre' => 'Electiva técnica', 'prerrequisitos' => [$e['mat']]] + $datos);
    catalogo()->cambiarEstadoAsignatura($e['jefe'], $id, false);

    $fila = DB::table('subjects')->where('id', $id)->first();
    expect($fila->name)->toBe('Electiva técnica')->and((bool) $fila->is_active)->toBeFalse()
        ->and(DB::table('subject_prerequisites')->where('subject_id', $id)->pluck('prerequisite_id')->map(fn ($p) => (int) $p)->all())->toBe([$e['mat']]);

    // Mientras esté en un plan de estudios no se elimina (CP-RF07-02): primero se le quitan los planes.
    catalogo()->guardarAsignatura($e['jefe'], $id, ['planes' => []] + $datos);
    catalogo()->eliminarAsignatura($e['jefe'], $id);
    expect(DB::table('subjects')->where('id', $id)->exists())->toBeFalse();
});

it('CP-RF07-14 impide al Jefe tocar asignaturas de otro departamento o mover la suya a otro', function () {
    $e = escenario();
    $ajena = asignaturaSuelta($e['dcnm']);
    $propia = asignaturaSuelta($e['dia'], 'ELE102');

    expect(fn () => catalogo()->guardarAsignatura($e['jefe'], $ajena, ['codigo' => 'ELE101', 'nombre' => 'Otra', 'creditos' => 3, 'departamento' => $e['dia']]))->toThrow(AuthorizationException::class)
        ->and(fn () => catalogo()->guardarAsignatura($e['jefe'], $propia, ['codigo' => 'ELE102', 'nombre' => 'Electiva', 'creditos' => 3, 'departamento' => $e['dcnm']]))->toThrow(AuthorizationException::class)
        ->and(fn () => catalogo()->cambiarEstadoAsignatura($e['jefe'], $ajena, false))->toThrow(AuthorizationException::class)
        ->and(fn () => catalogo()->eliminarAsignatura($e['jefe'], $ajena))->toThrow(AuthorizationException::class);

    expect(DB::table('subjects')->where('id', $ajena)->value('name'))->toBe('Electiva ELE101')
        ->and((int) DB::table('subjects')->where('id', $propia)->value('department_id'))->toBe($e['dia']);
});

it('CP-RF07-15 deja la estructura institucional solo al Administrador', function () {
    $e = escenario();

    actingAs($e['jefe']);
    get(route('catalogo.estructura'))->assertForbidden();
    Livewire::test(Catalogo::class)->assertDontSee(route('catalogo.estructura'), false);
    expect(fn () => catalogo()->guardarAula($e['jefe'], 'B-206', 40))->toThrow(AuthorizationException::class)
        ->and(fn () => catalogo()->guardarDepartamento($e['jefe'], null, 'DX', 'Nuevo'))->toThrow(AuthorizationException::class)
        ->and(fn () => catalogo()->eliminarDepartamento($e['jefe'], $e['dcnm']))->toThrow(AuthorizationException::class);

    actingAs($e['administrador']);
    Livewire::test(Catalogo::class)->assertSee(route('catalogo.estructura'), false);
});

it('CP-RF07-16 deja al Jefe editar la descripción de las asignaturas de su departamento, no de otro', function () {
    $e = escenario();
    // En PostgreSQL, DbContext::run toma el actor de la sesión: sin actingAs la auditoría rechaza la escritura (AC900).
    actingAs($e['jefe']);

    catalogo()->editarDescripcion($e['jefe'], $e['prn'], 'Fundamentos de programación.');

    expect(DB::table('subjects')->where('id', $e['prn'])->value('description'))->toBe('Fundamentos de programación.')
        ->and(fn () => catalogo()->editarDescripcion($e['jefe'], $e['mat'], 'Ajena.'))->toThrow(AuthorizationException::class);
});

/* ------------------------------------------------- CP-RF07-17 a CP-RF07-20 (carrera, plan y ciclo de la asignatura) */

/** Una segunda carrera con su plan: en el departamento indicado. @return array{carrera: int, plan: int} */
function carreraConPlan(int $departamento, string $codigo, string $plan = 'Plan 26'): array
{
    return DbContext::system(function () use ($departamento, $codigo, $plan) {
        $carrera = DB::table('careers')->insertGetId(['department_id' => $departamento, 'code' => $codigo, 'name' => 'Carrera '.$codigo, 'created_at' => now(), 'updated_at' => now()]);

        return ['carrera' => $carrera, 'plan' => DB::table('study_plans')->insertGetId(['career_id' => $carrera, 'name' => $plan, 'created_at' => now(), 'updated_at' => now()])];
    });
}

it('CP-RF07-17 rechaza crear una asignatura sin carrera ni plan de estudios', function () {
    $e = escenario();
    actingAs($e['jefe']);

    Livewire::test(AsignaturaFormulario::class)->set('planes', [])
        ->set('codigo', 'IND101')->set('nombre', 'Introducción a la Industrial')->set('creditos', 4)
        ->call('guardar')->assertHasErrors(['planes']);

    expect(DB::table('subjects')->where('code', 'IND101')->exists())->toBeFalse();
});

it('CP-RF07-18 el Jefe crea la asignatura sin elegir departamento, en la carrera y el plan de su departamento', function () {
    $e = escenario();
    $industrial = carreraConPlan($e['dia'], 'IIN');
    actingAs($e['jefe']);

    Livewire::test(AsignaturaFormulario::class)
        ->assertSet('departamentoId', $e['dia'])->assertDontSee('Departamento que la imparte')
        ->assertViewHas('carreras', fn ($carreras) => $carreras->pluck('id')->sort()->values()->all() === collect([$e['isi'], $industrial['carrera']])->sort()->values()->all())
        ->set('codigo', 'IND101')->set('nombre', 'Introducción a la Industrial')->set('creditos', 4)
        ->set('planes.0.carrera', $industrial['carrera'])->assertSet('planes.0.plan', $industrial['plan'])
        ->set('planes.0.ciclo', 1)
        ->call('agregarPlan')->set('planes.1.carrera', $e['isi'])->set('planes.1.plan', $e['plan'])->set('planes.1.ciclo', 9)
        ->call('guardar')->assertHasNoErrors()->assertRedirect(route('catalogo'));

    $id = (int) DB::table('subjects')->where('code', 'IND101')->value('id');
    expect((int) DB::table('subjects')->where('id', $id)->value('department_id'))->toBe($e['dia'])
        ->and(DB::table('career_subjects')->where('subject_id', $id)->orderBy('cycle')->get(['study_plan_id', 'cycle'])->map(fn ($f) => [(int) $f->study_plan_id, (int) $f->cycle])->all())
        ->toBe([[$industrial['plan'], 1], [$e['plan'], 9]]);
});

it('CP-RF07-19 impide al Jefe usar el plan de una carrera de otro departamento; el Administrador sí puede', function () {
    $e = escenario();
    $matematica = carreraConPlan($e['dcnm'], 'LMA', 'Plan 07');
    $datos = ['codigo' => 'IND102', 'nombre' => 'Estadística industrial', 'creditos' => 4, 'departamento' => $e['dia'], 'planes' => [['plan' => $matematica['plan'], 'ciclo' => 3]]];

    actingAs($e['jefe']);
    expect(fn () => catalogo()->guardarAsignatura($e['jefe'], null, $datos))->toThrow(AuthorizationException::class)
        ->and(DB::table('subjects')->where('code', 'IND102')->exists())->toBeFalse();

    actingAs($e['administrador']);
    $id = catalogo()->guardarAsignatura($e['administrador'], null, $datos);
    expect(DB::table('career_subjects')->where('subject_id', $id)->value('study_plan_id'))->toBe($matematica['plan']);
});

it('CP-RF07-20 al editar agrega, cambia y quita planes sin tocar los de otro departamento', function () {
    $e = escenario();
    $industrial = carreraConPlan($e['dia'], 'IIN');
    $matematica = carreraConPlan($e['dcnm'], 'LMA', 'Plan 07');
    $datos = ['codigo' => 'IND103', 'nombre' => 'Investigación de operaciones', 'creditos' => 4, 'departamento' => $e['dia']];
    actingAs($e['administrador']);
    $id = catalogo()->guardarAsignatura($e['administrador'], null, $datos + ['planes' => [['plan' => $e['plan'], 'ciclo' => 5], ['plan' => $matematica['plan'], 'ciclo' => 6]]]);
    actingAs($e['jefe']);

    // El Jefe solo ve en el formulario los planes de su departamento.
    Livewire::test(AsignaturaFormulario::class, ['asignatura' => 'IND103'])->assertSet('planes', [['carrera' => $e['isi'], 'plan' => $e['plan'], 'ciclo' => 5]]);

    catalogo()->guardarAsignatura($e['jefe'], $id, $datos + ['planes' => [['plan' => $industrial['plan'], 'ciclo' => 7]]]);

    expect(DB::table('career_subjects')->where('subject_id', $id)->orderBy('cycle')->get(['study_plan_id', 'cycle'])->map(fn ($f) => [(int) $f->study_plan_id, (int) $f->cycle])->all())
        ->toBe([[$matematica['plan'], 6], [$industrial['plan'], 7]]);
});

it('CP-RF07-04 el filtro de carrera solo ofrece al Jefe las carreras de su departamento y filtra desde la URL', function () {
    $e = escenario();
    $matematica = carreraConPlan($e['dcnm'], 'LMA', 'Plan 07');
    actingAs($e['jefe']);

    Livewire::withQueryParams(['carrera' => (string) $e['isi']])->test(Catalogo::class)
        ->assertSet('carrera', (string) $e['isi'])
        ->assertViewHas('carreras', fn ($carreras) => $carreras->pluck('id')->all() === [$e['isi']])
        ->assertViewHas('asignaturas', fn ($pagina) => collect($pagina->items())->pluck('code')->all() === ['PRN115'])
        ->assertDontSee('Carrera LMA');

    // Pedir por la URL una carrera de otro departamento no le muestra sus materias.
    Livewire::withQueryParams(['carrera' => (string) $matematica['carrera']])->test(Catalogo::class)
        ->assertViewHas('asignaturas', fn ($pagina) => $pagina->total() === 0);
});

it('aplica a una base con datos los permisos nuevos del Jefe (asignaturas.gestionar y secciones.gestionar)', function () {
    $e = escenario();
    $rol = Role::findByName('jefe_departamento');
    DbContext::system(fn () => $rol->revokePermissionTo(['asignaturas.gestionar', 'secciones.gestionar']));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    (require database_path('migrations/2026_11_18_000200_grant_head_catalog_and_sections_permissions.php'))->up();

    expect($e['jefe']->fresh()->hasPermissionTo('asignaturas.gestionar'))->toBeTrue()
        ->and($e['jefe']->fresh()->hasPermissionTo('secciones.gestionar'))->toBeTrue()
        ->and(Role::findByName('coordinador')->hasPermissionTo('asignaturas.gestionar'))->toBeFalse();
});

it('CP-RF07-21 crear y editar una asignatura van en su propia página, y el Jefe no edita una de otro departamento', function () {
    $e = escenario();
    actingAs($e['administrador']);
    $this->get(route('catalogo.asignaturas.crear'))->assertOk()->assertSee('Nueva asignatura');
    $this->get(route('catalogo.asignaturas.editar', ['asignatura' => 'PRN115']))->assertOk()->assertSee('Editar PRN115');
    Livewire::test(Catalogo::class)->assertSee(route('catalogo.asignaturas.editar', ['asignatura' => 'PRN115']), false);

    // MAT115 es del departamento de Ciencias Naturales: el Jefe de Ingeniería no la abre.
    actingAs($e['jefe']);
    $this->get(route('catalogo.asignaturas.editar', ['asignatura' => 'MAT115']))->assertForbidden();
    $this->get(route('catalogo.asignaturas.editar', ['asignatura' => 'NOEXISTE']))->assertNotFound();
    actingAs($e['docente']);
    $this->get(route('catalogo.asignaturas.crear'))->assertForbidden();
});

it('CP-RF07-04 categoriza y filtra el catálogo por departamento con pestañas y selector', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(Catalogo::class)
        ->assertSee('Todos los departamentos')
        ->assertSee('DIA')
        ->assertSee('DCNM')
        ->call('seleccionarDepartamento', 'DIA')
        ->assertSet('departamento', 'DIA')
        ->assertViewHas('asignaturas', fn ($pagina) => collect($pagina->items())->pluck('code')->all() === ['PRN115'])
        ->call('seleccionarDepartamento', 'DCNM')
        ->assertSet('departamento', 'DCNM')
        ->assertViewHas('asignaturas', fn ($pagina) => collect($pagina->items())->pluck('code')->all() === ['MAT115'])
        ->call('seleccionarDepartamento', '')
        ->assertSet('departamento', '')
        ->assertViewHas('asignaturas', fn ($pagina) => $pagina->total() === 2);
});

it('CP-RF07-27 rechaza poner una asignatura en dos planes de la misma carrera, pero la deja en planes de carreras distintas (ADR-089)', function () {
    $e = escenario();
    [$plan98, $lma] = DbContext::system(function () use ($e) {
        $plan98 = DB::table('study_plans')->insertGetId(['career_id' => $e['isi'], 'name' => 'Plan 98', 'created_at' => now(), 'updated_at' => now()]);
        $carrera = DB::table('careers')->insertGetId(['department_id' => $e['dcnm'], 'code' => 'LMA', 'name' => 'Licenciatura en Matemática', 'created_at' => now(), 'updated_at' => now()]);
        $lma = DB::table('study_plans')->insertGetId(['career_id' => $carrera, 'name' => 'Plan 07', 'created_at' => now(), 'updated_at' => now()]);

        return [$plan98, $lma];
    });
    actingAs($e['administrador']);
    $datos = ['codigo' => 'ALG115', 'nombre' => 'Álgebra', 'creditos' => 4, 'departamento' => $e['dcnm']];

    expect(fn () => app(CatalogoService::class)->guardarAsignatura($e['administrador'], null, $datos + ['planes' => [['plan' => $e['plan'], 'ciclo' => 1], ['plan' => $plan98, 'ciclo' => 1]]]))
        ->toThrow(ValidationException::class, 'Una asignatura solo puede estar en un plan de cada carrera');

    $id = app(CatalogoService::class)->guardarAsignatura($e['administrador'], null, $datos + ['planes' => [['plan' => $e['plan'], 'ciclo' => 1], ['plan' => $lma, 'ciclo' => 2]]]);
    expect(DB::table('career_subjects')->where('subject_id', $id)->count())->toBe(2);
});
