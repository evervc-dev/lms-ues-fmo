<?php

use App\Models\User;
use App\Modules\Academica\Services\CargaAcademicaService;
use App\Modules\Calificaciones\Livewire\PesosSeccion;
use App\Modules\Calificaciones\Services\CalificacionService;
use App\Modules\Calificaciones\Services\ComputoService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
| Pesos de la sección (RF17, CP-RF17-01; Flujo Cálculo de nota final): los cómputos suman 100 % y las actividades
| de cada cómputo no pasan de 100 % al guardar (y suman 100 % para publicar el acta). Los pesos se bloquean con
| notas publicadas o con el acta publicada. La sección nace con los cómputos de la plantilla del período.
*/

/**
 * Dos cómputos de 50 %, cada uno con dos actividades de 50 %, sin publicar.
 *
 * @param  array<string, mixed>  $e
 * @return array{computos: array<int, int>, actividades: array<int, array<int, int>>}
 */
function pesosEstructura(array $e, ?int $seccion = null): array
{
    return DbContext::system(function () use ($e, $seccion) {
        $computos = [];
        $actividades = [];
        foreach ([1, 2] as $n) {
            $c = DB::table('grading_components')->insertGetId([
                'section_id' => $seccion ?? $e['seccion'], 'number' => $n, 'name' => "Cómputo {$n}", 'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11',
                'weight_percent' => 50, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $computos[] = $c;
            $actividades[] = collect([1, 2])->map(fn (int $a) => DB::table('grade_items')->insertGetId([
                'component_id' => $c, 'section_id' => $seccion ?? $e['seccion'], 'item_type' => 'manual', 'title' => "Actividad {$n}.{$a}",
                'weight_percent' => 50, 'is_published' => false, 'created_at' => now(), 'updated_at' => now(),
            ]))->all();
        }

        return ['computos' => $computos, 'actividades' => $actividades];
    });
}

/**
 * Plantilla de cómputos del período del escenario: 30 / 30 / 40.
 *
 * @param  array<string, mixed>  $e
 */
function pesosPlantilla(array $e): void
{
    DbContext::system(fn () => collect([[1, 30], [2, 30], [3, 40]])->each(fn (array $c) => DB::table('grading_component_templates')->insert([
        'period_id' => $e['periodo'], 'number' => $c[0], 'name' => "Cómputo {$c[0]}", 'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11',
        'weight_percent' => $c[1], 'created_at' => now(), 'updated_at' => now(),
    ])));
}

/**
 * @return array<int, float>
 */
function pesosDe(string $tabla, array $ids): array
{
    return DB::table($tabla)->whereIn('id', $ids)->orderBy('id')->pluck('weight_percent')->map(fn ($p) => (float) $p)->all();
}

/**
 * Errores de validación de la acción (vacío si no hubo).
 *
 * @return array<string, array<int, string>>
 */
function pesosErrores(Closure $accion): array
{
    try {
        $accion();
    } catch (ValidationException $excepcion) {
        return $excepcion->errors();
    }

    return [];
}

/**
 * Actor del escenario por nombre; «docente ajeno» imparte solo la otra sección (MAT115).
 *
 * @param  array<string, mixed>  $e
 */
function pesosUsuario(array $e, string $quien): User
{
    return match ($quien) {
        'docente ajeno' => docenteAjeno($e),
        default => $e[$quien],
    };
}

/* ------------------------------------------------------------------ Reglas de suma */

it('CP-RF17-01 guarda los pesos cuando los cómputos suman 100 % y las actividades de cada cómputo no pasan de 100 %', function () {
    $e = escenario();
    $s = pesosEstructura($e);
    actingAs($e['docente']);

    app(ComputoService::class)->guardarPesos($e['docente'], $e['seccion'], [$s['computos'][0] => 40, $s['computos'][1] => 60], [$s['actividades'][0][0] => 70, $s['actividades'][0][1] => 30]);

    expect(pesosDe('grading_components', $s['computos']))->toEqual([40.0, 60.0])
        ->and(pesosDe('grade_items', $s['actividades'][0]))->toEqual([70.0, 30.0])
        ->and(pesosDe('grade_items', $s['actividades'][1]))->toEqual([50.0, 50.0])
        ->and(app(ComputoService::class)->problemas($e['seccion']))->toBe([]);
});

it('CP-RF17-01 rechaza los cómputos que no suman 100 % y no cambia ningún peso', function (array $pesos, string $suma) {
    $e = escenario();
    $s = pesosEstructura($e);
    actingAs($e['docente']);

    $errores = pesosErrores(fn () => app(ComputoService::class)->guardarPesos($e['docente'], $e['seccion'], array_combine($s['computos'], $pesos), []));

    expect($errores['computos'][0])->toBe("Los cómputos suman {$suma} %: deben sumar exactamente 100 %.")
        ->and(pesosDe('grading_components', $s['computos']))->toEqual([50.0, 50.0]);
})->with([
    'menos de 100' => [[40, 50], '90'],
    'más de 100' => [[60, 50.5], '110.5'],
]);

it('CP-RF17-01 la suma de los cómputos cuenta los que no se envían con su peso actual', function () {
    $e = escenario();
    $s = pesosEstructura($e);
    actingAs($e['docente']);

    // Solo se cambia el primero: 60 + 50 (actual) = 110.
    $errores = pesosErrores(fn () => app(ComputoService::class)->guardarPesos($e['docente'], $e['seccion'], [$s['computos'][0] => 60], []));

    expect($errores['computos'][0])->toBe('Los cómputos suman 110 %: deben sumar exactamente 100 %.')
        ->and(pesosDe('grading_components', $s['computos']))->toEqual([50.0, 50.0]);
});

it('CP-RF17-01 rechaza las actividades de un cómputo que pasan de 100 % y no cambia ningún peso', function () {
    $e = escenario();
    $s = pesosEstructura($e);
    actingAs($e['docente']);

    $errores = pesosErrores(fn () => app(ComputoService::class)->guardarPesos($e['docente'], $e['seccion'], [$s['computos'][0] => 40, $s['computos'][1] => 60],
        [$s['actividades'][1][0] => 70, $s['actividades'][1][1] => 40]));

    expect($errores['actividades'][0])->toBe('Las actividades de Cómputo 2 suman 110 %: no pueden pasar del 100 %.')
        ->and(pesosDe('grading_components', $s['computos']))->toEqual([50.0, 50.0])
        ->and(pesosDe('grade_items', $s['actividades'][1]))->toEqual([50.0, 50.0]);
});

it('CP-RF17-01 rechaza un peso de 0, negativo o mayor que 100', function (string $nivel, float $peso) {
    $e = escenario();
    $s = pesosEstructura($e);
    actingAs($e['docente']);
    [$computos, $actividades] = $nivel === 'computo'
        ? [[$s['computos'][0] => $peso, $s['computos'][1] => 100 - $peso], []]
        : [[], [$s['actividades'][0][0] => $peso]];

    $errores = pesosErrores(fn () => app(ComputoService::class)->guardarPesos($e['docente'], $e['seccion'], $computos, $actividades));

    expect($errores['computos'][0])->toBe('Cada peso debe ser mayor que 0 % y no pasar de 100 %.')
        ->and(pesosDe('grading_components', $s['computos']))->toEqual([50.0, 50.0])
        ->and(pesosDe('grade_items', $s['actividades'][0]))->toEqual([50.0, 50.0]);
})->with([
    'cómputo en 0' => ['computo', 0],
    'cómputo negativo' => ['computo', -10],
    'actividad en 0' => ['actividad', 0],
    'actividad negativa' => ['actividad', -5],
    'actividad sobre 100' => ['actividad', 101],
]);

it('CP-RF17-01 rechaza cómputos o actividades de otra sección y no los cambia', function (string $nivel) {
    $e = escenario();
    $s = pesosEstructura($e);
    $ajena = pesosEstructura($e, $e['otra']);
    actingAs($e['docente']);
    [$computos, $actividades] = $nivel === 'computo'
        ? [[$s['computos'][0] => 50, $ajena['computos'][0] => 50], []]
        : [[], [$ajena['actividades'][0][0] => 10]];

    $errores = pesosErrores(fn () => app(ComputoService::class)->guardarPesos($e['docente'], $e['seccion'], $computos, $actividades));

    expect($errores)->not->toBe([])
        ->and(pesosDe('grading_components', $ajena['computos']))->toEqual([50.0, 50.0])
        ->and(pesosDe('grade_items', $ajena['actividades'][0]))->toEqual([50.0, 50.0]);
})->with(['computo', 'actividad']);

/* ------------------------------------------------------------------ Bloqueo */

it('CP-RF17-01 bloquea los pesos cuando la sección tiene notas publicadas', function () {
    $e = escenario();
    $s = pesosEstructura($e);
    actingAs($e['docente']);
    app(CalificacionService::class)->calificar($e['docente'], $s['actividades'][0][0], $e['matricula'], 8);
    app(CalificacionService::class)->publicar($e['docente'], $s['actividades'][0][0]);

    $errores = pesosErrores(fn () => app(ComputoService::class)->guardarPesos($e['docente'], $e['seccion'], [$s['computos'][0] => 40, $s['computos'][1] => 60], []));

    expect($errores['computos'][0])->toBe('La sección ya tiene notas publicadas: no se pueden cambiar los pesos.')
        ->and(pesosDe('grading_components', $s['computos']))->toEqual([50.0, 50.0]);
});

it('CP-RF17-01 bloquea los pesos con el acta publicada o validada', function (string $estado) {
    $e = escenario();
    $s = pesosEstructura($e);
    DbContext::system(fn () => DB::table('grade_sheets')->insert([
        'section_id' => $e['seccion'], 'status' => $estado, 'published_by' => $e['docente']->id, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]));
    actingAs($e['docente']);

    $errores = pesosErrores(fn () => app(ComputoService::class)->guardarPesos($e['docente'], $e['seccion'], [$s['computos'][0] => 40, $s['computos'][1] => 60], []));

    expect($errores['computos'][0])->toBe('El acta está publicada: no se pueden cambiar los pesos.')
        ->and(pesosDe('grading_components', $s['computos']))->toEqual([50.0, 50.0]);
})->with(['published', 'validated']);

it('CP-RF17-01 con el acta devuelta y sin notas publicadas los pesos se pueden cambiar', function () {
    $e = escenario();
    $s = pesosEstructura($e);
    DbContext::system(fn () => DB::table('grade_sheets')->insert([
        'section_id' => $e['seccion'], 'status' => 'returned', 'return_reason' => 'Revise los pesos.', 'created_at' => now(), 'updated_at' => now(),
    ]));

    expect(app(ComputoService::class)->bloqueo($e['seccion']))->toBeNull();
});

/* ------------------------------------------------------------------ Publicar el acta */

it('CP-RF17-01 no publica el acta si las actividades de un cómputo no suman 100 % y sí cuando todo suma 100 %', function () {
    $e = escenario();
    $s = pesosEstructura($e);
    actingAs($e['docente']);
    // Guardar con menos de 100 % en un cómputo se permite (el docente aún agrega actividades); publicar el acta, no.
    app(ComputoService::class)->guardarPesos($e['docente'], $e['seccion'], [], [$s['actividades'][1][0] => 30]);

    $errores = pesosErrores(fn () => app(CalificacionService::class)->publicarActa($e['docente'], $e['seccion']));
    expect($errores['acta'][0])->toContain('Las actividades de Cómputo 2 suman 80 %: deben sumar 100 %.')
        ->and(DB::table('grade_sheets')->where('section_id', $e['seccion'])->exists())->toBeFalse();

    app(ComputoService::class)->guardarPesos($e['docente'], $e['seccion'], [], [$s['actividades'][1][0] => 50]);
    app(CalificacionService::class)->publicarActa($e['docente'], $e['seccion']);

    expect(DB::table('grade_sheets')->where('section_id', $e['seccion'])->value('status'))->toBe('published');
});

it('CP-RF17-01 no publica el acta de una sección cuyos cómputos no suman 100 %', function () {
    $e = escenario();
    $s = pesosEstructura($e);
    DbContext::system(fn () => DB::table('grading_components')->where('id', $s['computos'][1])->update(['weight_percent' => 40]));
    actingAs($e['docente']);

    $errores = pesosErrores(fn () => app(CalificacionService::class)->publicarActa($e['docente'], $e['seccion']));

    expect($errores['acta'][0])->toContain('Los cómputos suman 90 %: deben sumar 100 %.')
        ->and(DB::table('grade_sheets')->where('section_id', $e['seccion'])->exists())->toBeFalse();
});

/* ------------------------------------------------------------------ Plantilla del período */

it('CP-RF17-01 la sección nueva nace con los cómputos de la plantilla del período', function () {
    $e = escenario();
    pesosPlantilla($e);
    actingAs($e['coordinador']);

    $seccion = app(CargaAcademicaService::class)->crearSeccion($e['coordinador'], $e['periodo'], $e['prn'], '02', 40);

    $computos = DB::table('grading_components')->where('section_id', $seccion)->orderBy('number')->get();
    expect($computos->pluck('name')->all())->toBe(['Cómputo 1', 'Cómputo 2', 'Cómputo 3'])
        ->and($computos->pluck('weight_percent')->map(fn ($p) => (float) $p)->all())->toEqual([30.0, 30.0, 40.0]);
});

it('CP-RF17-01 copiarPlantilla es idempotente: no duplica ni pisa los cómputos de una sección que ya los tiene', function () {
    $e = escenario();
    pesosPlantilla($e);
    $s = pesosEstructura($e);
    $computos = app(ComputoService::class);

    $copiados = DbContext::system(fn () => $computos->copiarPlantilla($e['seccion']));

    expect($copiados)->toBe(0)
        ->and(DB::table('grading_components')->where('section_id', $e['seccion'])->count())->toBe(2)
        ->and(pesosDe('grading_components', $s['computos']))->toEqual([50.0, 50.0]);
});

it('CP-RF17-01 la migración copia la plantilla a las secciones sin cómputos y es idempotente', function () {
    $e = escenario();
    pesosPlantilla($e);
    $s = pesosEstructura($e, $e['otra']);
    $migracion = require database_path('migrations/2026_10_28_000200_copy_grading_templates_to_sections.php');

    $migracion->up();
    $migracion->up();

    expect(DB::table('grading_components')->where('section_id', $e['seccion'])->orderBy('number')->pluck('weight_percent')->map(fn ($p) => (float) $p)->all())->toEqual([30.0, 30.0, 40.0])
        ->and(DB::table('grading_components')->where('section_id', $e['otra'])->count())->toBe(2)
        ->and(pesosDe('grading_components', $s['computos']))->toEqual([50.0, 50.0]);
});

/* ------------------------------------------------------------------ Permiso y alcance */

it('CP-RF17-01 el docente de la sección ve los pesos con su bloqueo y sus problemas', function () {
    $e = escenario();
    $s = pesosEstructura($e);
    actingAs($e['docente']);

    $datos = app(ComputoService::class)->pesos($e['docente'], $e['seccion']);

    expect($datos['computos']->pluck('id')->all())->toBe($s['computos'])
        ->and($datos['computos']->first()->items->pluck('id')->all())->toBe($s['actividades'][0])
        ->and($datos['bloqueo'])->toBeNull()
        ->and($datos['problemas'])->toBe([]);
});

it('CP-RF17-01 solo el docente de la sección cambia los pesos: docente ajeno, coordinador y estudiante reciben AuthorizationException', function (string $quien) {
    $e = escenario();
    $s = pesosEstructura($e);
    $actor = pesosUsuario($e, $quien);
    actingAs($actor);
    $servicio = app(ComputoService::class);

    expect(fn () => $servicio->guardarPesos($actor, $e['seccion'], [$s['computos'][0] => 40, $s['computos'][1] => 60], []))->toThrow(AuthorizationException::class)
        ->and(fn () => $servicio->pesos($actor, $e['seccion']))->toThrow(AuthorizationException::class)
        ->and(pesosDe('grading_components', $s['computos']))->toEqual([50.0, 50.0]);
})->with(['docente ajeno', 'coordinador', 'estudiante']);

it('CP-RF17-01 la pantalla de pesos guarda los pesos del docente', function () {
    $e = escenario();
    $s = pesosEstructura($e);
    actingAs($e['docente']);

    Livewire::test(PesosSeccion::class, ['seccion' => $e['ulid']])
        ->assertSet("computos.{$s['computos'][0]}", '50')
        ->set("computos.{$s['computos'][0]}", 40)->set("computos.{$s['computos'][1]}", 60)
        ->set("actividades.{$s['actividades'][0][0]}", 25)->set("actividades.{$s['actividades'][0][1]}", 75)
        ->call('guardar')->assertHasNoErrors()->assertDispatched('aviso', tipo: 'exito');

    expect(pesosDe('grading_components', $s['computos']))->toEqual([40.0, 60.0])
        ->and(pesosDe('grade_items', $s['actividades'][0]))->toEqual([25.0, 75.0]);
});

it('CP-RF17-01 la pantalla de pesos muestra el error del servicio y el de formato sin guardar', function () {
    $e = escenario();
    $s = pesosEstructura($e);
    actingAs($e['docente']);

    Livewire::test(PesosSeccion::class, ['seccion' => $e['ulid']])
        ->set("computos.{$s['computos'][0]}", 40)->call('guardar')->assertHasErrors(['computos'])
        ->set("computos.{$s['computos'][0]}", '')->call('guardar')->assertHasErrors(["computos.{$s['computos'][0]}"]);

    expect(pesosDe('grading_components', $s['computos']))->toEqual([50.0, 50.0]);
});

it('CP-RF17-01 la ruta de pesos abre para el docente de la sección', function () {
    $e = escenario();
    pesosEstructura($e);

    actingAs($e['docente'])->get(route('secciones.pesos', ['seccion' => $e['ulid']]))->assertOk()->assertSeeText(['Pesos de los cómputos', 'Actividad 1.1']);
});

it('CP-RF17-01 la ruta de pesos da 403 al estudiante, al coordinador y al docente ajeno', function (string $quien) {
    $e = escenario();
    pesosEstructura($e);

    actingAs(pesosUsuario($e, $quien))->get(route('secciones.pesos', ['seccion' => $e['ulid']]))->assertForbidden();
})->with(['estudiante', 'coordinador', 'docente ajeno']);

it('CP-RF17-01 el libro avisa al docente que los pesos no suman 100 % y enlaza a la pantalla de pesos', function () {
    $e = escenario();
    $s = pesosEstructura($e);
    DbContext::system(fn () => DB::table('grade_items')->where('id', $s['actividades'][0][0])->update(['weight_percent' => 20]));

    actingAs($e['docente'])->get(route('secciones.notas', ['seccion' => $e['ulid']]))->assertOk()
        ->assertSeeText(['Revise los pesos: no podrá publicar el acta hasta que sumen 100 %', 'Las actividades de Cómputo 1 suman 70 %: deben sumar 100 %.', 'Ajustar los pesos']);
});
