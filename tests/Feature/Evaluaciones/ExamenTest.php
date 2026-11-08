<?php

use App\Modules\Evaluaciones\Jobs\AvisarRevisionesDisponibles;
use App\Modules\Evaluaciones\Jobs\CerrarIntentosVencidos;
use App\Modules\Evaluaciones\Livewire\Intento;
use App\Modules\Evaluaciones\Livewire\MonitorCuestionario;
use App\Modules\Evaluaciones\Services\CuestionarioService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Flujo del examen (RF16, ADR-042): cierre automático de intentos vencidos, autoguardado inmediato, contador
 * anclado al servidor y monitor del docente con minutos extra.
 */

/**
 * Cuestionario abierto de 2 preguntas V/F (20 min) con el intento del estudiante en curso.
 *
 * @return array{ulid: string, intento: string, respuestas: array<int, int>}
 */
function examenEnCurso(array $e): array
{
    $cuestionarios = app(CuestionarioService::class);
    $componente = DbContext::system(fn () => DB::table('grading_components')->insertGetId([
        'section_id' => $e['seccion'], 'number' => 1, 'name' => 'Cómputo 1', 'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11', 'weight_percent' => 100, 'created_at' => now(), 'updated_at' => now(),
    ]));
    actingAs($e['docente']);
    $banco = $cuestionarios->crearBanco($e['docente'], 'Banco PRN', $e['prn']);
    $cuestionarios->agregarPregunta($e['docente'], $banco, ['tipo' => 'true_false', 'enunciado' => 'PHP es interpretado', 'correcta' => 0]);
    $cuestionarios->agregarPregunta($e['docente'], $banco, ['tipo' => 'true_false', 'enunciado' => 'Laravel es un lenguaje', 'correcta' => 1]);
    $ulid = $cuestionarios->crearCuestionario($e['docente'], $e['seccion'], ['titulo' => 'Parcial 1', 'instrucciones' => null, 'componente' => $componente, 'peso' => 50,
        'abre' => now()->subMinutes(5), 'cierra' => now()->addMinutes(20), 'intentos' => 1, 'minutos' => 20, 'navegacion' => 'free', 'banco' => $banco, 'cantidad' => 2, 'tema' => null, 'dificultad' => null]);

    actingAs($e['estudiante']);
    $intento = $cuestionarios->iniciar($e['estudiante'], $e['seccion'], $ulid);

    return ['ulid' => $ulid, 'intento' => $intento, 'respuestas' => DB::table('attempt_answers')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all()];
}

function verdaderoDe(int $respuesta): int
{
    return (int) DB::table('question_options')->where('question_id', DB::table('attempt_answers')->where('id', $respuesta)->value('question_id'))->where('text', 'Verdadero')->value('id');
}

it('CP-RF16-06 closes and grades attempts whose time ran out without being sent, once', function () {
    $e = escenario();
    $x = examenEnCurso($e);
    app(CuestionarioService::class)->guardarRespuesta($e['estudiante'], $x['intento'], $x['respuestas'][0], verdaderoDe($x['respuestas'][0]), null);

    (new CerrarIntentosVencidos)->handle(app(CuestionarioService::class));
    expect(DB::table('quiz_attempts')->value('status'))->toBe('in_progress');

    $this->travel(21)->minutes();
    (new CerrarIntentosVencidos)->handle(app(CuestionarioService::class));
    $enviado = DB::table('quiz_attempts')->value('submitted_at');
    (new CerrarIntentosVencidos)->handle(app(CuestionarioService::class));

    expect(DB::table('quiz_attempts')->value('status'))->toBe('graded')
        ->and(DB::table('quiz_attempts')->value('submitted_at'))->toBe($enviado)
        ->and(DB::table('grades')->where('enrollment_id', $e['matricula'])->exists())->toBeTrue();
});

it('CP-RF16-03 saves each answer as soon as it changes, without waiting for the 30 s save', function () {
    $e = escenario();
    $x = examenEnCurso($e);
    [$primera] = $x['respuestas'];

    Livewire::test(Intento::class, ['intento' => $x['intento']])
        ->set("opciones.{$primera}", verdaderoDe($primera))
        ->assertSeeText('Sin conexión');

    expect((int) DB::table('attempt_answers')->where('id', $primera)->value('selected_option_id'))->toBe(verdaderoDe($primera))
        ->and(DB::table('attempt_answers')->where('id', $primera)->value('saved_at'))->not->toBeNull();
});

it('does not send the attempt when the browser timer ends but the teacher gave extra minutes', function () {
    $e = escenario();
    $x = examenEnCurso($e);
    $prueba = Livewire::test(Intento::class, ['intento' => $x['intento']]);

    DbContext::system(fn () => DB::table('quiz_attempts')->update(['deadline_at' => now()->addMinutes(25)]));
    $prueba->call('tiempoAgotado');
    expect(DB::table('quiz_attempts')->value('status'))->toBe('in_progress')->and($prueba->get('segundos'))->toBeGreaterThan(24 * 60);

    $this->travel(26)->minutes();
    $prueba->call('tiempoAgotado');
    expect(DB::table('quiz_attempts')->value('status'))->toBe('graded');
});

it('CP-RF16-07 shows the teacher the live state of each student', function () {
    $e = escenario();
    $x = examenEnCurso($e);
    $otro = usuarioCon('estudiante');
    DbContext::system(fn () => DB::table('enrollments')->insert(['student_id' => $otro->id, 'section_id' => $e['seccion'], 'period_id' => $e['periodo'], 'subject_id' => $e['prn'],
        'status' => 'active', 'enrolled_via' => 'admin', 'enrolled_by' => $e['administrador']->id, 'created_at' => now(), 'updated_at' => now()]));
    app(CuestionarioService::class)->guardarRespuesta($e['estudiante'], $x['intento'], $x['respuestas'][0], verdaderoDe($x['respuestas'][0]), null);

    actingAs($e['docente']);
    Livewire::test(MonitorCuestionario::class, ['seccion' => $e['ulid'], 'cuestionario' => $x['ulid']])
        ->assertSeeText(['Sin empezar: 1', 'En curso: 1', 'Enviados: 0', '1 / 2', 'Se actualiza cada 15 s'])
        ->assertSeeHtml('wire:poll.15s.keep-alive');
});

it('CP-RF16-07 lets the teacher give extra minutes to some or all attempts, up to 30 min after closing', function () {
    $e = escenario();
    $x = examenEnCurso($e);
    $cuestionarios = app(CuestionarioService::class);
    $cierre = Carbon::parse(DB::table('assessments')->value('closes_at'));
    $antes = Carbon::parse(DB::table('quiz_attempts')->value('deadline_at'));
    actingAs($e['docente']);

    Livewire::test(MonitorCuestionario::class, ['seccion' => $e['ulid'], 'cuestionario' => $x['ulid']])
        ->set('seleccionados', [$x['intento']])->set('minutos', 15)->call('darTiempo', false)->assertHasNoErrors();
    expect(Carbon::parse(DB::table('quiz_attempts')->value('deadline_at'))->equalTo($antes->copy()->addMinutes(15)))->toBeTrue();

    expect($cuestionarios->darTiempo($e['docente'], $e['seccion'], $x['ulid'], null, 60))->toBe(1)
        ->and(Carbon::parse(DB::table('quiz_attempts')->value('deadline_at'))->equalTo($cierre->copy()->addMinutes(30)))->toBeTrue()
        ->and($cuestionarios->darTiempo($e['docente'], $e['seccion'], $x['ulid'], null, 5))->toBe(0)
        ->and(fn () => $cuestionarios->darTiempo($e['docente'], $e['seccion'], $x['ulid'], [], 5))->toThrow(ValidationException::class);
});

it('CP-RF16-07 lets the teacher close an attempt, grading what was saved', function () {
    $e = escenario();
    $x = examenEnCurso($e);
    app(CuestionarioService::class)->guardarRespuesta($e['estudiante'], $x['intento'], $x['respuestas'][0], verdaderoDe($x['respuestas'][0]), null);
    actingAs($e['docente']);

    Livewire::test(MonitorCuestionario::class, ['seccion' => $e['ulid'], 'cuestionario' => $x['ulid']])->call('cerrar', $x['intento']);

    expect(DB::table('quiz_attempts')->value('status'))->toBe('graded')
        ->and(fn () => app(CuestionarioService::class)->cerrarIntento($e['docente'], $e['seccion'], $x['ulid'], $x['intento']))->toThrow(ValidationException::class);
});

it('CP-RF16-07 only lets the section teacher monitor, close or extend', function () {
    $e = escenario();
    $x = examenEnCurso($e);
    $cuestionarios = app(CuestionarioService::class);
    $ajeno = usuarioCon('docente');

    foreach ([$ajeno, $e['estudiante'], $e['coordinador']] as $actor) {
        expect(fn () => $cuestionarios->monitor($actor, $e['seccion'], $x['ulid']))->toThrow(HttpException::class)
            ->and(fn () => $cuestionarios->darTiempo($actor, $e['seccion'], $x['ulid'], null, 10))->toThrow(HttpException::class)
            ->and(fn () => $cuestionarios->cerrarIntento($actor, $e['seccion'], $x['ulid'], $x['intento']))->toThrow(HttpException::class);
    }
    expect(DB::table('quiz_attempts')->value('status'))->toBe('in_progress');
});

it('shows the correction 10 minutes after the last extended attempt ends, not after the closing', function () {
    $e = escenario();
    $x = examenEnCurso($e);
    actingAs($e['docente']);
    app(CuestionarioService::class)->darTiempo($e['docente'], $e['seccion'], $x['ulid'], null, 20);
    $c = DB::table('assessments')->first(['id', 'closes_at']);
    $fin = Carbon::parse(DB::table('quiz_attempts')->value('deadline_at'));

    $this->travelTo(Carbon::parse($c->closes_at)->addMinutes(12));
    expect(CuestionarioService::revisionVisible($c))->toBeFalse();

    $this->travelTo($fin->copy()->addMinutes(11));
    expect(CuestionarioService::revisionVisible($c))->toBeTrue();
});

it('audits the extra minutes in PostgreSQL', function () {
    $e = escenario();
    $x = examenEnCurso($e);
    actingAs($e['docente']);
    app(CuestionarioService::class)->darTiempo($e['docente'], $e['seccion'], $x['ulid'], null, 10);
    DbContext::auditScope(all: true);

    $this->assertDatabaseHas('audit_logs', ['entity_type' => 'quiz_attempts', 'action' => 'update', 'event_type' => 'quiz_attempt.extended', 'actor_user_id' => $e['docente']->id]);
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'La auditoría por triggers solo existe en PostgreSQL.');

it('CP-RF16-02 refuses an answer after the time limit and sends the attempt with what was saved', function () {
    $e = escenario();
    $x = examenEnCurso($e);
    $cuestionarios = app(CuestionarioService::class);
    $cuestionarios->guardarRespuesta($e['estudiante'], $x['intento'], $x['respuestas'][0], verdaderoDe($x['respuestas'][0]), null);

    $this->travel(21)->minutes();

    expect(fn () => $cuestionarios->guardarRespuesta($e['estudiante'], $x['intento'], $x['respuestas'][1], verdaderoDe($x['respuestas'][1]), null))
        ->toThrow(ValidationException::class, 'El intento ya terminó.')
        ->and(DB::table('attempt_answers')->where('id', $x['respuestas'][1])->value('selected_option_id'))->toBeNull()
        ->and(DB::table('quiz_attempts')->value('status'))->toBe('graded');
});

it('CP-RF16-03 recovers the saved answers when the student comes back, including written ones', function () {
    $e = escenario();
    $x = examenEnCurso($e);
    [$primera, $segunda] = $x['respuestas'];

    Livewire::test(Intento::class, ['intento' => $x['intento']])->set("opciones.{$primera}", verdaderoDe($primera))->set("textos.{$segunda}", 'Mi justificación');

    Livewire::test(Intento::class, ['intento' => $x['intento']])
        ->assertSet("opciones.{$primera}", verdaderoDe($primera))
        ->assertSet("textos.{$segunda}", 'Mi justificación');
});

it('does not let a linear quiz change a previous question', function () {
    $e = escenario();
    $x = examenEnCurso($e);
    DbContext::system(fn () => DB::table('quiz_settings')->update(['navigation' => 'linear']));
    $anterior = (int) DB::table('attempt_answers')->where('position', 0)->value('id');

    Livewire::test(Intento::class, ['intento' => $x['intento']])->call('ir', 1)->set("opciones.{$anterior}", verdaderoDe($anterior))->assertForbidden();

    expect(DB::table('attempt_answers')->where('id', $anterior)->value('selected_option_id'))->toBeNull();
});

it('does not close an expired attempt that just received extra minutes, and counts them from now', function () {
    $e = escenario();
    $x = examenEnCurso($e);
    $this->travel(21)->minutes();
    actingAs($e['docente']);

    app(CuestionarioService::class)->darTiempo($e['docente'], $e['seccion'], $x['ulid'], null, 10);
    (new CerrarIntentosVencidos)->handle(app(CuestionarioService::class));

    expect(DB::table('quiz_attempts')->value('status'))->toBe('in_progress')
        ->and(abs(Carbon::parse(DB::table('quiz_attempts')->value('deadline_at'))->diffInSeconds(now()->addMinutes(10))))->toBeLessThan(2);
});

it('ignores attempts of another quiz in the monitor actions', function () {
    $e = escenario();
    $x = examenEnCurso($e);
    $otroCuestionario = DbContext::system(function () {
        $original = DB::table('assessments')->first();
        $ulid = (string) str()->ulid();
        DB::table('assessments')->insert(collect((array) $original)->except('id')->merge(['ulid' => $ulid, 'title' => 'Otro'])->all());

        return $ulid;
    });
    actingAs($e['docente']);

    expect(app(CuestionarioService::class)->darTiempo($e['docente'], $e['seccion'], $otroCuestionario, [$x['intento']], 10))->toBe(0)
        ->and(fn () => app(CuestionarioService::class)->cerrarIntento($e['docente'], $e['seccion'], $otroCuestionario, $x['intento']))->toThrow(HttpException::class)
        ->and(DB::table('quiz_attempts')->value('status'))->toBe('in_progress');
});

it('does not announce the correction while an attempt with extra minutes is still running', function () {
    $e = escenario();
    $x = examenEnCurso($e);
    actingAs($e['docente']);
    app(CuestionarioService::class)->darTiempo($e['docente'], $e['seccion'], $x['ulid'], null, 25);

    $this->travel(31)->minutes();
    (new AvisarRevisionesDisponibles)->handle();
    expect(DB::table('notifications')->where('event_code', 'feedback.available')->count())->toBe(0);
});

it('audits why an attempt was closed in PostgreSQL', function () {
    $e = escenario();
    $x = examenEnCurso($e);
    $this->travel(21)->minutes();
    (new CerrarIntentosVencidos)->handle(app(CuestionarioService::class));
    DbContext::auditScope(all: true);

    $this->assertDatabaseHas('audit_logs', ['entity_type' => 'quiz_attempts', 'action' => 'update', 'event_type' => 'quiz_attempt.expired']);
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'La auditoría por triggers solo existe en PostgreSQL.');
