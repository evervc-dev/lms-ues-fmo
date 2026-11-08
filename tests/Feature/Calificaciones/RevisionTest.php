<?php

use App\Modules\Calificaciones\Livewire\Revision;
use App\Modules\Calificaciones\Livewire\RevisionesSeccion;
use App\Modules\Calificaciones\Livewire\SolicitudRevision;
use App\Modules\Calificaciones\Services\CalificacionService;
use App\Modules\Calificaciones\Services\RevisionService;
use App\Modules\Evaluaciones\Jobs\AvisarRevisionesDisponibles;
use App\Modules\Evaluaciones\Livewire\Entregas;
use App\Modules\Evaluaciones\Livewire\Intento;
use App\Modules\Evaluaciones\Livewire\NuevaTarea;
use App\Modules\Evaluaciones\Livewire\Prorrogas;
use App\Modules\Evaluaciones\Livewire\Tarea;
use App\Modules\Evaluaciones\Services\ActividadService;
use App\Modules\Evaluaciones\Services\CuestionarioService;
use App\Modules\Evaluaciones\Services\ProrrogaService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake());

/**
 * Revisión de actividades (RF15, RF16) y solicitud de revisión de la nota (RF35, ADR-040).
 */
function computoUnico(array $e): int
{
    return DbContext::system(fn () => DB::table('grading_components')->insertGetId([
        'section_id' => $e['seccion'], 'number' => 1, 'name' => 'Cómputo 1', 'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11', 'weight_percent' => 100, 'created_at' => now(), 'updated_at' => now(),
    ]));
}

/**
 * Cuestionario de 2 preguntas V/F: el estudiante acierta la primera y falla la segunda (nota 5); nota publicada.
 *
 * @return array{ulid: string, intento: int, respuestas: array<int, int>}
 */
function cuestionarioCalificado(array $e, bool $publicar = true): array
{
    $cuestionarios = app(CuestionarioService::class);
    actingAs($e['docente']);
    $banco = $cuestionarios->crearBanco($e['docente'], 'Banco PRN', $e['prn']);
    $cuestionarios->agregarPregunta($e['docente'], $banco, ['tipo' => 'true_false', 'enunciado' => 'PHP es interpretado', 'correcta' => 0, 'justificacion' => 'PHP se interpreta en tiempo de ejecución.']);
    $cuestionarios->agregarPregunta($e['docente'], $banco, ['tipo' => 'true_false', 'enunciado' => 'Laravel es un lenguaje', 'correcta' => 1]);
    $ulid = $cuestionarios->crearCuestionario($e['docente'], $e['seccion'], ['titulo' => 'Parcial 1', 'instrucciones' => null, 'componente' => computoUnico($e), 'peso' => 50,
        'abre' => now()->subHour(), 'cierra' => now()->addHour(), 'intentos' => 1, 'minutos' => 30, 'navegacion' => 'free', 'banco' => $banco, 'cantidad' => 2, 'tema' => null, 'dificultad' => null]);

    actingAs($e['estudiante']);
    $intentoUlid = $cuestionarios->iniciar($e['estudiante'], $e['seccion'], $ulid);
    $respuestas = DB::table('attempt_answers as r')->join('questions as p', 'p.id', '=', 'r.question_id')->orderBy('p.id')->pluck('r.id')->all();
    foreach ($respuestas as $respuestaId) {
        $verdadero = DB::table('question_options')->where('question_id', DB::table('attempt_answers')->where('id', $respuestaId)->value('question_id'))->where('text', 'Verdadero')->value('id');
        $cuestionarios->guardarRespuesta($e['estudiante'], $intentoUlid, $respuestaId, (int) $verdadero, null);
    }
    $cuestionarios->terminar($e['estudiante'], $intentoUlid);
    // La corrección se ve, y se puede pedir revisión, 10 minutos después del cierre (ADR-040).
    cerrarCuestionarioEn($ulid, now()->subMinutes(CuestionarioService::demoraRevision() + 1));

    if ($publicar) {
        actingAs($e['docente']);
        app(CalificacionService::class)->publicar($e['docente'], (int) DB::table('assessments')->where('ulid', $ulid)->value('grade_item_id'));
    }

    return ['ulid' => $ulid, 'intento' => (int) DB::table('quiz_attempts')->value('id'), 'respuestas' => $respuestas];
}

/**
 * Mueve el cierre del cuestionario y el límite de sus intentos: la corrección depende de ambos (ADR-042).
 */
function cerrarCuestionarioEn(string $ulid, Carbon $cuando): void
{
    DbContext::system(function () use ($ulid, $cuando) {
        $id = DB::table('assessments')->where('ulid', $ulid)->value('id');
        DB::table('assessments')->where('id', $id)->update(['closes_at' => $cuando]);
        DB::table('quiz_attempts')->where('assessment_id', $id)->update(['deadline_at' => $cuando]);
    });
}

/**
 * Tarea con rúbrica (Contenido 6, Forma 4) calificada «Bueno» en ambos (4.2 + 2.8 = 7.0) y publicada.
 *
 * @return array{ulid: string, entrega: int, criterios: array<string, int>}
 */
function tareaConRubricaCalificada(array $e, bool $publicar = true): array
{
    actingAs($e['docente']);
    Livewire::test(NuevaTarea::class, ['seccion' => $e['ulid']])->set('titulo', 'Informe')->set('componente', computoUnico($e))->set('peso', 50)
        ->set('abre', now()->subDay()->format('Y-m-d\TH:i'))->set('cierra', now()->addDay()->format('Y-m-d\TH:i'))->set('extensiones', 'pdf')
        ->set('conRubrica', true)->set('criterios', [['nombre' => 'Contenido', 'maximo' => 6], ['nombre' => 'Forma', 'maximo' => 4]])
        ->call('guardar')->assertHasNoErrors();
    $ulid = (string) DB::table('assessments')->where('title', 'Informe')->value('ulid');

    actingAs($e['estudiante']);
    Livewire::test(Tarea::class, ['seccion' => $e['ulid'], 'tarea' => $ulid])->set('archivo', UploadedFile::fake()->create('informe.pdf', 50))->call('entregar')->assertHasNoErrors();
    $entrega = (int) DB::table('submissions')->value('id');
    $criterios = DB::table('rubric_criteria')->pluck('id', 'name')->map(fn ($id) => (int) $id)->all();
    $bueno = DB::table('rubric_levels')->where('name', 'Bueno')->pluck('id', 'criterion_id')->all();

    actingAs($e['docente']);
    $prueba = Livewire::test(Entregas::class, ['seccion' => $e['ulid'], 'tarea' => $ulid])->call('abrir', $entrega)->set('niveles', $bueno)->set('comentario', 'Buen análisis, mejore la forma.')->call('calificar');
    if ($publicar) {
        $prueba->call('publicar');
    }

    return ['ulid' => $ulid, 'entrega' => $entrega, 'criterios' => $criterios];
}

it('CP-RF15-06 shows the student the rubric breakdown and the feedback only once grades are published', function () {
    $e = escenario();
    $t = tareaConRubricaCalificada($e, publicar: false);

    actingAs($e['estudiante']);
    $datos = app(ActividadService::class)->tarea($e['estudiante'], $e['seccion'], $t['ulid']);
    expect($datos['nota'])->toBeNull()->and($datos['retroalimentacion'])->toBeEmpty()->and($datos['desglose'])->toBeEmpty();

    actingAs($e['docente']);
    Livewire::test(Entregas::class, ['seccion' => $e['ulid'], 'tarea' => $t['ulid']])->call('publicar');

    actingAs($e['estudiante']);
    Livewire::test(Tarea::class, ['seccion' => $e['ulid'], 'tarea' => $t['ulid']])
        ->assertSeeText(['7.0', 'Buen análisis, mejore la forma.', 'Contenido', 'Bueno', '4.2 / 6', '2.8 / 4']);
});

it('replaces the feedback when the teacher grades again instead of duplicating it', function () {
    $e = escenario();
    $t = tareaConRubricaCalificada($e);

    Livewire::test(Entregas::class, ['seccion' => $e['ulid'], 'tarea' => $t['ulid']])->call('abrir', $t['entrega'])->set('nota', 9)->set('comentario', 'Corregido: excelente.')->call('calificar');

    expect(DB::table('submission_feedback')->where('submission_id', $t['entrega'])->pluck('comment')->all())->toBe(['Corregido: excelente.']);
});

it('CP-RF16-04 shows the quiz correction on its own 10 minutes after closing and notifies once', function () {
    $e = escenario();
    $c = cuestionarioCalificado($e);
    cerrarCuestionarioEn($c['ulid'], now()->subMinutes(5));
    $intento = (string) DB::table('quiz_attempts')->value('ulid');

    actingAs($e['estudiante']);
    Livewire::test(Intento::class, ['intento' => $intento])->assertDontSeeText(['Correcta', 'PHP se interpreta en tiempo de ejecución.']);
    (new AvisarRevisionesDisponibles)->handle();
    expect(DB::table('notifications')->where('event_code', 'feedback.available')->count())->toBe(0);

    $this->travel(6)->minutes();
    // Las preguntas salen en orden aleatorio: se navega a la posición real de cada una.
    $posicion = fn (string $enunciado) => (int) DB::table('attempt_answers as r')->join('questions as p', 'p.id', '=', 'r.question_id')->where('p.statement', $enunciado)->value('r.position');
    Livewire::test(Intento::class, ['intento' => $intento])
        ->call('ir', $posicion('PHP es interpretado'))->assertSeeText(['PHP es interpretado', 'Correcta', '1 de 1 punto', 'PHP se interpreta en tiempo de ejecución.'])
        ->call('ir', $posicion('Laravel es un lenguaje'))->assertSeeText(['Laravel es un lenguaje', '0 de 1 punto']);

    (new AvisarRevisionesDisponibles)->handle();
    (new AvisarRevisionesDisponibles)->handle();
    expect(DB::table('notifications')->where('notifiable_id', $e['estudiante']->id)->where('event_code', 'feedback.available')->count())->toBe(1);
});

it('notifies the students once when their grades are published', function () {
    $e = escenario();
    $c = cuestionarioCalificado($e);
    actingAs($e['docente']);
    app(CalificacionService::class)->publicar($e['docente'], (int) DB::table('assessments')->where('ulid', $c['ulid'])->value('grade_item_id'));

    expect(DB::table('notifications')->where('notifiable_id', $e['estudiante']->id)->where('event_code', 'grade.published')->count())->toBe(1);
});

it('CP-RF35-01 lets the student ask to review specific quiz questions and notifies the teacher', function () {
    $e = escenario();
    $c = cuestionarioCalificado($e);
    actingAs($e['estudiante']);

    Livewire::test(SolicitudRevision::class, ['seccion' => $e['ulid'], 'actividad' => $c['ulid']])
        ->assertSeeText('Solicitar revisión')
        ->call('abrir')
        ->set('marcados', ['pregunta-'.$c['respuestas'][1]])
        ->set('comentarios.pregunta-'.$c['respuestas'][1], 'Laravel se escribe en PHP, la pregunta es ambigua.')
        ->set('motivo', 'Creo que la pregunta 2 admite otra interpretación.')
        ->call('enviar')->assertHasNoErrors()
        ->assertSeeText(['Pendiente', 'Laravel se escribe en PHP, la pregunta es ambigua.']);

    $solicitud = DB::table('grade_review_requests')->first();
    expect($solicitud->status)->toBe('pending')->and((float) $solicitud->score_before)->toEqual(5.0)->and((int) $solicitud->quiz_attempt_id)->toBe($c['intento'])
        ->and(DB::table('grade_review_items')->get(['attempt_answer_id', 'points_before'])->map(fn ($i) => [(int) $i->attempt_answer_id, (float) $i->points_before])->all())
        ->toBe([[$c['respuestas'][1], 0.0]]);
    $this->assertDatabaseHas('notifications', ['notifiable_id' => $e['docente']->id, 'event_code' => 'grade_review.requested']);
});

it('CP-RF35-02 refuses a request without explanation, before publishing, out of time, after the grade sheet or twice', function () {
    $e = escenario();
    $c = cuestionarioCalificado($e, publicar: false);
    $revisiones = app(RevisionService::class);
    $clave = 'pregunta-'.$c['respuestas'][1];
    $pedir = fn (?array $items = null, string $motivo = 'La pregunta es ambigua, revísela.') => $revisiones->solicitar($e['estudiante'], $e['seccion'], $c['ulid'], $motivo, $items ?? [$clave => 'Está mal corregida.']);
    $mensaje = function (callable $accion): string {
        try {
            $accion();
        } catch (ValidationException $excepcion) {
            return (string) collect($excepcion->errors())->flatten()->first();
        }

        return '';
    };
    actingAs($e['estudiante']);

    expect($mensaje($pedir))->toBe('Podrá solicitar revisión cuando se publique la nota.');

    actingAs($e['docente']);
    app(CalificacionService::class)->publicar($e['docente'], (int) DB::table('assessments')->where('ulid', $c['ulid'])->value('grade_item_id'));
    actingAs($e['estudiante']);

    expect($mensaje(fn () => $pedir([])))->toBe('Marque al menos una pregunta, criterio o la nota general, y explique por qué.')
        ->and($mensaje(fn () => $pedir([$clave => 'no'])))->toBe('Explique qué quiere que se revise (al menos 5 caracteres).')
        ->and($mensaje(fn () => $pedir(null, 'corto')))->toBe('Explique en al menos 10 caracteres por qué pide la revisión.');

    $this->travel(RevisionService::plazoDias() + 1)->days();
    expect($mensaje($pedir))->toStartWith('El plazo para solicitar revisión venció');
    $this->travelBack();

    DbContext::system(fn () => DB::table('grade_sheets')->insert(['section_id' => $e['seccion'], 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]));
    expect($mensaje($pedir))->toBe('El acta de la sección ya se publicó.');
    DbContext::system(fn () => DB::table('grade_sheets')->delete());

    $pedir();
    expect($mensaje($pedir))->toBe('Ya solicitó revisión de esta actividad.')
        ->and(DB::table('grade_review_requests')->count())->toBe(1);
});

it('CP-RF35-03 lets the teacher raise a quiz question and recalculates the grade', function () {
    $e = escenario();
    $c = cuestionarioCalificado($e);
    actingAs($e['estudiante']);
    $ulid = app(RevisionService::class)->solicitar($e['estudiante'], $e['seccion'], $c['ulid'], 'La pregunta 2 es ambigua, revísela.', ['pregunta-'.$c['respuestas'][1] => 'Laravel está escrito en PHP.']);
    $item = (int) DB::table('grade_review_items')->value('id');

    actingAs($e['docente']);
    Livewire::test(RevisionesSeccion::class, ['seccion' => $e['ulid']])
        ->assertSeeText(['Solicitudes de revisión', 'Parcial 1']);
    Livewire::test(Revision::class, ['seccion' => $e['ulid'], 'revision' => $ulid])
        ->assertSet('paso', 1)
        ->assertSeeText('La pregunta 2 es ambigua')
        ->call('siguiente')->assertSet('paso', 2)
        ->assertSeeText(['Laravel es un lenguaje', 'Laravel está escrito en PHP.'])
        ->set("decisiones.{$item}.cambiar", true)->set("decisiones.{$item}.valor", 1)->set("decisiones.{$item}.respuesta", 'Tiene razón: la redacción era confusa.')
        ->call('siguiente')->assertSet('paso', 3)
        ->call('resolver')->assertHasNoErrors();

    $solicitud = DB::table('grade_review_requests')->first();
    expect($solicitud->status)->toBe('resolved_changed')->and((float) $solicitud->score_after)->toEqual(10.0)
        ->and((float) DB::table('quiz_attempts')->value('score'))->toEqual(10.0)
        ->and((float) DB::table('grades')->value('score'))->toEqual(10.0)
        ->and(DB::table('grades')->value('status'))->toBe('published')
        ->and(DB::table('grade_review_items')->first(['decision', 'points_after'])->decision)->toBe('changed');
    $this->assertDatabaseHas('notifications', ['notifiable_id' => $e['estudiante']->id, 'event_code' => 'grade_review.resolved']);

    actingAs($e['estudiante']);
    Livewire::test(SolicitudRevision::class, ['seccion' => $e['ulid'], 'actividad' => $c['ulid']])
        ->assertSeeText(['Resuelta: nota modificada', 'Tiene razón: la redacción era confusa.', 'Nota: 5 → 10'])->assertDontSeeText('Solicitar revisión');
});

it('CP-RF35-04 lets the teacher change a rubric criterion and recalculates the task grade', function () {
    $e = escenario();
    $t = tareaConRubricaCalificada($e);
    actingAs($e['estudiante']);
    $ulid = app(RevisionService::class)->solicitar($e['estudiante'], $e['seccion'], $t['ulid'], 'El contenido cumple todo lo pedido.', ['criterio-'.$t['criterios']['Contenido'] => 'Incluí todos los puntos del enunciado.']);
    $item = (int) DB::table('grade_review_items')->value('id');
    $excelente = (int) DB::table('rubric_levels')->where('criterion_id', $t['criterios']['Contenido'])->where('name', 'Excelente')->value('id');

    actingAs($e['docente']);
    app(RevisionService::class)->resolver($e['docente'], $e['seccion'], $ulid, [$item => ['cambiar' => true, 'valor' => $excelente, 'respuesta' => 'Correcto, merece Excelente.']], null);

    expect((float) DB::table('grades')->value('score'))->toEqual(8.8)
        ->and((float) DB::table('submission_rubric_scores')->where('criterion_id', $t['criterios']['Contenido'])->value('points'))->toEqual(6.0)
        ->and((float) DB::table('grade_review_items')->value('points_after'))->toEqual(6.0);
});

it('CP-RF35-05 keeps the grade when the teacher answers without changes, and requires an answer per item', function () {
    $e = escenario();
    $c = cuestionarioCalificado($e);
    actingAs($e['estudiante']);
    $ulid = app(RevisionService::class)->solicitar($e['estudiante'], $e['seccion'], $c['ulid'], 'Quiero que revise mi nota general.', ['general' => 'Estudié mucho para este parcial.']);
    $item = (int) DB::table('grade_review_items')->value('id');
    $revisiones = app(RevisionService::class);
    actingAs($e['docente']);

    expect(fn () => $revisiones->resolver($e['docente'], $e['seccion'], $ulid, [$item => ['cambiar' => false, 'respuesta' => '']], null))->toThrow(ValidationException::class)
        ->and(fn () => $revisiones->resolver($e['docente'], $e['seccion'], $ulid, [$item => ['cambiar' => true, 'valor' => 11, 'respuesta' => 'Más nota']], null))->toThrow(ValidationException::class);

    $revisiones->resolver($e['docente'], $e['seccion'], $ulid, [$item => ['cambiar' => false, 'respuesta' => 'La nota refleja sus respuestas.']], 'Revisé el intento completo.');

    $solicitud = DB::table('grade_review_requests')->first();
    expect($solicitud->status)->toBe('resolved_unchanged')->and((float) $solicitud->score_after)->toEqual(5.0)->and($solicitud->response)->toBe('Revisé el intento completo.')
        ->and((float) DB::table('grades')->value('score'))->toEqual(5.0)
        ->and(fn () => $revisiones->resolver($e['docente'], $e['seccion'], $ulid, [$item => ['respuesta' => 'Otra vez']], null))->toThrow(ValidationException::class);
});

it('CP-RF35-06 lets the coordinator see requests but only the section teacher resolve them', function () {
    $e = escenario();
    $c = cuestionarioCalificado($e);
    actingAs($e['estudiante']);
    $ulid = app(RevisionService::class)->solicitar($e['estudiante'], $e['seccion'], $c['ulid'], 'Quiero que revise mi nota general.', ['general' => 'Estudié mucho para este parcial.']);
    $item = (int) DB::table('grade_review_items')->value('id');
    $revisiones = app(RevisionService::class);
    $decision = [$item => ['cambiar' => true, 'valor' => 9, 'respuesta' => 'Ajuste']];
    $ajeno = usuarioCon('docente');

    expect($revisiones->deSeccion($e['coordinador'], $e['seccion'])->pluck('ulid')->all())->toBe([$ulid])
        ->and(fn () => $revisiones->resolver($e['coordinador'], $e['seccion'], $ulid, $decision, null))->toThrow(HttpException::class)
        ->and(fn () => $revisiones->deSeccion($ajeno, $e['seccion']))->toThrow(HttpException::class)
        ->and(fn () => $revisiones->resolver($ajeno, $e['seccion'], $ulid, $decision, null))->toThrow(HttpException::class)
        ->and(fn () => $revisiones->deSeccion($e['estudiante'], $e['seccion']))->toThrow(HttpException::class)
        ->and((float) DB::table('grades')->value('score'))->toEqual(5.0);

    actingAs($e['coordinador'])->get(route('secciones.revisiones', ['seccion' => $e['ulid']]))->assertOk()->assertSeeText('Solicitudes de revisión');
    actingAs($e['estudiante'])->get(route('secciones.revisiones', ['seccion' => $e['ulid']]))->assertForbidden();
});

it('CP-RF35-06 does not let a student outside the section or another student request a review', function () {
    $e = escenario();
    $c = cuestionarioCalificado($e);
    $otro = usuarioCon('estudiante');
    actingAs($otro);

    expect(fn () => app(RevisionService::class)->solicitar($otro, $e['seccion'], $c['ulid'], 'Quiero que revise la nota.', ['general' => 'Revise por favor.']))->toThrow(HttpException::class)
        ->and(fn () => app(RevisionService::class)->solicitar($e['docente'], $e['seccion'], $c['ulid'], 'Quiero que revise la nota.', ['general' => 'Revise por favor.']))->toThrow(AuthorizationException::class)
        ->and(DB::table('grade_review_requests')->count())->toBe(0);
});

it('lists pending review requests in the teacher inbox', function () {
    $e = escenario();
    $c = cuestionarioCalificado($e);
    actingAs($e['estudiante']);
    app(RevisionService::class)->solicitar($e['estudiante'], $e['seccion'], $c['ulid'], 'Quiero que revise mi nota general.', ['general' => 'Estudié mucho para este parcial.']);

    actingAs($e['docente'])->get(route('pendientes-de-calificar'))->assertOk()->assertSeeText(['Solicitudes de revisión', 'PRN115']);
});

it('CP-RF35-07 enforces one request per activity in the database', function () {
    $e = escenario();
    $c = cuestionarioCalificado($e);
    $fila = fn () => ['ulid' => (string) str()->ulid(), 'section_id' => $e['seccion'], 'enrollment_id' => $e['matricula'], 'assessment_id' => DB::table('assessments')->where('ulid', $c['ulid'])->value('id'),
        'status' => 'resolved_unchanged', 'reason' => 'Motivo de prueba', 'score_before' => 5, 'requested_at' => now(), 'created_at' => now(), 'updated_at' => now()];

    DbContext::system(fn () => DB::table('grade_review_requests')->insert($fila()));
    expect(fn () => DbContext::system(fn () => DB::table('grade_review_requests')->insert($fila())))->toThrow(UniqueConstraintViolationException::class);
});

it('CP-RF35-02 accepts a quiz request only 10 minutes after closing, with the question points then visible', function () {
    $e = escenario();
    $c = cuestionarioCalificado($e);
    cerrarCuestionarioEn($c['ulid'], now()->subMinutes(5));
    actingAs($e['estudiante']);

    Livewire::test(SolicitudRevision::class, ['seccion' => $e['ulid'], 'actividad' => $c['ulid']])->assertSeeText('Podrá solicitar revisión desde el');

    $this->travel(6)->minutes();
    Livewire::test(SolicitudRevision::class, ['seccion' => $e['ulid'], 'actividad' => $c['ulid']])->call('abrir')->assertSeeText(['Pregunta 1', 'pts']);
});

it('CP-RF35-02 refuses a request when the period is closed', function () {
    $e = escenario();
    $c = cuestionarioCalificado($e);
    DbContext::system(fn () => DB::table('academic_periods')->where('id', $e['periodo'])->update(['status' => 'closed']));
    actingAs($e['estudiante']);

    expect(fn () => app(RevisionService::class)->solicitar($e['estudiante'], $e['seccion'], $c['ulid'], 'Quiero que revise mi nota general.', ['general' => 'Estudié mucho.']))
        ->toThrow(ValidationException::class, 'El período está cerrado.');
});

it('CP-RF35-04 recalculates a rubric with the late penalty', function () {
    $e = escenario();
    $t = tareaConRubricaCalificada($e);
    DbContext::system(fn () => DB::table('submissions')->where('id', $t['entrega'])->update(['is_late' => true, 'late_penalty_applied' => 10]));
    $revisiones = app(RevisionService::class);
    actingAs($e['estudiante']);
    $ulid = $revisiones->solicitar($e['estudiante'], $e['seccion'], $t['ulid'], 'El contenido cumple todo lo pedido.', ['criterio-'.$t['criterios']['Contenido'] => 'Incluí todos los puntos.']);
    $item = (int) DB::table('grade_review_items')->value('id');
    $excelente = (int) DB::table('rubric_levels')->where('criterion_id', $t['criterios']['Contenido'])->where('name', 'Excelente')->value('id');

    actingAs($e['docente']);
    $revisiones->resolver($e['docente'], $e['seccion'], $ulid, [$item => ['cambiar' => true, 'valor' => $excelente, 'respuesta' => 'Merece Excelente.']], null);

    expect((float) DB::table('grades')->value('score'))->toEqual(7.92)->and((float) DB::table('grades')->value('score_before_penalty'))->toEqual(8.8);
});

it('CP-RF35-04 takes an overall grade set by the teacher as the final grade, without the late penalty', function () {
    $e = escenario();
    $t = tareaConRubricaCalificada($e);
    DbContext::system(fn () => DB::table('submissions')->where('id', $t['entrega'])->update(['is_late' => true, 'late_penalty_applied' => 10]));
    $revisiones = app(RevisionService::class);
    actingAs($e['estudiante']);
    $ulid = $revisiones->solicitar($e['estudiante'], $e['seccion'], $t['ulid'], 'Pido revisar la nota final completa.', ['general' => 'La penalización no corresponde.']);

    actingAs($e['docente']);
    $revisiones->resolver($e['docente'], $e['seccion'], $ulid, [(int) DB::table('grade_review_items')->value('id') => ['cambiar' => true, 'valor' => 9, 'respuesta' => 'Queda en 9.']], null);

    expect((float) DB::table('grades')->value('score'))->toEqual(9.0);
});

it('CP-RF35-05 marks the request unchanged when the teacher keeps the same value', function () {
    $e = escenario();
    $c = cuestionarioCalificado($e);
    actingAs($e['estudiante']);
    $ulid = app(RevisionService::class)->solicitar($e['estudiante'], $e['seccion'], $c['ulid'], 'La pregunta 2 es ambigua, revísela.', ['pregunta-'.$c['respuestas'][1] => 'Revise la redacción.']);
    $item = (int) DB::table('grade_review_items')->value('id');

    actingAs($e['docente']);
    app(RevisionService::class)->resolver($e['docente'], $e['seccion'], $ulid, [$item => ['cambiar' => true, 'valor' => 0, 'respuesta' => 'Se mantiene en 0.']], null);

    expect(DB::table('grade_review_requests')->value('status'))->toBe('resolved_unchanged')
        ->and(DB::table('grade_review_items')->value('decision'))->toBe('kept');
});

it('CP-RF35-08 lets the teacher extend the review deadline for one student', function () {
    $e = escenario();
    $c = cuestionarioCalificado($e);
    $revisiones = app(RevisionService::class);
    $pedir = fn () => $revisiones->solicitar($e['estudiante'], $e['seccion'], $c['ulid'], 'Estuve enfermo, revise mi nota.', ['general' => 'Adjunto constancia.']);
    $this->travel(RevisionService::plazoDias() + 1)->days();
    actingAs($e['estudiante']);
    expect($pedir)->toThrow(ValidationException::class);

    actingAs($e['docente']);
    app(ProrrogaService::class)->conceder($e['docente'], $e['seccion'], $c['ulid'], $e['matricula'], 'review', now()->addDays(2)->toIso8601String(), 'Constancia médica');

    actingAs($e['estudiante']);
    $pedir();
    expect(DB::table('grade_review_requests')->count())->toBe(1);
    $this->assertDatabaseHas('notifications', ['notifiable_id' => $e['estudiante']->id, 'event_code' => 'extension.granted']);
});

it('CP-RF15-08 lets a student with an extension submit a closed task on time, without penalty', function () {
    $e = escenario();
    $t = tareaConRubricaCalificada($e, publicar: false);
    $otro = usuarioCon('estudiante');
    $otraMatricula = DbContext::system(fn () => DB::table('enrollments')->insertGetId(['student_id' => $otro->id, 'section_id' => $e['seccion'], 'period_id' => $e['periodo'], 'subject_id' => $e['prn'],
        'status' => 'active', 'enrolled_via' => 'admin', 'enrolled_by' => $e['administrador']->id, 'created_at' => now(), 'updated_at' => now()]));
    DbContext::system(fn () => DB::table('assessments')->where('ulid', $t['ulid'])->update(['closes_at' => now()->subHour()]));
    $entregar = fn () => app(ActividadService::class)->entregar($otro, $e['seccion'], $t['ulid'], UploadedFile::fake()->create('tarde.pdf', 20));
    actingAs($otro);
    expect($entregar)->toThrow(ValidationException::class, 'La tarea ya cerró.');

    actingAs($e['docente']);
    Livewire::test(Prorrogas::class, ['seccion' => $e['ulid'], 'actividad' => $t['ulid']])
        ->call('abrir')->set('matricula', $otraMatricula)->set('tipo', 'submission')
        ->set('hasta', now()->timezone('America/El_Salvador')->addDay()->format('Y-m-d\TH:i'))->set('motivo', 'Problema de conexión')
        ->call('conceder')->assertHasNoErrors()->assertSeeText('Problema de conexión');

    actingAs($otro);
    Livewire::test(Tarea::class, ['seccion' => $e['ulid'], 'tarea' => $t['ulid']])->assertSeeText('Tiene prórroga hasta el');
    $entregar();
    expect(DB::table('submissions')->where('enrollment_id', $otraMatricula)->first(['is_late', 'late_penalty_applied']))
        ->is_late->toBeFalsy()->late_penalty_applied->toEqual(0);
});

it('CP-RF15-08 only lets the section teacher grant extensions, and no submission extension for quizzes', function () {
    $e = escenario();
    $c = cuestionarioCalificado($e);
    $prorrogas = app(ProrrogaService::class);
    $hasta = now()->addDay()->toIso8601String();

    expect(fn () => $prorrogas->conceder(usuarioCon('docente'), $e['seccion'], $c['ulid'], $e['matricula'], 'review', $hasta, 'Motivo válido'))->toThrow(HttpException::class)
        ->and(fn () => $prorrogas->conceder($e['estudiante'], $e['seccion'], $c['ulid'], $e['matricula'], 'review', $hasta, 'Motivo válido'))->toThrow(HttpException::class)
        ->and(fn () => $prorrogas->conceder($e['docente'], $e['seccion'], $c['ulid'], $e['matricula'], 'submission', $hasta, 'Motivo válido'))->toThrow(HttpException::class)
        ->and(fn () => $prorrogas->conceder($e['docente'], $e['seccion'], $c['ulid'], $e['matricula'], 'review', now()->subDay()->toIso8601String(), 'Motivo válido'))->toThrow(ValidationException::class)
        ->and(DB::table('assessment_extensions')->count())->toBe(0);
});
