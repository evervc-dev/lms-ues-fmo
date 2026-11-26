<?php

use App\Modules\Contenidos\Livewire\NuevoRecurso;
use App\Modules\Contenidos\Livewire\Recurso;
use App\Modules\Evaluaciones\Livewire\Cuestionario;
use App\Modules\Evaluaciones\Livewire\NuevoCuestionario;
use App\Modules\Evaluaciones\Services\ActividadService;
use App\Modules\Evaluaciones\Services\CuestionarioService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
| Lección con cuestionario (RF38, ADR-091): el material (archivo o página) y su cuestionario en la misma página. El
| estudiante completa la lectura (tiempo mínimo y, si se exige, llegar al final), pulsa «Ya lo leí» y solo entonces
| intenta el cuestionario, que se abre en modo examen.
*/

/**
 * Un cómputo y un banco de 2 preguntas V/F del docente: lo que necesita el cuestionario de una lección.
 *
 * @return array{componente: int, banco: int}
 */
function preparacionDeLeccion(array $e): array
{
    $componente = DbContext::system(fn () => DB::table('grading_components')->insertGetId([
        'section_id' => $e['seccion'], 'number' => 1, 'name' => 'Cómputo 1', 'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11', 'weight_percent' => 100, 'created_at' => now(), 'updated_at' => now(),
    ]));
    actingAs($e['docente']);
    $cuestionarios = app(CuestionarioService::class);
    $banco = $cuestionarios->crearBanco($e['docente'], 'Banco PRN', $e['prn']);
    $cuestionarios->agregarPregunta($e['docente'], $banco, ['tipo' => 'true_false', 'enunciado' => 'PHP es interpretado', 'correcta' => 0]);
    $cuestionarios->agregarPregunta($e['docente'], $banco, ['tipo' => 'true_false', 'enunciado' => 'Laravel es un lenguaje', 'correcta' => 1]);

    return ['componente' => $componente, 'banco' => $banco];
}

/**
 * @return array<string, mixed>
 */
function datosDelCuestionarioDeLeccion(array $preparacion): array
{
    return ['titulo' => 'Lección 1', 'instrucciones' => null,
        'abre' => now()->subHour()->format('Y-m-d H:i'), 'cierra' => now()->addDay()->format('Y-m-d H:i'), 'minutos' => 15, 'intentos' => 1,
        'navegacion' => 'free', 'banco' => $preparacion['banco'], 'cantidad' => 2, 'tema' => null];
}

/**
 * Lección de tipo página publicada por el docente, con lectura de 2 minutos.
 *
 * @return array{recurso: object, cuestionario: string}
 */
function leccionPublicada(array $e, bool $exigeFinal = false): array
{
    $preparacion = preparacionDeLeccion($e);
    contenidos()->agregarRecurso($e['docente'], unidadDeContenidos($e['seccion']), [
        'tipo' => 'page', 'titulo' => 'Lección 1', 'texto' => '<p>Normalización de bases de datos.</p>',
        'regla' => ['type' => 'read', 'min_seconds' => 120, 'to_end' => $exigeFinal],
        'cuestionario' => datosDelCuestionarioDeLeccion($preparacion),
    ]);
    $recurso = DB::table('resources')->where('title', 'Lección 1')->first();

    return ['recurso' => $recurso, 'cuestionario' => (string) DB::table('assessments')->where('title', 'Lección 1')->value('ulid')];
}

/** Segundos con la pestaña visible, enviados como lo hace la página (latidos de hasta 35 s). */
function leerDurante(array $e, string $ulid, int $segundos): void
{
    for (; $segundos > 0; $segundos -= 30) {
        contenidos()->registrarTiempo($e['estudiante'], $e['seccion'], $ulid, min(30, $segundos));
    }
}

it('CP-RF38-01 el docente crea la lección con su material y su cuestionario en un solo asistente', function () {
    $e = escenario();
    $preparacion = preparacionDeLeccion($e);
    $unidad = unidadDeContenidos($e['seccion']);

    Livewire::test(NuevoRecurso::class, ['seccion' => $e['ulid'], 'unidad' => $unidad])
        ->set('tipo', 'page')->set('tituloRecurso', 'Lección 1')->set('texto', '<p>Normalización.</p>')
        ->set('leccion', true)->set('lecturaMinutos', 3)->set('lecturaFinal', true)
        ->set('cuestionario.banco', $preparacion['banco'])
        ->set('cuestionario.cantidad', 2)->set('cuestionario.abre', now()->subHour()->format('Y-m-d\TH:i'))->set('cuestionario.cierra', now()->addDay()->format('Y-m-d\TH:i'))
        ->call('agregar')->assertHasNoErrors();

    $recurso = DB::table('resources')->where('title', 'Lección 1')->sole();
    $cuestionario = DB::table('assessments')->where('title', 'Lección 1')->sole();
    expect(json_decode($recurso->completion_rule, true))->toBe(['type' => 'read', 'min_seconds' => 180, 'to_end' => true])
        ->and($recurso->completion_mode)->toBe('automatic')
        ->and($cuestionario->assessment_type)->toBe('quiz')
        ->and((int) DB::table('quiz_settings')->where('assessment_id', $cuestionario->id)->value('required_resource_id'))->toBe((int) $recurso->id);
});

it('CP-RF38-01 no deja ni la lección ni el cuestionario si el cuestionario se rechaza', function () {
    $e = escenario();
    $preparacion = preparacionDeLeccion($e);

    expect(fn () => contenidos()->agregarRecurso($e['docente'], unidadDeContenidos($e['seccion']), [
        'tipo' => 'page', 'titulo' => 'Lección 1', 'texto' => '<p>Texto.</p>', 'regla' => ['type' => 'read', 'min_seconds' => 120, 'to_end' => false],
        'cuestionario' => ['cantidad' => 9] + datosDelCuestionarioDeLeccion($preparacion),
    ]))->toThrow(ValidationException::class);

    expect(DB::table('resources')->where('title', 'Lección 1')->exists())->toBeFalse()
        ->and(DB::table('assessments')->where('title', 'Lección 1')->exists())->toBeFalse();
});

it('CP-RF38-02 el estudiante ve el material y el cuestionario en la misma página, con el cuestionario bloqueado', function () {
    $e = escenario();
    $leccion = leccionPublicada($e);
    actingAs($e['estudiante']);

    Livewire::test(Recurso::class, ['seccion' => $e['ulid'], 'recurso' => $leccion['recurso']->ulid])
        ->assertSee('Normalización de bases de datos.')->assertSee('Verificación de lectura')
        ->assertSee('Intentar cuestionario')->assertSee('Primero complete la lectura');
});

it('CP-RF38-03 rechaza «Ya lo leí» sin el tiempo mínimo y lo acepta al cumplirlo', function () {
    $e = escenario();
    $leccion = leccionPublicada($e);
    actingAs($e['estudiante']);

    leerDurante($e, $leccion['recurso']->ulid, 60);
    expect(fn () => contenidos()->marcarLeido($e['estudiante'], $e['seccion'], (int) $leccion['recurso']->id))->toThrow(ValidationException::class);

    leerDurante($e, $leccion['recurso']->ulid, 60);
    contenidos()->marcarLeido($e['estudiante'], $e['seccion'], (int) $leccion['recurso']->id);
    expect(DB::table('resource_progress')->where('resource_id', $leccion['recurso']->id)->value('origin'))->toBe('rule');
});

it('CP-RF38-04 si se exige llegar al final, el tiempo no basta hasta que el estudiante llega al final de la página', function () {
    $e = escenario();
    $leccion = leccionPublicada($e, exigeFinal: true);
    actingAs($e['estudiante']);
    leerDurante($e, $leccion['recurso']->ulid, 150);

    expect(fn () => contenidos()->marcarLeido($e['estudiante'], $e['seccion'], (int) $leccion['recurso']->id))->toThrow(ValidationException::class);

    Livewire::test(Recurso::class, ['seccion' => $e['ulid'], 'recurso' => $leccion['recurso']->ulid])
        ->call('llegoAlFinal')->call('llegoAlFinal')->call('marcarLeido')->assertDispatched('aviso', tipo: 'exito');
    expect(DB::table('resource_access_logs')->where('resource_id', $leccion['recurso']->id)->where('event_type', 'end')->count())->toBe(1);
});

it('CP-RF38-04 en un archivo que solo se descarga no se exige llegar al final: basta el tiempo', function () {
    Storage::fake();
    $e = escenario();
    $recurso = recursoEnUnidad(unidadDeContenidos($e['seccion']), ['resource_type' => 'file', 'file_path' => 'contenidos/1/guia.docx',
        'completion_mode' => 'automatic', 'completion_rule' => json_encode(['type' => 'read', 'min_seconds' => 60, 'to_end' => true])]);
    actingAs($e['estudiante']);
    leerDurante($e, $recurso['ulid'], 60);

    contenidos()->marcarLeido($e['estudiante'], $e['seccion'], $recurso['id']);

    expect(DB::table('resource_progress')->where('resource_id', $recurso['id'])->exists())->toBeTrue();
});

it('CP-RF38-05 rechaza iniciar el cuestionario sin completar la lectura, aunque se llame directo al servicio', function () {
    $e = escenario();
    $leccion = leccionPublicada($e);
    actingAs($e['estudiante']);

    expect(fn () => app(CuestionarioService::class)->iniciar($e['estudiante'], $e['seccion'], $leccion['cuestionario']))
        ->toThrow(ValidationException::class, 'Primero complete la lectura de la lección.');
    expect(DB::table('quiz_attempts')->exists())->toBeFalse();
});

it('CP-RF38-05 lleva al estudiante a la lección cuando abre el cuestionario por otro enlace; el docente sigue en el cuestionario', function () {
    $e = escenario();
    $leccion = leccionPublicada($e);

    actingAs($e['estudiante']);
    Livewire::test(Cuestionario::class, ['seccion' => $e['ulid'], 'cuestionario' => $leccion['cuestionario']])
        ->assertRedirect(route('secciones.recurso', ['seccion' => $e['ulid'], 'recurso' => $leccion['recurso']->ulid]));

    actingAs($e['docente']);
    Livewire::test(Cuestionario::class, ['seccion' => $e['ulid'], 'cuestionario' => $leccion['cuestionario']])
        ->assertNoRedirect()->assertSee('Ver la lección');
});

it('CP-RF38-06 con la lectura completa, pide confirmación y abre el intento en modo examen', function () {
    $e = escenario();
    $leccion = leccionPublicada($e);
    actingAs($e['estudiante']);
    leerDurante($e, $leccion['recurso']->ulid, 120);

    $pagina = Livewire::test(Recurso::class, ['seccion' => $e['ulid'], 'recurso' => $leccion['recurso']->ulid])
        ->call('marcarLeido')->assertSee('¿Está seguro de intentar el cuestionario?');
    $pagina->call('comenzarCuestionario');

    $intento = DB::table('quiz_attempts')->sole();
    $pagina->assertRedirect(route('intentos.show', ['intento' => $intento->ulid]));
    expect($intento->status)->toBe('in_progress');
});

it('CP-RF38-07 impide crear la lección a un docente fuera de la sección', function () {
    $e = escenario();
    $preparacion = preparacionDeLeccion($e);
    $ajeno = docenteAjeno($e);

    expect(fn () => contenidos()->agregarRecurso($ajeno, unidadDeContenidos($e['seccion']), [
        'tipo' => 'page', 'titulo' => 'Lección 1', 'texto' => '<p>Texto.</p>', 'regla' => ['type' => 'read', 'min_seconds' => 120, 'to_end' => false],
        'cuestionario' => datosDelCuestionarioDeLeccion($preparacion),
    ]))->toThrow(AuthorizationException::class);
});

it('CP-RF38-07 impide marcar la lectura a quien no está matriculado en la sección', function () {
    $e = escenario();
    $leccion = leccionPublicada($e);
    $otro = usuarioCon('estudiante');

    expect(fn () => contenidos()->marcarLeido($otro, $e['seccion'], (int) $leccion['recurso']->id))->toThrow(AuthorizationException::class);
});

it('CP-RF38-08 el cuestionario de la lección es una verificación de lectura: no tiene cómputo, peso ni ítem de calificación', function () {
    $e = escenario();
    $leccion = leccionPublicada($e);

    $cuestionario = DB::table('assessments')->where('ulid', $leccion['cuestionario'])->sole();
    expect($cuestionario->grade_item_id)->toBeNull()
        ->and((bool) DB::table('quiz_settings')->where('assessment_id', $cuestionario->id)->value('is_reading_check'))->toBeTrue()
        ->and(DB::table('grade_items')->where('section_id', $e['seccion'])->count())->toBe(0)
        // No es una evaluación copiada por programar (RF14): no se ofrece darle cómputo.
        ->and(app(ActividadService::class)->sinProgramar($e['docente'], $e['seccion']))->toBeEmpty();
});

it('CP-RF38-08 el resultado del intento no entra a la nota: el estudiante lo ve como retroalimentación', function () {
    $e = escenario();
    $leccion = leccionPublicada($e);
    actingAs($e['estudiante']);
    leerDurante($e, $leccion['recurso']->ulid, 120);
    contenidos()->marcarLeido($e['estudiante'], $e['seccion'], (int) $leccion['recurso']->id);
    $cuestionarios = app(CuestionarioService::class);

    $intento = $cuestionarios->iniciar($e['estudiante'], $e['seccion'], $leccion['cuestionario']);
    $cuestionarios->terminar($e['estudiante'], $intento);

    $guardado = DB::table('quiz_attempts')->where('ulid', $intento)->sole();
    expect($guardado->status)->toBe('graded')
        ->and($guardado->score)->not->toBeNull()
        ->and(DB::table('grades')->count())->toBe(0);
    Livewire::test(Recurso::class, ['seccion' => $e['ulid'], 'recurso' => $leccion['recurso']->ulid])
        ->assertSee('No suma a su nota.')->assertSee('Resultado: 0.0 de 10');
});

it('CP-RF38-08 la verificación de lectura no admite preguntas de ensayo', function () {
    $e = escenario();
    $preparacion = preparacionDeLeccion($e);
    app(CuestionarioService::class)->agregarPregunta($e['docente'], $preparacion['banco'], ['tipo' => 'essay', 'enunciado' => 'Explique la 3FN']);

    expect(fn () => contenidos()->agregarRecurso($e['docente'], unidadDeContenidos($e['seccion']), [
        'tipo' => 'page', 'titulo' => 'Lección 1', 'texto' => '<p>Texto.</p>', 'regla' => ['type' => 'read', 'min_seconds' => 120, 'to_end' => false],
        'cuestionario' => datosDelCuestionarioDeLeccion($preparacion),
    ]))->toThrow(ValidationException::class);
    expect(DB::table('assessments')->where('title', 'Lección 1')->exists())->toBeFalse();
});

it('CP-RF38-08 al editar la verificación de lectura no se piden cómputo ni peso', function () {
    $e = escenario();
    $leccion = leccionPublicada($e);
    actingAs($e['docente']);

    Livewire::test(NuevoCuestionario::class, ['seccion' => $e['ulid'], 'cuestionario' => $leccion['cuestionario']])
        ->assertSet('verificacion', true)
        ->assertSee('no suma a la nota, así que no lleva cómputo ni peso')
        ->set('titulo', 'Lección 1 (revisada)')->call('guardar')->assertHasNoErrors();

    expect(DB::table('assessments')->where('ulid', $leccion['cuestionario'])->value('title'))->toBe('Lección 1 (revisada)')
        ->and(DB::table('assessments')->where('ulid', $leccion['cuestionario'])->value('grade_item_id'))->toBeNull();
});
