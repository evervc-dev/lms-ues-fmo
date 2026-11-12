<?php

use App\Modules\Calificaciones\Services\CalificacionService;
use App\Modules\Comunicacion\Services\ForoService;
use App\Modules\Contenidos\Livewire\ContenidosSeccion;
use App\Modules\Contenidos\Livewire\Progreso;
use App\Modules\Contenidos\Services\ProgresoService;
use App\Modules\Evaluaciones\Services\ActividadService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Un cómputo con su actividad y su item de nota en la sección indicada (sin publicar).
 *
 * @param  array<string, mixed>  $e
 * @return array{item: int, evaluacion: int}
 */
function evaluacionDeProgreso(array $e, string $tipo = 'quiz', ?int $seccion = null, string $titulo = 'Parcial 1'): array
{
    $seccion ??= $e['seccion'];

    return DbContext::system(function () use ($seccion, $tipo, $titulo) {
        $t = fn (array $fila) => $fila + ['created_at' => now(), 'updated_at' => now()];
        $computo = DB::table('grading_components')->insertGetId($t(['section_id' => $seccion, 'number' => 1, 'name' => 'Cómputo 1',
            'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11', 'weight_percent' => 100]));
        $item = DB::table('grade_items')->insertGetId($t(['component_id' => $computo, 'section_id' => $seccion, 'item_type' => $tipo,
            'title' => $titulo, 'weight_percent' => 100, 'is_published' => false]));
        $evaluacion = DB::table('assessments')->insertGetId($t(['ulid' => (string) str()->ulid(), 'section_id' => $seccion, 'grade_item_id' => $item,
            'assessment_type' => $tipo, 'title' => $titulo, 'opens_at' => now()->subDay(), 'closes_at' => now()->addDay(), 'max_attempts' => 2]));

        return ['item' => $item, 'evaluacion' => $evaluacion];
    });
}

/** Un hilo del foro abierto por el docente en la sección del escenario. */
function hiloDeProgreso(array $e): int
{
    return DbContext::system(fn () => DB::table('forum_threads')->insertGetId(['section_id' => $e['seccion'], 'author_id' => $e['docente']->id,
        'title' => 'Dudas de la unidad 1', 'is_closed' => false, 'is_pinned' => false, 'created_at' => now(), 'updated_at' => now()]));
}

/** Nota en borrador de la matrícula del escenario. */
function notaEnBorrador(array $e, int $item, float $nota): void
{
    DbContext::system(fn () => DB::table('grades')->updateOrInsert(
        ['grade_item_id' => $item, 'enrollment_id' => $e['matricula']],
        ['section_id' => $e['seccion'], 'score' => $nota, 'score_before_penalty' => $nota, 'status' => 'draft',
            'published_at' => null, 'created_at' => now(), 'updated_at' => now()],
    ));
}

/** ¿Está el recurso completado para la matrícula del escenario? */
function completado(array $e, int $recursoId): bool
{
    return DB::table('resource_progress')->where('enrollment_id', $e['matricula'])->where('resource_id', $recursoId)->exists();
}

/** Un recurso automático con su regla, en una unidad nueva de la sección. */
function recursoConRegla(array $e, array $regla, string $titulo = 'Lectura evaluada'): array
{
    return recursoEnUnidad(unidadDeContenidos($e['seccion'], $titulo.' · unidad'), [
        'title' => $titulo, 'completion_mode' => 'automatic', 'completion_rule' => json_encode($regla),
    ]);
}

it('CP-RF18-02 solo cuenta el recurso automático cuando se cumple la regla de nota mínima del cuestionario', function () {
    $e = escenario();
    $q = evaluacionDeProgreso($e);
    $recurso = recursoConRegla($e, ['type' => 'min_score', 'assessment_id' => $q['evaluacion'], 'min' => 6.0]);

    // Abrirlo no lo completa: la regla no es «al abrirlo».
    actingAs($e['estudiante'])->get(route('secciones.recurso', ['seccion' => $e['ulid'], 'recurso' => $recurso['ulid']]))->assertOk()
        ->assertSeeText('Se completa al obtener 6.0 o más');
    expect(completado($e, $recurso['id']))->toBeFalse();

    // Con la nota publicada por debajo del mínimo, sigue sin completarse.
    notaEnBorrador($e, $q['item'], 5.0);
    app(CalificacionService::class)->publicar($e['docente'], $q['item']);
    expect(completado($e, $recurso['id']))->toBeFalse();

    // Se corrige la nota a 8.0 y se vuelve a publicar: ahora la regla se cumple.
    DbContext::system(fn () => DB::table('grades')->where('grade_item_id', $q['item'])->update(['score' => 8.0]));
    app(CalificacionService::class)->publicar($e['docente'], $q['item']);

    expect(DB::table('resource_progress')->where('enrollment_id', $e['matricula'])->where('resource_id', $recurso['id'])->value('origin'))->toBe('rule');
});

it('CP-RF18-02 «ya lo leí» no completa un recurso con regla automática, aunque lo pida el navegador', function () {
    $e = escenario();
    $q = evaluacionDeProgreso($e);
    $recurso = recursoConRegla($e, ['type' => 'min_score', 'assessment_id' => $q['evaluacion'], 'min' => 6.0]);
    actingAs($e['estudiante']);

    // El id lo manda el cliente: el Service tiene que rechazar el recurso automático.
    Livewire::test(ContenidosSeccion::class, ['seccion' => $e['ulid']])->call('marcarLeido', $recurso['id'])
        ->assertHasErrors('recurso');
    expect(completado($e, $recurso['id']))->toBeFalse();

    // Un recurso manual de otra sección tampoco se completa desde aquí.
    $ajeno = recursoEnUnidad(unidadDeContenidos($e['otra'], 'Unidad de la otra sección'), ['title' => 'Lectura ajena']);
    expect(fn () => contenidos()->marcarLeido($e['estudiante'], $e['seccion'], $ajeno['id']))->toThrow(HttpException::class);
    expect(DB::table('resource_progress')->where('resource_id', $ajeno['id'])->exists())->toBeFalse();
});

it('CP-RF18-02 completa el recurso al escribir en el foro los mensajes que pide la regla', function () {
    $e = escenario();
    $hilo = hiloDeProgreso($e);
    $recurso = recursoConRegla($e, ['type' => 'forum_posts', 'thread_id' => $hilo, 'count' => 2], 'Participación');
    $foro = app(ForoService::class);

    $foro->responder($e['estudiante'], $e['seccion'], $hilo, 'Mi primera duda.');
    expect(completado($e, $recurso['id']))->toBeFalse();

    $foro->responder($e['estudiante'], $e['seccion'], $hilo, 'Y la segunda.');
    expect(completado($e, $recurso['id']))->toBeTrue();
});

it('CP-RF18-02 completa el recurso al entregar la tarea que pide la regla', function () {
    Storage::fake();
    $e = escenario();
    $tarea = evaluacionDeProgreso($e, 'assignment', titulo: 'Informe de laboratorio');
    DbContext::system(fn () => DB::table('assignment_settings')->insert(['assessment_id' => $tarea['evaluacion'], 'is_group' => false,
        'late_policy' => 'strict', 'allowed_extensions' => json_encode(['pdf']), 'max_file_mb' => 5]));
    $recurso = recursoConRegla($e, ['type' => 'submission', 'assessment_id' => $tarea['evaluacion']], 'Guía del informe');
    $ulid = DB::table('assessments')->where('id', $tarea['evaluacion'])->value('ulid');

    app(ActividadService::class)->entregar($e['estudiante'], $e['seccion'], $ulid, UploadedFile::fake()->create('informe.pdf', 120, 'application/pdf'));

    expect(completado($e, $recurso['id']))->toBeTrue();
});

it('CP-RF18-02 al fijar la regla completa a quien ya la cumplía y no desmarca lo completado', function () {
    $e = escenario();
    $q = evaluacionDeProgreso($e);
    notaEnBorrador($e, $q['item'], 9.0);
    app(CalificacionService::class)->publicar($e['docente'], $q['item']);
    $unidad = unidadDeContenidos($e['seccion']);

    // Se publica el recurso con la regla ya cumplida: cuenta de forma retroactiva.
    contenidos()->agregarRecurso($e['docente'], $unidad, ['tipo' => 'link', 'titulo' => 'Solucionario', 'url' => 'https://ues.edu.sv/solucionario',
        'completion' => 'automatic', 'regla' => ['type' => 'min_score', 'assessment_id' => $q['evaluacion'], 'min' => 6.0]]);
    $recurso = (int) DB::table('resources')->where('unit_id', $unidad)->where('title', 'Solucionario')->value('id');
    expect(completado($e, $recurso))->toBeTrue();

    // Se endurece la regla a una nota imposible: lo ya completado se queda completado.
    contenidos()->cambiarRegla($e['docente'], $e['seccion'], $recurso, 'automatic', ['type' => 'min_score', 'assessment_id' => $q['evaluacion'], 'min' => 10.0]);
    expect(completado($e, $recurso))->toBeTrue();
});

it('CP-RF18-02 rechaza una regla que no es de la sección o está fuera de rango, y exige el permiso de gestión', function () {
    $e = escenario();
    $propia = evaluacionDeProgreso($e);
    $ajena = evaluacionDeProgreso($e, 'quiz', $e['otra'], 'Parcial de otra sección');
    $progreso = app(ProgresoService::class);

    expect(fn () => $progreso->validarRegla($e['seccion'], ['type' => 'min_score', 'assessment_id' => $ajena['evaluacion'], 'min' => 6]))
        ->toThrow(ValidationException::class);
    expect(fn () => $progreso->validarRegla($e['seccion'], ['type' => 'min_score', 'assessment_id' => $propia['evaluacion'], 'min' => 11]))
        ->toThrow(ValidationException::class);
    expect(fn () => $progreso->validarRegla($e['seccion'], ['type' => 'forum_posts', 'thread_id' => hiloDeProgreso($e), 'count' => 50]))
        ->toThrow(ValidationException::class);
    expect(fn () => $progreso->validarRegla($e['seccion'], ['type' => 'lo-que-sea']))->toThrow(ValidationException::class);

    // La nota se normaliza a un decimal (ADR-067).
    expect($progreso->validarRegla($e['seccion'], ['type' => 'min_score', 'assessment_id' => $propia['evaluacion'], 'min' => '6.04']))
        ->toBe(['type' => 'min_score', 'assessment_id' => $propia['evaluacion'], 'min' => 6.0]);

    // Cambiar la regla es del docente de la sección: el estudiante no puede.
    $recurso = recursoConRegla($e, ['type' => 'opened']);
    expect(fn () => contenidos()->cambiarRegla($e['estudiante'], $e['seccion'], $recurso['id'], 'manual', null))
        ->toThrow(AuthorizationException::class);
});

it('CP-RF18-03 la pestaña Progreso muestra el avance por unidad con el alcance de cada rol', function () {
    $e = escenario();
    $primera = unidadDeContenidos($e['seccion'], 'Unidad 1 · Fundamentos');
    $segunda = unidadDeContenidos($e['seccion'], 'Unidad 2 · Recursividad', 2);
    $leido = recursoEnUnidad($primera, ['title' => 'Lectura 1']);
    recursoEnUnidad($primera, ['title' => 'Lectura 2', 'position' => 2]);
    recursoEnUnidad($segunda, ['title' => 'Lectura 3']);
    DbContext::system(fn () => DB::table('resource_progress')->insert(['resource_id' => $leido['id'], 'enrollment_id' => $e['matricula'], 'origin' => 'manual']));

    // El docente ve la fila de su estudiante: 50 % en la primera unidad, 0 % en la segunda y 33 % en total.
    actingAs($e['docente']);
    Livewire::test(Progreso::class, ['seccion' => $e['ulid']])
        ->assertSee('Unidad 1 · Fundamentos')->assertSee($e['estudiante']->last_name)
        ->assertSeeHtml('aria-valuenow="50"')->assertSeeHtml('aria-valuenow="0"')->assertSeeHtml('aria-valuenow="33"');

    // El Coordinador de la carrera también (alcance C).
    actingAs($e['coordinador']);
    Livewire::test(Progreso::class, ['seccion' => $e['ulid']])->assertSee($e['estudiante']->last_name);

    // El estudiante solo su fila (alcance P).
    $otro = usuarioCon('estudiante');
    DbContext::system(fn () => DB::table('enrollments')->insert(['student_id' => $otro->id, 'section_id' => $e['seccion'], 'period_id' => $e['periodo'],
        'subject_id' => $e['prn'], 'status' => 'active', 'enrolled_via' => 'key', 'enrolled_by' => $otro->id, 'created_at' => now(), 'updated_at' => now()]));
    actingAs($e['estudiante']);
    Livewire::test(Progreso::class, ['seccion' => $e['ulid']])->assertSee($e['estudiante']->last_name)->assertDontSee($otro->last_name);
});

it('CP-RF18-03 niega la pestaña Progreso a un docente de otra sección', function () {
    $e = escenario();
    unidadDeContenidos($e['seccion']);
    actingAs(docenteAjeno($e));

    Livewire::test(Progreso::class, ['seccion' => $e['ulid']])->assertForbidden();
});
