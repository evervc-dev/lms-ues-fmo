<?php

use App\Models\User;
use App\Modules\Academica\Livewire\ClavesMatricula;
use App\Modules\Academica\Livewire\Inscripcion;
use App\Modules\Academica\Livewire\InscripcionDetalle;
use App\Modules\Academica\Services\CatalogoService;
use App\Modules\Academica\Services\MatriculaService;
use App\Modules\Academica\Services\PeriodoService;
use App\Modules\Contenidos\Livewire\ContenidosSeccion;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
| Inscripción de materias con clave (RF29, ADR-050). El estudiante ve las asignaturas de su plan del ciclo en curso
| (ciclo I: ciclos impares del plan; ciclo II: pares), abre una, ve su descripción y sus grupos y se inscribe con la
| clave. Las claves las gestionan el Coordinador (su carrera) y el Jefe (su departamento); la descripción, el docente
| con grupo de la asignatura y el Administrador.
|
| El escenario usa 2026-II (27/07–11/12): ciclo II, así que PRN115 (ciclo 1 del plan) queda fuera de la lista.
*/

function inscripcion(): MatriculaService
{
    return app(MatriculaService::class);
}

/**
 * Una asignatura en el plan indicado (Plan 25 de ISI por defecto) con una sección abierta los lunes.
 *
 * @return array{asignatura: int, seccion: int}
 */
function inscripcionMateria(array $e, string $codigo, int $ciclo = 2, ?int $plan = null, ?int $departamento = null, string $inicio = '13:00', string $fin = '14:40'): array
{
    return DbContext::system(function () use ($e, $codigo, $ciclo, $plan, $departamento, $inicio, $fin) {
        $asignatura = DB::table('subjects')->insertGetId(['code' => $codigo, 'name' => 'Materia '.$codigo, 'credits' => 4, 'department_id' => $departamento ?? $e['dia'],
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('career_subjects')->insert(['study_plan_id' => $plan ?? $e['plan'], 'subject_id' => $asignatura, 'cycle' => $ciclo]);
        $seccion = DB::table('sections')->insertGetId(['ulid' => (string) str()->ulid(), 'period_id' => $e['periodo'], 'subject_id' => $asignatura, 'code' => '01',
            'max_capacity' => 40, 'created_at' => now(), 'updated_at' => now()]);
        // Un aula por sección: el EXCLUDE section_schedules_classroom_no_overlap prohíbe dos clases
        // en la misma aula a la misma hora. Así dos secciones pueden compartir horario (y chocar
        // en el horario del estudiante) sin chocar en el aula, que es lo realista.
        $aula = DB::table('classrooms')->insertGetId(['code' => 'A-'.$codigo, 'capacity' => 45, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('section_schedules')->insert(['section_id' => $seccion, 'period_id' => $e['periodo'], 'classroom_id' => $aula, 'weekday' => 1,
            'start_time' => $inicio, 'end_time' => $fin, 'created_at' => now(), 'updated_at' => now()]);

        return ['asignatura' => $asignatura, 'seccion' => $seccion];
    });
}

/** Otra carrera (Física, de DCNM) con su plan: sus asignaturas no son de la carrera del estudiante. */
function inscripcionOtroPlan(array $e): int
{
    return DbContext::system(function () use ($e) {
        $carrera = DB::table('careers')->insertGetId(['department_id' => $e['dcnm'], 'code' => 'FIS', 'name' => 'Licenciatura en Física', 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('study_plans')->insertGetId(['career_id' => $carrera, 'name' => 'Plan 20', 'created_at' => now(), 'updated_at' => now()]);
    });
}

/** Un Jefe del departamento indicado. */
function inscripcionJefeDe(int $departamento): User
{
    $jefe = usuarioCon('jefe_departamento');
    DbContext::system(fn () => DB::table('department_heads')->insert(['user_id' => $jefe->id, 'department_id' => $departamento, 'valid_from' => '2025-01-01',
        'created_at' => now(), 'updated_at' => now()]));

    return $jefe;
}

/** Cambia el mes en que empieza el ciclo II (`system_settings.ciclo_ii_mes_inicio`). */
function inscripcionMesCicloDos(int $mes): void
{
    DbContext::system(fn () => DB::table('system_settings')->updateOrInsert(['key' => 'ciclo_ii_mes_inicio'], ['value' => json_encode($mes), 'updated_at' => now()]));
}

/** Clave vigente de una asignatura, generada por quien se indique. */
function inscripcionClave(User $actor, int $asignatura, ?string $clave = null): string
{
    actingAs($actor);

    return inscripcion()->generarClave($actor, $asignatura, null, $clave);
}

it('CP-RF29-03 no acepta la clave de una asignatura de otra carrera ni de un ciclo de otra paridad, sin contarla como intento fallido', function () {
    $e = escenario();
    $ajena = inscripcionMateria($e, 'FIS215', plan: inscripcionOtroPlan($e), departamento: $e['dcnm']);
    $claveAjena = inscripcionClave(inscripcionJefeDe($e['dcnm']), $ajena['asignatura']);
    $clavePrn = inscripcionClave($e['coordinador'], $e['prn']);
    DbContext::system(fn () => DB::table('enrollments')->where('id', $e['matricula'])->update(['status' => 'withdrawn']));
    actingAs($e['estudiante']);

    expect(fn () => inscripcion()->automatricular($e['estudiante'], $ajena['asignatura'], $claveAjena, $ajena['seccion']))
        ->toThrow(ValidationException::class, 'La materia no corresponde a su carrera y ciclo.')
        ->and(fn () => inscripcion()->automatricular($e['estudiante'], $e['prn'], $clavePrn, $e['seccion']))
        ->toThrow(ValidationException::class, 'La materia no corresponde a su carrera y ciclo.');

    expect(RateLimiter::attempts('automatricula:'.$e['estudiante']->id))->toBe(0)
        ->and(DB::table('enrollments')->where('student_id', $e['estudiante']->id)->where('status', 'active')->exists())->toBeFalse();
});

it('CP-RF29-03 la clave de otra asignatura de su carrera no sirve para esta', function () {
    $e = escenario();
    $bdd = inscripcionMateria($e, 'BDD115');
    $sis = inscripcionMateria($e, 'SIS215', inicio: '15:00', fin: '16:40');
    inscripcionClave($e['coordinador'], $bdd['asignatura']);
    $claveSis = inscripcionClave($e['coordinador'], $sis['asignatura']);
    actingAs($e['estudiante']);

    expect(fn () => inscripcion()->automatricular($e['estudiante'], $bdd['asignatura'], $claveSis, $bdd['seccion']))
        ->toThrow(ValidationException::class, 'La clave no es válida o venció.');

    expect(DB::table('enrollments')->where('section_id', $bdd['seccion'])->exists())->toBeFalse();
});

it('CP-RF29-08 lista solo las asignaturas de la carrera del estudiante y de la paridad del ciclo en curso', function () {
    $e = escenario();
    $bdd = inscripcionMateria($e, 'BDD115');
    inscripcionMateria($e, 'SIS415', ciclo: 4, inicio: '15:00', fin: '16:40');
    inscripcionMateria($e, 'ANS315', ciclo: 3);
    inscripcionMateria($e, 'FIS215', plan: inscripcionOtroPlan($e), departamento: $e['dcnm']);
    inscripcionClave($e['coordinador'], $bdd['asignatura']);
    actingAs($e['estudiante']);

    $materias = inscripcion()->materiasInscribibles($e['estudiante'])->keyBy('code');

    expect($materias->keys()->all())->toBe(['BDD115', 'SIS415'])
        ->and($materias['BDD115']->tiene_clave)->toBeTrue()
        ->and($materias['SIS415']->tiene_clave)->toBeFalse()
        ->and($materias['BDD115']->inscrito)->toBeFalse();

    Livewire::test(Inscripcion::class)
        ->assertSee(['Inscripción de materias', 'Ciclo II', 'BDD115', 'SIS415', 'Inscripción no habilitada'])
        ->assertDontSee(['PRN115', 'ANS315', 'FIS215']);
});

it('CP-RF29-08 el ciclo sale del mes central del período y del parámetro ciclo_ii_mes_inicio', function () {
    $e = escenario();
    inscripcionMateria($e, 'BDD115');
    $primerSemestre = DbContext::system(fn () => DB::table('academic_periods')->insertGetId(['name' => '2027-I', 'start_date' => '2027-02-01', 'end_date' => '2027-06-18',
        'status' => 'planned', 'created_at' => now(), 'updated_at' => now()]));
    $periodos = app(PeriodoService::class);

    expect($periodos->ciclo())->toBe(2)
        ->and($periodos->ciclo($e['periodo']))->toBe(2)
        ->and($periodos->ciclo($primerSemestre))->toBe(1);

    // El punto medio de 2026-II (27/07–11/12) es el 03/10: con el ciclo II desde noviembre pasa a ser ciclo I.
    inscripcionMesCicloDos(11);
    actingAs($e['estudiante']);

    expect($periodos->ciclo())->toBe(1)
        ->and(inscripcion()->materiasInscribibles($e['estudiante'])->pluck('code')->all())->toBe(['PRN115']);
});

it('CP-RF29-08 abre «Inscripción de materias» al estudiante y no al docente', function () {
    $e = escenario();

    actingAs($e['estudiante'])->get(route('inscripcion'))->assertOk()->assertSeeText('Inscripción de materias');
    actingAs($e['docente'])->get(route('inscripcion'))->assertForbidden();
});

it('CP-RF29-09 el detalle muestra la descripción y los grupos, y el estudiante se inscribe con la clave', function () {
    $e = escenario();
    $bdd = inscripcionMateria($e, 'BDD115');
    DbContext::system(function () use ($e, $bdd): void {
        DB::table('subjects')->where('id', $bdd['asignatura'])->update(['description' => "Modelo relacional y SQL.\n<script>alert(1)</script>"]);
        DB::table('teaching_assignments')->insert(['section_id' => $bdd['seccion'], 'teacher_id' => $e['docente']->id, 'is_responsible' => true, 'started_at' => '2026-07-01',
            'created_at' => now(), 'updated_at' => now()]);
    });
    $clave = inscripcionClave($e['coordinador'], $bdd['asignatura']);
    actingAs($e['estudiante']);

    $detalle = inscripcion()->detalleMateria($e['estudiante'], $bdd['asignatura']);
    expect($detalle['asignatura']->code)->toBe('BDD115')
        ->and($detalle['tiene_clave'])->toBeTrue()
        ->and($detalle['inscrito'])->toBeFalse()
        ->and($detalle['secciones']->sole())->id->toBe($bdd['seccion'])->docente->toContain($e['docente']->first_name);

    Livewire::test(InscripcionDetalle::class, ['asignatura' => $bdd['asignatura']])
        ->assertSeeHtml('Modelo relacional y SQL.<br />')
        ->assertDontSeeHtml('<script>alert(1)</script>')
        ->assertSee([$e['docente']->first_name, '13:00', 'A-BDD115', 'Inscribirme con clave'])
        ->set('seccionElegida', $bdd['seccion'])->set('clave', 'CLAVE-FALSA')->call('inscribirme')->assertHasErrors(['clave'])
        ->set('clave', $clave)->call('inscribirme')->assertHasNoErrors()->assertRedirect(route('materias'));

    expect(DB::table('enrollments')->where('student_id', $e['estudiante']->id)->where('section_id', $bdd['seccion'])->value('enrolled_via'))->toBe('key')
        ->and(inscripcion()->materiasInscribibles($e['estudiante'])->firstWhere('code', 'BDD115')->inscrito)->toBeTrue();
});

it('CP-RF29-09 exige la clave y el grupo en el formulario de inscripción', function () {
    $e = escenario();
    $bdd = inscripcionMateria($e, 'BDD115');
    actingAs($e['estudiante']);

    Livewire::test(InscripcionDetalle::class, ['asignatura' => $bdd['asignatura']])
        ->call('inscribirme')->assertHasErrors(['clave' => 'required', 'seccionElegida' => 'required']);
});

it('CP-RF29-09 no abre una asignatura de otra carrera ni de un ciclo de otra paridad', function () {
    $e = escenario();
    $ajena = inscripcionMateria($e, 'FIS215', plan: inscripcionOtroPlan($e), departamento: $e['dcnm']);
    actingAs($e['estudiante']);

    $this->get(route('inscripcion.show', ['asignatura' => $ajena['asignatura']]))->assertNotFound();
    $this->get(route('inscripcion.show', ['asignatura' => $e['prn']]))->assertNotFound();
});

it('CP-RF29-10 una clave escrita a mano funciona y deja sin efecto la anterior', function (string $rol) {
    $e = escenario();
    $bdd = inscripcionMateria($e, 'BDD115');
    $anterior = inscripcionClave($e[$rol], $bdd['asignatura']);
    $propia = inscripcionClave($e[$rol], $bdd['asignatura'], '  BDD-2026-II ');
    actingAs($e['estudiante']);

    expect($propia)->toBe('BDD-2026-II')
        ->and(fn () => inscripcion()->automatricular($e['estudiante'], $bdd['asignatura'], $anterior, $bdd['seccion']))->toThrow(ValidationException::class, 'La clave no es válida o venció.');

    inscripcion()->automatricular($e['estudiante'], $bdd['asignatura'], 'BDD-2026-II', $bdd['seccion']);

    expect(DB::table('enrollments')->where('student_id', $e['estudiante']->id)->where('section_id', $bdd['seccion'])->value('status'))->toBe('active');
})->with(['coordinador', 'jefe']);

it('CP-RF29-10 la pantalla de claves usa la clave escrita y rechaza la de formato no válido', function () {
    $e = escenario();
    actingAs($e['coordinador']);

    Livewire::test(ClavesMatricula::class)
        ->set('claveManual', 'no válida!')->call('generar', $e['prn'])->assertHasErrors(['claveManual'])
        ->set('claveManual', 'PRN-2026-II')->call('generar', $e['prn'])->assertHasNoErrors()->assertSet('claveNueva', 'PRN-2026-II');

    expect(Hash::check('PRN-2026-II', DB::table('enrollment_keys')->where('subject_id', $e['prn'])->where('is_active', true)->value('key_hash')))->toBeTrue();
});

it('CP-RF29-11 «Aplicar a todas» pone la misma clave en todas las asignaturas del ámbito y en ninguna de fuera', function (string $rol) {
    $e = escenario();
    $bdd = inscripcionMateria($e, 'BDD115');
    $ajena = inscripcionMateria($e, 'FIS215', plan: inscripcionOtroPlan($e), departamento: $e['dcnm']);
    $anterior = inscripcionClave($e[$rol], $e['prn']);
    actingAs($e[$rol]);

    $cuantas = inscripcion()->aplicarClaveATodas($e[$rol], 'ISI-2026-II');

    $activas = DB::table('enrollment_keys')->where('period_id', $e['periodo'])->where('is_active', true)->pluck('key_hash', 'subject_id');
    expect($cuantas)->toBe(2)
        ->and($activas->keys()->sort()->values()->all())->toBe(collect([$e['prn'], $bdd['asignatura']])->sort()->values()->all())
        ->and($activas->every(fn (string $hash) => Hash::check('ISI-2026-II', $hash)))->toBeTrue()
        ->and(Hash::check($anterior, $activas[$e['prn']]))->toBeFalse()
        ->and(DB::table('enrollment_keys')->whereIn('subject_id', [$e['mat'], $ajena['asignatura']])->exists())->toBeFalse();
})->with(['coordinador', 'jefe']);

it('CP-RF29-11 la pantalla de claves aplica la clave común a todo el ámbito y exige una clave válida', function () {
    $e = escenario();
    inscripcionMateria($e, 'BDD115');
    actingAs($e['jefe']);

    Livewire::test(ClavesMatricula::class)
        ->call('aplicarATodas')->assertHasErrors(['claveComun' => 'required'])
        ->set('claveComun', 'con espacio')->call('aplicarATodas')->assertHasErrors(['claveComun'])
        ->set('claveComun', 'DIA-2026-II')->call('aplicarATodas')->assertHasNoErrors()->assertSet('claveNueva', 'DIA-2026-II');

    expect(DB::table('enrollment_keys')->where('is_active', true)->count())->toBe(2);
});

it('CP-RF29-11 «Aplicar a todas» no está al alcance del docente ni del estudiante', function (string $rol) {
    $e = escenario();
    actingAs($e[$rol]);

    expect(fn () => inscripcion()->aplicarClaveATodas($e[$rol], 'ISI-2026-II'))->toThrow(AuthorizationException::class);

    expect(DB::table('enrollment_keys')->exists())->toBeFalse();
})->with(['docente', 'estudiante']);

it('CP-RF29-12 el Jefe gestiona las claves de su departamento y no las de otro', function () {
    $e = escenario();
    $jefeDcnm = inscripcionJefeDe($e['dcnm']);
    actingAs($e['jefe']);

    expect(inscripcion()->claves($e['jefe'])->pluck('code')->all())->toBe(['PRN115'])
        ->and(fn () => inscripcion()->generarClave($e['jefe'], $e['mat']))->toThrow(AuthorizationException::class)
        ->and(fn () => inscripcion()->desactivarClave($e['jefe'], $e['mat']))->toThrow(AuthorizationException::class);

    Livewire::test(ClavesMatricula::class)->assertSee('PRN115')->assertDontSee('MAT115');
    $this->get(route('matricula.claves'))->assertOk();

    inscripcionClave($jefeDcnm, $e['mat']);

    expect(DB::table('enrollment_keys')->where('is_active', true)->pluck('subject_id')->all())->toBe([$e['mat']]);
});

it('CP-RF29-13 el docente con grupo de la asignatura y el Administrador editan su descripción', function (string $rol) {
    $e = escenario();
    actingAs($e[$rol]);

    app(CatalogoService::class)->editarDescripcion($e[$rol], $e['prn'], 'Fundamentos de programación estructurada.');

    expect(DB::table('subjects')->where('id', $e['prn'])->value('description'))->toBe('Fundamentos de programación estructurada.');
})->with(['docente', 'administrador']);

it('CP-RF29-13 un docente sin grupo vigente de la asignatura, el Coordinador y el estudiante reciben 403 al editar la descripción', function (string $rol) {
    $e = escenario();
    $actor = match ($rol) {
        'docente de otra asignatura' => docenteAjeno($e),
        'docente con el grupo terminado' => $e['docente'],
        default => $e[$rol],
    };
    if ($rol === 'docente con el grupo terminado') {
        DbContext::system(fn () => DB::table('teaching_assignments')->where('teacher_id', $actor->id)->update(['ended_at' => '2026-08-01']));
    }
    actingAs($actor);

    expect(fn () => app(CatalogoService::class)->editarDescripcion($actor, $e['prn'], 'Intento ajeno.'))->toThrow(AuthorizationException::class);

    expect(DB::table('subjects')->where('id', $e['prn'])->value('description'))->toBeNull();
})->with(['docente de otra asignatura', 'docente con el grupo terminado', 'coordinador', 'estudiante']);

it('CP-RF29-13 el docente guarda la descripción desde los contenidos de su sección', function () {
    $e = escenario();
    actingAs($e['docente']);

    DbContext::system(fn () => DB::table('subjects')->where('id', $e['prn'])->update(['description' => 'Texto anterior.']));

    Livewire::test(ContenidosSeccion::class, ['seccion' => $e['ulid']])
        ->call('abrirDescripcion')->assertSet('descripcion', 'Texto anterior.')
        ->set('descripcion', str_repeat('a', 2001))->call('guardarDescripcion')->assertHasErrors(['descripcion' => 'max'])
        ->set('descripcion', 'Fundamentos de programación estructurada.')->call('guardarDescripcion')->assertHasNoErrors();

    expect(DB::table('subjects')->where('id', $e['prn'])->value('description'))->toBe('Fundamentos de programación estructurada.');
});

it('CP-RF29-08 la lista se agrupa por el ciclo del plan y se busca por código o nombre, sin distinguir tildes', function () {
    $e = escenario();
    inscripcionMateria($e, 'MAT225', 2);
    $avanzada = inscripcionMateria($e, 'PRN315', 4);
    DbContext::system(fn () => DB::table('subjects')->where('id', $avanzada['asignatura'])->update(['name' => 'Programación Avanzada']));
    actingAs($e['estudiante']);

    Livewire::test(Inscripcion::class)
        ->assertSeeInOrder(['Ciclo 2', 'MAT225', 'Ciclo 4', 'Programación Avanzada'])
        ->set('buscar', 'MAT225')->assertSee('MAT225')->assertDontSee('Programación Avanzada')
        // Sin tildes y por un trozo del nombre, como en el catálogo (H-10 de la QA del 27/10).
        ->set('buscar', 'programacion')->assertSee('Programación Avanzada')->assertDontSee('MAT225')
        ->set('buscar', 'zzz')->assertSee('Ninguna materia coincide con su búsqueda');
});
