<?php

use App\Modules\Academica\Livewire\Estructura;
use App\Modules\Academica\Livewire\PanelAcademico;
use App\Modules\Academica\Services\CalendarioPeriodoService;
use App\Modules\Academica\Services\CargaAcademicaService;
use App\Modules\Academica\Services\CatalogoService;
use App\Modules\Academica\Services\MatriculaService;
use App\Modules\Academica\Services\OfertaService;
use App\Modules\Academica\Services\PeriodoService;
use App\Modules\Asistencia\Services\AsistenciaService;
use App\Modules\Calificaciones\Services\ComputoService;
use App\Modules\Identidad\Services\AsignacionService;
use App\Modules\Reportes\Services\PanelService;
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
| Departamentos del catálogo (RF07): el Administrador (asignaturas.gestionar G) los crea, edita su código y nombre,
| los archiva y reactiva (ADR-076) y los elimina solo si nada los usa. Panel académico del Administrador con la Vista
| de sección en solo lectura (ADR-075): CP-RF07-25 y CP-RF07-26.
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
        ->assertDispatched('aviso', tipo: 'error', mensaje: 'No se puede eliminar el departamento: tiene carreras, asignaturas, docentes, jefaturas, plazos de matrícula. Archívelo para que deje de ofrecerse.');

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
        ->toThrow(ValidationException::class, 'No se puede eliminar el departamento: tiene jefaturas. Archívelo para que deje de ofrecerse.');
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

/** Un departamento activo que nada usa todavía. */
function departamentoLibre(string $codigo = 'DQA', string $nombre = 'Química y Farmacia'): int
{
    return DbContext::system(fn () => DB::table('departments')->insertGetId([
        'code' => $codigo, 'name' => $nombre, 'created_at' => now(), 'updated_at' => now(),
    ]));
}

/** Archiva el departamento sin pasar por el Service (para preparar las pruebas de los filtros). */
function departamentoArchivado(int $departamento): void
{
    DbContext::system(fn () => DB::table('departments')->where('id', $departamento)->update(['is_active' => false]));
}

/** Una asignatura del departamento con una sección en un período nuevo en el estado indicado. */
function departamentoConSeccionEn(int $departamento, string $estado, string $periodo, string $inicio, string $fin): void
{
    DbContext::system(function () use ($departamento, $estado, $periodo, $inicio, $fin): void {
        $periodoId = DB::table('academic_periods')->insertGetId(['name' => $periodo, 'start_date' => $inicio, 'end_date' => $fin, 'status' => $estado, 'created_at' => now(), 'updated_at' => now()]);
        $asignatura = DB::table('subjects')->insertGetId(['code' => 'QUI115', 'name' => 'Química General', 'credits' => 4, 'department_id' => $departamento, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('sections')->insert(['ulid' => (string) str()->ulid(), 'period_id' => $periodoId, 'subject_id' => $asignatura, 'code' => '01', 'max_capacity' => 40, 'created_at' => now(), 'updated_at' => now()]);
    });
}

function departamentoActivo(int $departamento): bool
{
    return (bool) DB::table('departments')->where('id', $departamento)->value('is_active');
}

/**
 * Códigos de las asignaturas que ofrece la lista indicada (CP-RF07-23).
 *
 * @param  array<string, mixed>  $e
 * @return array<int, string>
 */
function asignaturasEn(string $lista, array $e): array
{
    $filas = match ($lista) {
        'la inscripción del estudiante' => app(MatriculaService::class)->materiasInscribibles($e['estudiante']),
        'la oferta' => app(OfertaService::class)->estadisticas($e['administrador'])['filas'],
        'al crear secciones' => app(CargaAcademicaService::class)->asignaturas($e['administrador']),
    };

    return $filas->pluck('code')->all();
}

/**
 * Ids de los departamentos que ofrece la lista indicada (CP-RF07-24).
 *
 * @param  array<string, mixed>  $e
 * @return array<int, int>
 */
function departamentosEn(string $lista, array $e): array
{
    $filas = match ($lista) {
        'al asignar Jefe o Docente' => app(AsignacionService::class)->opciones($e['administrador'])['departamentos'],
        'los plazos de matrícula' => app(MatriculaService::class)->plazos($e['administrador']),
        'el cierre del período' => app(PeriodoService::class)->detalle($e['administrador'], $e['periodo'])['cierres'],
    };

    return $filas->pluck('id')->map(fn ($id) => (int) $id)->all();
}

/* ------------------------------------------------------------------ CP-RF07-21 y CP-RF07-22 (archivar) */

it('CP-RF07-21 archiva y reactiva el departamento sin secciones en períodos sin cerrar', function (bool $conSeccionCerrada) {
    $e = escenario();
    $id = departamentoLibre();
    if ($conSeccionCerrada) {
        departamentoConSeccionEn($id, 'closed', '2026-I', '2026-02-02', '2026-06-19');
    }
    actingAs($e['administrador']);

    $pantalla = Livewire::test(Estructura::class)
        ->call('archivarDepartamento', $id)
        ->assertDispatched('aviso', tipo: 'exito');
    $archivado = departamentoActivo($id);
    $pantalla->call('reactivarDepartamento', $id)->assertDispatched('aviso', tipo: 'exito', mensaje: __('Departamento reactivado.'));

    expect($archivado)->toBeFalse()
        ->and(departamentoActivo($id))->toBeTrue();
})->with([
    'sin secciones' => [false],
    'con secciones solo en un período cerrado' => [true],
]);

it('CP-RF07-21 deja el archivo y la reactivación en la auditoría con el actor', function () {
    $e = escenario();
    $id = departamentoLibre();

    departamentos()->archivarDepartamento($e['administrador'], $id);
    departamentos()->reactivarDepartamento($e['administrador'], $id);

    DbContext::auditScope(all: true);
    $filas = DB::table('audit_logs')->where('entity_type', 'departments')->where('entity_id', $id)->where('actor_user_id', $e['administrador']->id)
        ->orderBy('id')->get(['action', 'old_values', 'new_values']);
    expect($filas->pluck('action')->all())->toBe(['update', 'update'])
        ->and(json_decode($filas[0]->old_values, true)['is_active'])->toBeTrue()
        ->and(json_decode($filas[0]->new_values, true)['is_active'])->toBeFalse()
        ->and(json_decode($filas[1]->new_values, true)['is_active'])->toBeTrue();
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'Requiere PostgreSQL');

it('CP-RF07-21 el Jefe no archiva ni reactiva un departamento, aunque tenga asignaturas.gestionar con D', function () {
    $e = escenario();
    $id = departamentoLibre();

    expect(fn () => departamentos()->archivarDepartamento($e['jefe'], $e['dia']))->toThrow(AuthorizationException::class)
        ->and(fn () => departamentos()->reactivarDepartamento($e['jefe'], $id))->toThrow(AuthorizationException::class)
        ->and(departamentoActivo($e['dia']))->toBeTrue();
});

it('CP-RF07-22 impide archivar el departamento con una sección en un período en curso', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(Estructura::class)
        ->call('archivarDepartamento', $e['dia'])
        ->assertDispatched('aviso', tipo: 'error', mensaje: 'No se puede archivar: el departamento tiene 1 grupo teórico en un período en curso o planificado. Podrá archivarlo cuando ese período se cierre.');

    expect(departamentoActivo($e['dia']))->toBeTrue();
});

it('CP-RF07-22 impide archivar el departamento con una sección en un período planificado', function () {
    $e = escenario();
    $id = departamentoLibre();
    departamentoConSeccionEn($id, 'planned', '2027-I', '2027-02-01', '2027-06-18');

    expect(fn () => departamentos()->archivarDepartamento($e['administrador'], $id))
        ->toThrow(ValidationException::class, 'No se puede archivar: el departamento tiene 1 grupo teórico en un período en curso o planificado.');

    expect(departamentoActivo($id))->toBeTrue();
});

/* ------------------------------------------------------------------ CP-RF07-23 y CP-RF07-24 (filtros del archivado) */

it('CP-RF07-23 saca las asignaturas del departamento archivado de las listas', function (string $lista) {
    $e = escenario();
    // PRN115 en un ciclo par del plan, para que el estudiante la pueda inscribir en 2026-II (ADR-050).
    DbContext::system(fn () => DB::table('career_subjects')->where('study_plan_id', $e['plan'])->where('subject_id', $e['prn'])->update(['cycle' => 2]));
    $antes = asignaturasEn($lista, $e);

    departamentoArchivado($e['dia']);

    expect($antes)->toContain('PRN115')
        ->and(asignaturasEn($lista, $e))->not->toContain('PRN115');
})->with(['la inscripción del estudiante', 'la oferta', 'al crear secciones']);

it('CP-RF07-23 saca el departamento archivado y sus carreras de las listas para crear grupos teóricos y asignaturas', function () {
    $e = escenario();
    $carga = app(CargaAcademicaService::class);
    $antes = [
        'departamentos' => $carga->departamentosParaGrupos($e['administrador'])->pluck('id')->map(fn ($id) => (int) $id)->all(),
        'carreras' => $carga->carrerasParaGrupos($e['administrador'])->pluck('id')->map(fn ($id) => (int) $id)->all(),
        'catálogo' => departamentos()->carrerasConPlanes($e['administrador'])->pluck('id')->map(fn ($id) => (int) $id)->all(),
    ];

    departamentoArchivado($e['dia']);

    expect($antes['departamentos'])->toContain($e['dia'])
        ->and($antes['carreras'])->toContain($e['isi'])
        ->and($antes['catálogo'])->toContain($e['isi'])
        ->and($carga->departamentosParaGrupos($e['administrador'])->pluck('id')->map(fn ($id) => (int) $id)->all())->not->toContain($e['dia'])
        ->and($carga->carrerasParaGrupos($e['administrador'])->pluck('id')->map(fn ($id) => (int) $id)->all())->not->toContain($e['isi'])
        ->and(departamentos()->carrerasConPlanes($e['administrador'])->pluck('id')->map(fn ($id) => (int) $id)->all())->not->toContain($e['isi']);
});

it('CP-RF07-24 saca el departamento archivado de las listas de departamentos', function (string $lista) {
    $e = escenario();
    $id = departamentoLibre();
    $antes = departamentosEn($lista, $e);

    departamentoArchivado($id);

    expect($antes)->toContain($id)
        ->and(departamentosEn($lista, $e))->not->toContain($id)->toContain($e['dia']);
})->with(['al asignar Jefe o Docente', 'los plazos de matrícula', 'el cierre del período']);

it('CP-RF07-24 no cuenta el departamento archivado en el total del panel del Administrador', function () {
    $e = escenario();
    departamentoArchivado(departamentoLibre());

    $panel = app(PanelService::class)->administrador($e['administrador']);

    expect($panel['cierres']['departamentos'])->toBe(2);
});

it('CP-RF07-24 conserva en el cierre el departamento archivado que tiene secciones en ese período', function () {
    $e = escenario();
    departamentoArchivado($e['dia']);

    $cierres = app(PeriodoService::class)->detalle($e['administrador'], $e['periodo'])['cierres'];

    expect($cierres->pluck('code')->all())->toBe(['DCNM', 'DIA']);
});

it('CP-RF07-24 no asigna un jefe ni un docente nuevos a un departamento archivado', function (string $rol) {
    $e = escenario();
    $id = departamentoLibre();
    departamentoArchivado($id);
    $usuario = usuarioCon($rol);
    $asignaciones = app(AsignacionService::class);

    expect(fn () => $rol === 'jefe_departamento'
        ? $asignaciones->asignarJefatura($e['administrador'], $usuario->id, $id)
        : $asignaciones->perfilDocente($e['administrador'], $usuario->id, $id))
        ->toThrow(ValidationException::class, 'El departamento elegido está archivado.');

    $this->assertDatabaseMissing('department_heads', ['department_id' => $id]);
    $this->assertDatabaseMissing('teacher_profiles', ['department_id' => $id]);
})->with(['jefe_departamento', 'docente']);

/* ------------------------------------------------------------------ CP-RF07-25 (panel académico) */

it('CP-RF07-25 el Administrador recorre departamentos, carreras y materias del plan', function () {
    $e = escenario();
    actingAs($e['administrador']);

    $this->get(route('catalogo.departamentos'))->assertOk()->assertSeeText(['Ingeniería y Arquitectura', 'Ciencias Naturales y Matemática']);
    $this->get(route('catalogo.departamentos.show', ['departamento' => $e['dia']]))->assertOk()->assertSeeText(['Ingeniería de Sistemas Informáticos', 'Plan 25']);
    $this->get(route('catalogo.departamentos.carrera', ['departamento' => $e['dia'], 'carrera' => $e['isi']]))->assertOk()->assertSeeText(['PRN115', 'Programación I']);
});

it('CP-RF07-25 al elegir la materia muestra sus secciones del período con enlace a la Vista de sección', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(PanelAcademico::class, ['departamento' => $e['dia'], 'carrera' => $e['isi']])
        ->call('elegirMateria', $e['prn'])
        ->assertSet('materiaId', $e['prn'])
        ->assertSeeText(['Grupo 01', $e['docente']->first_name.' '.$e['docente']->last_name])
        ->assertSeeHtml('href="'.route('secciones.contenidos', ['seccion' => $e['ulid']]).'"');
});

it('CP-RF07-25 responde 404 a una carrera de otro departamento', function () {
    $e = escenario();
    actingAs($e['administrador']);

    $this->get(route('catalogo.departamentos.carrera', ['departamento' => $e['dcnm'], 'carrera' => $e['isi']]))->assertNotFound();
});

it('CP-RF07-25 niega el panel académico a otros roles', function (string $rol) {
    $e = escenario();
    actingAs($e[$rol]);

    $this->get(route('catalogo.departamentos.show', ['departamento' => $e['dia']]))->assertForbidden();
})->with(['docente', 'jefe']);

/* ------------------------------------------------------------------ CP-RF07-26 (Vista de sección en solo lectura) */

it('CP-RF07-26 el Administrador abre las pestañas de la Vista de sección', function (string $ruta) {
    $e = escenario();
    actingAs($e['administrador']);

    $this->get(route($ruta, ['seccion' => $e['ulid']]))->assertOk();
})->with([
    'Contenidos' => 'secciones.contenidos',
    'Calificaciones' => 'secciones.notas',
    'Asistencia' => 'secciones.asistencia',
    'Progreso' => 'secciones.progreso',
]);

it('CP-RF07-26 el Administrador ve los contenidos sin acciones de edición', function () {
    $e = escenario();
    actingAs($e['administrador']);

    $this->get(route('secciones.contenidos', ['seccion' => $e['ulid']]))->assertOk()->assertDontSeeText(['Nueva unidad', 'Papelera']);
});

it('CP-RF07-26 niega al Administrador escribir contenido, pesos y asistencia de la sección', function () {
    $e = escenario();
    $computo = DbContext::system(fn () => DB::table('grading_components')->insertGetId([
        'section_id' => $e['seccion'], 'number' => 1, 'name' => 'Cómputo 1', 'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11',
        'weight_percent' => 100, 'created_at' => now(), 'updated_at' => now(),
    ]));
    $admin = $e['administrador'];

    expect(fn () => contenidos()->crearUnidad($admin, $e['seccion'], 'Unidad 1'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ComputoService::class)->guardarPesos($admin, $e['seccion'], [$computo => 100], []))->toThrow(AuthorizationException::class)
        ->and(fn () => app(AsistenciaService::class)->abrir($admin, $e['seccion'], 10))->toThrow(AuthorizationException::class);

    expect(DB::table('units')->where('section_id', $e['seccion'])->exists())->toBeFalse()
        ->and((float) DB::table('grading_components')->where('id', $computo)->value('weight_percent'))->toBe(100.0)
        ->and(DB::table('attendance_sessions')->where('section_id', $e['seccion'])->exists())->toBeFalse();
});

it('CP-RF07-26 el Docente de la sección sigue escribiendo contenido, pesos y asistencia', function () {
    $e = escenario();
    $computos = DbContext::system(fn () => collect([1, 2])->map(fn (int $n) => DB::table('grading_components')->insertGetId([
        'section_id' => $e['seccion'], 'number' => $n, 'name' => "Cómputo {$n}", 'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11',
        'weight_percent' => 50, 'created_at' => now(), 'updated_at' => now(),
    ]))->all());
    $docente = $e['docente'];

    contenidos()->crearUnidad($docente, $e['seccion'], 'Unidad 1');
    app(ComputoService::class)->guardarPesos($docente, $e['seccion'], [$computos[0] => 40, $computos[1] => 60], []);
    app(AsistenciaService::class)->abrir($docente, $e['seccion'], 10);

    expect(DB::table('units')->where('section_id', $e['seccion'])->pluck('title')->all())->toBe(['Unidad 1'])
        ->and(DB::table('grading_components')->whereIn('id', $computos)->orderBy('number')->pluck('weight_percent')->map(fn ($p) => (float) $p)->all())->toBe([40.0, 60.0])
        ->and(DB::table('attendance_sessions')->where('section_id', $e['seccion'])->where('status', 'open')->count())->toBe(1);
});

it('CP-RF07-26 niega la Vista de sección al Jefe y a Soporte', function (string $rol) {
    $e = escenario();
    actingAs($e[$rol]);

    $this->get(route('secciones.contenidos', ['seccion' => $e['ulid']]))->assertForbidden();
})->with(['jefe', 'soporte']);

/* ------------------------------------------------------------------ Carreras del departamento archivado (ADR-076) */

/**
 * Una carrera con su plan en un departamento nuevo, que luego se archiva.
 *
 * @return array{departamento: int, carrera: int, plan: int}
 */
function carreraDeDepartamentoArchivado(): array
{
    $departamento = departamentoLibre();

    return DbContext::system(function () use ($departamento): array {
        $carrera = DB::table('careers')->insertGetId(['department_id' => $departamento, 'code' => 'LQF', 'name' => 'Licenciatura en Química y Farmacia', 'created_at' => now(), 'updated_at' => now()]);
        $plan = DB::table('study_plans')->insertGetId(['career_id' => $carrera, 'name' => 'Plan 2020', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('departments')->where('id', $departamento)->update(['is_active' => false]);

        return compact('departamento', 'carrera', 'plan');
    });
}

it('CP-RF07-24 saca las carreras y los planes del departamento archivado de los selectores de asignación', function () {
    $e = escenario();
    $archivada = carreraDeDepartamentoArchivado();

    $opciones = app(AsignacionService::class)->opciones($e['administrador']);

    expect($opciones['carreras']->pluck('id')->map(fn ($id) => (int) $id)->all())->toBe([$e['isi']])
        ->and($opciones['planes']->pluck('id')->map(fn ($id) => (int) $id)->all())->toBe([$e['plan']])
        ->and($opciones['planes']->pluck('id')->all())->not->toContain($archivada['plan']);
});

it('CP-RF07-24 no asigna un coordinador ni un estudiante nuevos a una carrera de un departamento archivado', function (string $rol) {
    $e = escenario();
    $archivada = carreraDeDepartamentoArchivado();
    $usuario = usuarioCon($rol);
    $asignaciones = app(AsignacionService::class);

    expect(fn () => $rol === 'coordinador'
        ? $asignaciones->asignarCoordinacion($e['administrador'], $usuario->id, $archivada['carrera'])
        : $asignaciones->perfilEstudiante($e['administrador'], $usuario->id, ['carrera' => $archivada['carrera'], 'plan' => $archivada['plan'], 'ciclo' => 1, 'carne' => 'QF20001']))
        ->toThrow(ValidationException::class, 'La carrera elegida es de un departamento archivado.');

    $this->assertDatabaseMissing('career_coordinators', ['career_id' => $archivada['carrera']]);
    $this->assertDatabaseMissing('student_careers', ['career_id' => $archivada['carrera']]);
})->with(['coordinador', 'estudiante']);

it('CP-RF07-24 el coordinador y el estudiante que ya tenían la carrera la conservan al archivar su departamento', function () {
    $e = escenario();
    departamentoArchivado($e['dia']);
    $asignaciones = app(AsignacionService::class);

    $asignaciones->asignarCoordinacion($e['administrador'], $e['coordinador']->id, $e['isi']);
    $asignaciones->perfilEstudiante($e['administrador'], $e['estudiante']->id, ['carrera' => $e['isi'], 'plan' => $e['plan'], 'ciclo' => 2, 'carne' => 'GS21004']);

    $this->assertDatabaseHas('career_coordinators', ['career_id' => $e['isi'], 'user_id' => $e['coordinador']->id, 'valid_to' => null]);
    $this->assertDatabaseHas('student_careers', ['career_id' => $e['isi'], 'student_id' => $e['estudiante']->id, 'status' => 'active', 'current_cycle' => 2]);
});

it('CP-RF07-24 saca las carreras del departamento archivado del calendario y de la oferta', function () {
    $e = escenario();
    $antesCalendario = array_column(app(CalendarioPeriodoService::class)->ambitos($e['coordinador']), 'valor');
    $antesOferta = app(OfertaService::class)->estadisticas($e['administrador'])['carreras']->pluck('code')->all();

    departamentoArchivado($e['dia']);

    expect($antesCalendario)->toContain('carrera:'.$e['isi'])
        ->and(array_column(app(CalendarioPeriodoService::class)->ambitos($e['coordinador']), 'valor'))->not->toContain('carrera:'.$e['isi'])
        ->and($antesOferta)->toContain('ISI')
        ->and(app(OfertaService::class)->estadisticas($e['administrador'])['carreras']->pluck('code')->all())->not->toContain('ISI');
});

it('CP-RF07-24 no cuenta como autorizado el cierre de un departamento archivado en el panel del Administrador', function () {
    $e = escenario();
    $id = departamentoLibre();
    DbContext::system(fn () => DB::table('department_closures')->insert([
        'period_id' => $e['periodo'], 'department_id' => $id, 'authorized_by' => $e['jefe']->id, 'authorized_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]));
    departamentoArchivado($id);

    $cierres = app(PanelService::class)->administrador($e['administrador'])['cierres'];

    expect($cierres)->toBe(['autorizados' => 0, 'departamentos' => 2]);
});

/* ------------------------------------------------------------------ Asignaturas en un departamento archivado (ADR-076) */

it('CP-RF07-23 no crea una asignatura nueva en un departamento archivado', function () {
    $e = escenario();
    $id = departamentoLibre();
    departamentoArchivado($id);

    expect(fn () => app(CatalogoService::class)->guardarAsignatura($e['administrador'], null, ['codigo' => 'QUI115', 'nombre' => 'Química General', 'creditos' => 4, 'departamento' => $id]))
        ->toThrow(ValidationException::class, 'El departamento elegido está archivado.');

    $this->assertDatabaseMissing('subjects', ['code' => 'QUI115']);
});

it('CP-RF07-23 sigue editando la asignatura de un departamento archivado sin perder su departamento', function () {
    $e = escenario();
    departamentoArchivado($e['dia']);
    $catalogo = app(CatalogoService::class);

    $catalogo->guardarAsignatura($e['administrador'], $e['prn'], ['codigo' => 'PRN115', 'nombre' => 'Programación I (Plan 25)', 'creditos' => 4, 'departamento' => $e['dia']]);

    $this->assertDatabaseHas('subjects', ['id' => $e['prn'], 'name' => 'Programación I (Plan 25)', 'department_id' => $e['dia']]);
    expect($catalogo->departamentosGestionables($e['administrador'])->pluck('code')->all())->toBe(['DCNM'])
        ->and($catalogo->departamentosGestionables($e['administrador'], $e['prn'])->pluck('code')->all())->toBe(['DCNM', 'DIA']);
});

/* ------------------------------------------------------------------ Formato y período del panel */

it('CP-RF07-25 toma el período en curso si el período elegido viene vacío o no es un número', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(PanelAcademico::class, ['departamento' => $e['dia'], 'carrera' => $e['isi']])
        ->call('elegirMateria', $e['prn'])
        ->set('periodoId', '')
        ->assertOk()
        ->assertSeeText('Grupo 01');

    Livewire::withQueryParams(['materia' => $e['prn'], 'periodo' => 'abc'])
        ->test(PanelAcademico::class, ['departamento' => $e['dia'], 'carrera' => $e['isi']])
        ->assertOk()
        ->assertSeeText('Grupo 01');
});
