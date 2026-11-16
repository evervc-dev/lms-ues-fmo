<?php

use App\Modules\Calificaciones\Events\RiesgoAcademicoDetectado;
use App\Modules\Calificaciones\Jobs\EvaluarRiesgoAcademico;
use App\Modules\Calificaciones\Services\CalificacionService;
use App\Modules\Calificaciones\Services\RevisionService;
use App\Modules\Calificaciones\Services\RiesgoService;
use App\Modules\Comunicacion\Notifications\Aviso;
use App\Modules\Evaluaciones\Livewire\NuevaTarea;
use App\Modules\Evaluaciones\Livewire\Tarea;
use App\Modules\Evaluaciones\Services\ActividadService;
use App\Modules\Evaluaciones\Services\CuestionarioService;
use App\Modules\Reportes\Services\PanelService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake());

/*
| Riesgo académico (RF17, CP-RF17-05; Flujo Cálculo de nota final «en vivo»): con las notas publicadas, si la nota
| proyectada baja de la mínima (6.0) se abre una alerta `grade_risk` sin cómputo, se avisa una vez al estudiante y el
| docente lo ve en su panel; al subir a la mínima o más, la alerta se cierra. Las actividades sin publicar no cuentan.
| El job EvaluarRiesgoAcademico (cola default) se encola al publicar, al calificar una actividad ya publicada (directa,
| entrega o cuestionario) y al resolver una revisión con cambios.
*/

/**
 * Dos cómputos de 50 %, cada uno con una actividad manual del 100 %, sin publicar.
 *
 * @param  array<string, mixed>  $e
 * @return array<int, int> id de la actividad de cada cómputo
 */
function riesgoActividades(array $e): array
{
    return DbContext::system(fn () => collect([1, 2])->map(function (int $n) use ($e) {
        $computo = DB::table('grading_components')->insertGetId([
            'section_id' => $e['seccion'], 'number' => $n, 'name' => "Cómputo {$n}", 'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11',
            'weight_percent' => 50, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('grade_items')->insertGetId([
            'component_id' => $computo, 'section_id' => $e['seccion'], 'item_type' => 'manual', 'title' => "Parcial {$n}",
            'weight_percent' => 100, 'is_published' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
    })->all());
}

/**
 * El docente califica la matrícula del escenario y publica la actividad.
 *
 * @param  array<string, mixed>  $e
 */
function riesgoPublicar(array $e, int $actividad, float $nota): void
{
    actingAs($e['docente']);
    app(CalificacionService::class)->calificar($e['docente'], $actividad, $e['matricula'], $nota);
    app(CalificacionService::class)->publicar($e['docente'], $actividad);
}

/**
 * @param  array<string, mixed>  $e
 * @return Collection<int, object>
 */
function riesgoAlertas(array $e, ?int $matricula = null): Collection
{
    return DB::table('academic_alerts')->where('enrollment_id', $matricula ?? $e['matricula'])->where('alert_type', 'grade_risk')->orderBy('id')->get();
}

/**
 * @param  array<string, mixed>  $e
 */
function riesgoAvisos(array $e): int
{
    return Notification::sent($e['estudiante'], Aviso::class)->filter(fn (Aviso $aviso) => $aviso->evento === 'academic_alert.opened')->count();
}

function riesgoComputo(array $e): int
{
    return DbContext::system(fn () => DB::table('grading_components')->insertGetId([
        'section_id' => $e['seccion'], 'number' => 1, 'name' => 'Cómputo 1', 'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11',
        'weight_percent' => 100, 'created_at' => now(), 'updated_at' => now(),
    ]));
}

/**
 * Tarea (sin rúbrica) con una entrega del estudiante, sin calificar.
 *
 * @param  array<string, mixed>  $e
 * @return array{ulid: string, entrega: int, actividad: int}
 */
function riesgoTareaEntregada(array $e): array
{
    actingAs($e['docente']);
    Livewire::test(NuevaTarea::class, ['seccion' => $e['ulid']])->set('titulo', 'Informe')->set('componente', riesgoComputo($e))->set('peso', 100)
        ->set('abre', now()->subDay()->format('Y-m-d\TH:i'))->set('cierra', now()->addDay()->format('Y-m-d\TH:i'))->set('extensiones', 'pdf')
        ->call('guardar')->assertHasNoErrors();
    $tarea = DB::table('assessments')->where('title', 'Informe')->first(['ulid', 'grade_item_id']);

    actingAs($e['estudiante']);
    Livewire::test(Tarea::class, ['seccion' => $e['ulid'], 'tarea' => $tarea->ulid])->set('archivo', UploadedFile::fake()->create('informe.pdf', 50))->call('entregar')->assertHasNoErrors();

    return ['ulid' => (string) $tarea->ulid, 'entrega' => (int) DB::table('submissions')->value('id'), 'actividad' => (int) $tarea->grade_item_id];
}

/**
 * Cuestionario de dos preguntas V/F que el estudiante empezó y respondió «Verdadero» en ambas (acierta una: 5),
 * sin terminar el intento.
 *
 * @param  array<string, mixed>  $e
 * @return array{ulid: string, intento: string, actividad: int, respuestas: array<int, int>}
 */
function riesgoCuestionarioRespondido(array $e): array
{
    $cuestionarios = app(CuestionarioService::class);
    actingAs($e['docente']);
    $banco = $cuestionarios->crearBanco($e['docente'], 'Banco PRN', $e['prn']);
    $cuestionarios->agregarPregunta($e['docente'], $banco, ['tipo' => 'true_false', 'enunciado' => 'PHP es interpretado', 'correcta' => 0]);
    $cuestionarios->agregarPregunta($e['docente'], $banco, ['tipo' => 'true_false', 'enunciado' => 'Laravel es un lenguaje', 'correcta' => 1]);
    $ulid = $cuestionarios->crearCuestionario($e['docente'], $e['seccion'], ['titulo' => 'Parcial 1', 'instrucciones' => null, 'componente' => riesgoComputo($e), 'peso' => 100,
        'abre' => now()->subHour(), 'cierra' => now()->addHour(), 'intentos' => 1, 'minutos' => 30, 'navegacion' => 'free', 'banco' => $banco, 'cantidad' => 2, 'tema' => null, 'dificultad' => null]);

    actingAs($e['estudiante']);
    $intento = $cuestionarios->iniciar($e['estudiante'], $e['seccion'], $ulid);
    $respuestas = DB::table('attempt_answers as r')->join('questions as p', 'p.id', '=', 'r.question_id')->orderBy('p.id')->pluck('r.id')->map(fn ($id) => (int) $id)->all();
    foreach ($respuestas as $respuesta) {
        $verdadero = DB::table('question_options')->where('question_id', DB::table('attempt_answers')->where('id', $respuesta)->value('question_id'))->where('text', 'Verdadero')->value('id');
        $cuestionarios->guardarRespuesta($e['estudiante'], $intento, $respuesta, (int) $verdadero, null);
    }

    return ['ulid' => $ulid, 'intento' => $intento, 'actividad' => (int) DB::table('assessments')->where('ulid', $ulid)->value('grade_item_id'), 'respuestas' => $respuestas];
}

/**
 * Cuestionario terminado (nota 5), cerrado hace más de la demora de revisión (ADR-040) y con la nota publicada; el
 * estudiante pide revisión de la segunda pregunta. Devuelve el ulid de la solicitud y el id de su ítem.
 *
 * @param  array<string, mixed>  $e
 * @return array{solicitud: string, item: int}
 */
function riesgoRevisionPedida(array $e): array
{
    $c = riesgoCuestionarioRespondido($e);
    app(CuestionarioService::class)->terminar($e['estudiante'], $c['intento']);
    $cierre = now()->subMinutes(CuestionarioService::demoraRevision() + 1);
    DbContext::system(function () use ($c, $cierre) {
        $id = DB::table('assessments')->where('ulid', $c['ulid'])->value('id');
        DB::table('assessments')->where('id', $id)->update(['closes_at' => $cierre]);
        DB::table('quiz_attempts')->where('assessment_id', $id)->update(['deadline_at' => $cierre]);
    });
    actingAs($e['docente']);
    app(CalificacionService::class)->publicar($e['docente'], $c['actividad']);

    actingAs($e['estudiante']);
    $solicitud = app(RevisionService::class)->solicitar($e['estudiante'], $e['seccion'], $c['ulid'], 'La pregunta 2 es ambigua, revísela.', ['pregunta-'.$c['respuestas'][1] => 'Laravel está escrito en PHP.']);

    return ['solicitud' => $solicitud, 'item' => (int) DB::table('grade_review_items')->value('id')];
}

/* ------------------------------------------------------------------ Apertura, aviso y cierre */

it('CP-RF17-05 al publicar una nota bajo la mínima abre la alerta grade_risk sin cómputo y avisa una vez al estudiante', function () {
    Notification::fake();
    $e = escenario();
    [$parcial1] = riesgoActividades($e);

    riesgoPublicar($e, $parcial1, 4);

    $alertas = riesgoAlertas($e);
    expect($alertas)->toHaveCount(1)
        ->and($alertas->first()->grading_component_id)->toBeNull()
        ->and($alertas->first()->notified_at)->not->toBeNull()
        ->and($alertas->first()->closed_at)->toBeNull()
        ->and(riesgoAvisos($e))->toBe(1);
    Notification::assertSentTo($e['estudiante'], Aviso::class, fn (Aviso $aviso) => $aviso->evento === 'academic_alert.opened'
        && $aviso->titulo === 'Riesgo de reprobar: PRN115'
        && str_contains($aviso->cuerpo, 'su nota proyectada en Programación I es 4.0 y se aprueba con 6.0')
        && $aviso->queue === 'notificaciones');
    Notification::assertNotSentTo($e['docente'], Aviso::class, fn (Aviso $aviso) => $aviso->evento === 'academic_alert.opened');
});

it('CP-RF17-05 de punta a punta por calificar(): la nota publicada abre la alerta y la nota corregida a la mínima la cierra', function () {
    Notification::fake();
    $e = escenario();
    [$parcial1] = riesgoActividades($e);
    riesgoPublicar($e, $parcial1, 4);
    expect(riesgoAlertas($e)->whereNull('closed_at'))->toHaveCount(1);

    // La actividad ya está publicada: recalificarla reevalúa el riesgo sin volver a publicar.
    app(CalificacionService::class)->calificar($e['docente'], $parcial1, $e['matricula'], 6);

    $alertas = riesgoAlertas($e);
    expect($alertas)->toHaveCount(1)
        ->and($alertas->first()->closed_at)->not->toBeNull()
        ->and(riesgoAvisos($e))->toBe(1);
});

it('CP-RF17-05 evaluar dos veces con las mismas notas no duplica la alerta ni el aviso', function () {
    Notification::fake();
    $e = escenario();
    [$parcial1] = riesgoActividades($e);
    riesgoPublicar($e, $parcial1, 3);

    $nuevas = app(RiesgoService::class)->evaluarSeccion($e['seccion']);

    expect($nuevas)->toBe(0)
        ->and(riesgoAlertas($e))->toHaveCount(1)
        ->and(riesgoAvisos($e))->toBe(1);
});

it('CP-RF17-05 con la nota en la mínima o más no abre ninguna alerta', function () {
    Notification::fake();
    $e = escenario();
    [$parcial1] = riesgoActividades($e);

    riesgoPublicar($e, $parcial1, 6);

    expect(riesgoAlertas($e))->toBeEmpty()->and(riesgoAvisos($e))->toBe(0);
});

it('CP-RF17-05 sin ninguna actividad publicada no evalúa: una nota en borrador bajo la mínima no abre alerta', function () {
    $e = escenario();
    [$parcial1] = riesgoActividades($e);
    actingAs($e['docente']);
    app(CalificacionService::class)->calificar($e['docente'], $parcial1, $e['matricula'], 2);

    expect(app(RiesgoService::class)->evaluarSeccion($e['seccion']))->toBe(0)
        ->and(riesgoAlertas($e))->toBeEmpty();
});

it('CP-RF17-05 las actividades sin publicar no cuentan: con el cómputo 1 en 4.0 y el 2 sin publicar la proyectada es 4.0', function () {
    $e = escenario();
    [$parcial1] = riesgoActividades($e);
    riesgoPublicar($e, $parcial1, 4);
    Event::fake([RiesgoAcademicoDetectado::class]);
    DbContext::system(fn () => DB::table('academic_alerts')->delete());

    app(RiesgoService::class)->evaluarSeccion($e['seccion']);

    Event::assertDispatched(RiesgoAcademicoDetectado::class, fn (RiesgoAcademicoDetectado $evento) => $evento->notaProyectada === 4.0);
    expect(riesgoAlertas($e))->toHaveCount(1);
});

it('CP-RF17-05 las actividades sin publicar no cuentan como 0: con el cómputo 1 en 8.0 y el 2 sin publicar no hay riesgo', function () {
    $e = escenario();
    [$parcial1] = riesgoActividades($e);

    // Contando el cómputo 2 como 0 la nota sería 4.0 y abriría la alerta.
    riesgoPublicar($e, $parcial1, 8);

    expect(riesgoAlertas($e))->toBeEmpty();
});

it('CP-RF17-05 una actividad publicada sin nota cuenta 0 en la proyectada', function () {
    $e = escenario();
    [$parcial1] = riesgoActividades($e);
    actingAs($e['docente']);

    app(CalificacionService::class)->publicar($e['docente'], $parcial1);

    expect(riesgoAlertas($e))->toHaveCount(1);
});

it('CP-RF17-05 solo evalúa matrículas activas: una matrícula retirada con nota baja no abre alerta', function () {
    $e = escenario();
    [$parcial1] = riesgoActividades($e);
    riesgoPublicar($e, $parcial1, 2);
    DbContext::system(function () use ($e) {
        DB::table('academic_alerts')->delete();
        DB::table('enrollments')->where('id', $e['matricula'])->update(['status' => 'withdrawn']);
    });

    expect(app(RiesgoService::class)->evaluarSeccion($e['seccion']))->toBe(0)
        ->and(riesgoAlertas($e))->toBeEmpty();
});

/* ------------------------------------------------------------------ Panel */

it('CP-RF17-05 el estudiante en riesgo aparece en el panel del docente y cuenta en el del Coordinador', function () {
    $e = escenario();
    [$parcial1] = riesgoActividades($e);

    riesgoPublicar($e, $parcial1, 4);

    $panel = app(PanelService::class)->docente($e['docente']);
    expect($panel['alertas']->pluck('alert_type')->all())->toBe(['grade_risk'])
        ->and($panel['alertas']->first()->last_name)->toBe($e['estudiante']->last_name)
        ->and($panel['alertas']->first()->code)->toBe('PRN115')
        ->and(app(PanelService::class)->coordinador($e['coordinador'])['riesgo'])->toBe(1);

    actingAs($e['docente'])->get(route('inicio'))->assertOk()->assertSeeText(['Riesgo de reprobar', $e['estudiante']->last_name]);
});

it('CP-RF17-05 el docente de otra sección no ve la alerta en su panel', function () {
    $e = escenario();
    [$parcial1] = riesgoActividades($e);
    riesgoPublicar($e, $parcial1, 4);

    expect(app(PanelService::class)->docente(docenteAjeno($e))['alertas'])->toBeEmpty();
});

/* ------------------------------------------------------------------ Cola y caminos que reevalúan */

it('CP-RF17-05 al publicar las notas encola EvaluarRiesgoAcademico de la sección en la cola default', function () {
    $e = escenario();
    [$parcial1] = riesgoActividades($e);
    actingAs($e['docente']);
    app(CalificacionService::class)->calificar($e['docente'], $parcial1, $e['matricula'], 4);
    Queue::fake();

    app(CalificacionService::class)->publicar($e['docente'], $parcial1);

    Queue::assertPushedOn('default', EvaluarRiesgoAcademico::class, fn (EvaluarRiesgoAcademico $job) => $job->seccionId === $e['seccion']);
    expect(riesgoAlertas($e))->toBeEmpty();
});

it('CP-RF17-05 calificar() una actividad publicada encola la reevaluación; en una sin publicar, no', function (bool $publicada) {
    $e = escenario();
    [$parcial1] = riesgoActividades($e);
    actingAs($e['docente']);
    if ($publicada) {
        app(CalificacionService::class)->publicar($e['docente'], $parcial1);
    }
    Bus::fake([EvaluarRiesgoAcademico::class]);

    app(CalificacionService::class)->calificar($e['docente'], $parcial1, $e['matricula'], 4);

    $publicada
        ? Bus::assertDispatched(EvaluarRiesgoAcademico::class, fn (EvaluarRiesgoAcademico $job) => $job->seccionId === $e['seccion'])
        : Bus::assertNotDispatched(EvaluarRiesgoAcademico::class);
})->with(['publicada' => [true], 'sin publicar' => [false]]);

it('CP-RF17-05 calificar la entrega de una tarea publicada encola la reevaluación; de una sin publicar, no', function (bool $publicada) {
    $e = escenario();
    $t = riesgoTareaEntregada($e);
    actingAs($e['docente']);
    if ($publicada) {
        app(CalificacionService::class)->publicar($e['docente'], $t['actividad']);
    }
    Bus::fake([EvaluarRiesgoAcademico::class]);

    app(ActividadService::class)->calificarEntrega($e['docente'], $e['seccion'], $t['entrega'], 4, [], null);

    $publicada
        ? Bus::assertDispatched(EvaluarRiesgoAcademico::class, fn (EvaluarRiesgoAcademico $job) => $job->seccionId === $e['seccion'])
        : Bus::assertNotDispatched(EvaluarRiesgoAcademico::class);
})->with(['publicada' => [true], 'sin publicar' => [false]]);

it('CP-RF17-05 terminar un cuestionario ya publicado encola la reevaluación; uno sin publicar, no', function (bool $publicada) {
    $e = escenario();
    $c = riesgoCuestionarioRespondido($e);
    if ($publicada) {
        actingAs($e['docente']);
        app(CalificacionService::class)->publicar($e['docente'], $c['actividad']);
    }
    Bus::fake([EvaluarRiesgoAcademico::class]);

    actingAs($e['estudiante']);
    app(CuestionarioService::class)->terminar($e['estudiante'], $c['intento']);

    expect(DB::table('grades')->where('enrollment_id', $e['matricula'])->value('status'))->toBe($publicada ? 'published' : 'draft');
    $publicada
        ? Bus::assertDispatched(EvaluarRiesgoAcademico::class, fn (EvaluarRiesgoAcademico $job) => $job->seccionId === $e['seccion'])
        : Bus::assertNotDispatched(EvaluarRiesgoAcademico::class);
})->with(['publicada' => [true], 'sin publicar' => [false]]);

it('CP-RF17-05 de punta a punta por el cuestionario: terminar con la actividad publicada y nota 5 abre la alerta', function () {
    Notification::fake();
    $e = escenario();
    $c = riesgoCuestionarioRespondido($e);
    actingAs($e['docente']);
    app(CalificacionService::class)->publicar($e['docente'], $c['actividad']);
    // Publicada sin notas: la proyectada es 0 y ya hay alerta; se borra para ver la que abre el intento.
    DbContext::system(fn () => DB::table('academic_alerts')->delete());

    actingAs($e['estudiante']);
    app(CuestionarioService::class)->terminar($e['estudiante'], $c['intento']);

    expect((float) DB::table('grades')->where('enrollment_id', $e['matricula'])->value('score'))->toEqual(5.0)
        ->and(riesgoAlertas($e)->whereNull('closed_at'))->toHaveCount(1);
});

it('CP-RF17-05 resolver una revisión con cambios encola la reevaluación de la sección', function () {
    $e = escenario();
    $r = riesgoRevisionPedida($e);
    actingAs($e['docente']);
    Bus::fake([EvaluarRiesgoAcademico::class]);

    app(RevisionService::class)->resolver($e['docente'], $e['seccion'], $r['solicitud'], [$r['item'] => ['cambiar' => true, 'valor' => 1, 'respuesta' => 'Tiene razón: la redacción era confusa.']], null);

    expect(DB::table('grade_review_requests')->value('status'))->toBe('resolved_changed');
    Bus::assertDispatched(EvaluarRiesgoAcademico::class, fn (EvaluarRiesgoAcademico $job) => $job->seccionId === $e['seccion']);
});

it('CP-RF17-05 de punta a punta por la revisión: la nota sube de 5 a 10 y la alerta se cierra', function () {
    Notification::fake();
    $e = escenario();
    $r = riesgoRevisionPedida($e);
    expect(riesgoAlertas($e)->whereNull('closed_at'))->toHaveCount(1);
    actingAs($e['docente']);

    app(RevisionService::class)->resolver($e['docente'], $e['seccion'], $r['solicitud'], [$r['item'] => ['cambiar' => true, 'valor' => 1, 'respuesta' => 'Tiene razón: la redacción era confusa.']], null);

    expect((float) DB::table('grades')->where('enrollment_id', $e['matricula'])->value('score'))->toEqual(10.0)
        ->and(riesgoAlertas($e)->whereNull('closed_at'))->toBeEmpty();
});

it('CP-RF17-05 resolver una revisión sin cambios no encola la reevaluación', function () {
    $e = escenario();
    $r = riesgoRevisionPedida($e);
    actingAs($e['docente']);
    Bus::fake([EvaluarRiesgoAcademico::class]);

    app(RevisionService::class)->resolver($e['docente'], $e['seccion'], $r['solicitud'], [$r['item'] => ['cambiar' => false, 'respuesta' => 'La nota refleja sus respuestas.']], null);

    expect(DB::table('grade_review_requests')->value('status'))->toBe('resolved_unchanged');
    Bus::assertNotDispatched(EvaluarRiesgoAcademico::class);
});
