<?php

use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Flujos completos del examen en navegador (RF16, RF35). Todo pasa por la interfaz:
 * 1. el docente crea el banco, las preguntas y el examen, el estudiante lo resuelve y el docente revisa las notas;
 * 2. además, el docente publica la nota, el estudiante pide revisión de una respuesta corta con una letra de más
 *    («perfectoo» en vez de «perfecto»), el docente la corrige y el estudiante ve la nota corregida.
 */

/**
 * El docente arma el examen por la interfaz: banco, dos preguntas (V/F y respuesta corta «perfecto») y el
 * cuestionario habilitado ya (abre hace un minuto, cierra en una hora). Devuelve el ulid del cuestionario.
 */
function docenteCreaExamen(array $e): string
{
    DbContext::system(fn () => DB::table('grading_components')->insert([
        'section_id' => $e['seccion'], 'number' => 1, 'name' => 'Cómputo 1', 'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11', 'weight_percent' => 100, 'created_at' => now(), 'updated_at' => now(),
    ]));
    test()->actingAs($e['docente']);

    $banco = navegar(route('bancos-de-preguntas'))
        ->click('Nuevo banco')
        ->type('#nombreBanco', 'Banco del parcial')
        ->click('Crear banco')
        ->assertSee('Banco del parcial');

    $banco->click('Nueva pregunta')
        ->select('#tipoP', 'true_false')
        ->type('#enunciado', 'PHP es un lenguaje interpretado')
        ->select('#correctaTf', '0')
        ->click('Agregar')
        ->assertSee('PHP es un lenguaje interpretado');

    $banco->click('Nueva pregunta')
        ->select('#tipoP', 'short_answer')
        ->type('#enunciado', '¿Cómo se califica un trabajo sin errores? (una palabra)')
        ->type('#aceptadas', 'perfecto')
        ->click('Agregar')
        ->assertSee('¿Cómo se califica un trabajo sin errores?');

    navegar(route('secciones.cuestionarios.crear', ['seccion' => $e['ulid']]))
        ->type('#tituloC', 'Parcial de prueba')
        ->type('#instruccionesC', 'Responda las dos preguntas.')
        ->select('#componenteC', (string) DB::table('grading_components')->value('id'))
        ->type('#pesoC', '40')
        ->type('#abreC', now()->subMinute()->format('Y-m-d\TH:i'))
        ->type('#cierraC', now()->addHour()->format('Y-m-d\TH:i'))
        ->select('#bancoC', (string) DB::table('question_banks')->value('id'))
        ->type('#cantidad', '2')
        ->type('#minutos', '20')
        ->click('Crear cuestionario')
        ->assertSee('Parcial de prueba')
        ->assertSee('Responda las dos preguntas.');

    return (string) DB::table('assessments')->where('title', 'Parcial de prueba')->value('ulid');
}

/**
 * Espera (hasta 10 s) a que el servidor refleje lo que hizo el navegador. «Guardado» no sirve como señal: se muestra
 * siempre que no hay una petición en curso. La pausa se hace en el navegador (una promesa), no con wait(): la app
 * corre en este mismo proceso y wait() lo bloquea, así que el servidor no atendería la petición pendiente.
 */
function esperarHasta(mixed $pagina, callable $condicion): void
{
    for ($i = 0; $i < 50 && ! $condicion(); $i++) {
        $pagina->script('new Promise((listo) => setTimeout(listo, 200))');
    }
    expect($condicion())->toBeTrue('No se guardó en el servidor: '.json_encode(DB::table('attempt_answers')->get(['position', 'selected_option_id', 'answer_text', 'saved_at'])));
}

/**
 * El estudiante abre el examen y lo resuelve: «Verdadero» y lo que escriba en la respuesta corta.
 */
function estudianteResuelve(array $e, string $cuestionario, string $respuestaCorta): void
{
    test()->actingAs($e['estudiante']);
    $pagina = navegar(route('secciones.cuestionarios.show', ['seccion' => $e['ulid'], 'cuestionario' => $cuestionario]))->assertSee('Parcial de prueba');
    aceptarConfirmaciones($pagina)->click('Comenzar intento')->assertSee('Pregunta 1 de 2');
    aceptarConfirmaciones($pagina);

    // Las preguntas salen en orden aleatorio: se responde según el tipo que tocó en cada posición.
    $orden = DB::table('attempt_answers as r')->join('questions as p', 'p.id', '=', 'r.question_id')->orderBy('r.position')->pluck('p.question_type')->all();
    foreach ($orden as $i => $tipo) {
        $respuesta = DB::table('attempt_answers')->where('position', $i);
        if ($tipo === 'true_false') {
            $pagina->click('Verdadero');
            esperarHasta($pagina, fn () => (clone $respuesta)->value('selected_option_id') !== null);
        } else {
            // El texto se guarda 2 s después de dejar de escribir.
            $pagina->type('input[aria-label="Respuesta"]', $respuestaCorta);
            esperarHasta($pagina, fn () => (clone $respuesta)->value('answer_text') === $respuestaCorta);
        }
        if ($i === 0) {
            $pagina->click('Siguiente ▶')->assertSee('Pregunta 2 de 2');
        }
    }

    $pagina->click('Terminar intento')->assertSee('Intento enviado.')->assertSee('Calificado')->assertNoJavaScriptErrors();
}

it('lets the teacher create and enable an exam, the student solve it and the teacher review the grades', function () {
    $e = escenario();
    $cuestionario = docenteCreaExamen($e);

    estudianteResuelve($e, $cuestionario, 'perfecto');

    // El docente ve en el monitor el intento enviado y la nota en el libro de calificaciones.
    $this->actingAs($e['docente']);
    navegar(route('secciones.cuestionarios.show', ['seccion' => $e['ulid'], 'cuestionario' => $cuestionario]))
        ->assertSee('Enviados: 1')
        ->assertSee($e['estudiante']->first_name.' '.$e['estudiante']->last_name);
    navegar(route('secciones.notas', ['seccion' => $e['ulid']]))
        ->assertSee('Parcial de prueba')
        ->assertSee($e['estudiante']->last_name.', '.$e['estudiante']->first_name)
        ->assertSee('10.0')
        ->assertNoJavaScriptErrors();

    expect((float) DB::table('grades')->value('score'))->toEqual(10.0)->and(DB::table('grades')->value('status'))->toBe('draft');
});

it('lets the student ask to review a typo, the teacher fix it and the student see the corrected grade', function () {
    $e = escenario();
    $cuestionario = docenteCreaExamen($e);

    // «perfectoo»: una letra de más, la respuesta corta queda en 0 y la nota en 5.
    estudianteResuelve($e, $cuestionario, 'perfectoo');
    expect((float) DB::table('grades')->value('score'))->toEqual(5.0);

    // El docente publica la nota desde el libro de calificaciones.
    $this->actingAs($e['docente']);
    navegar(route('secciones.notas', ['seccion' => $e['ulid']]))->click('Publicar')->assertSee('✓');
    expect(DB::table('grades')->value('status'))->toBe('published');

    // La corrección y la solicitud se habilitan 10 min después del cierre del cuestionario (ADR-040).
    $this->travelTo(Carbon::parse(DB::table('assessments')->where('ulid', $cuestionario)->value('closes_at'))->addMinutes(11));
    $respuesta = (int) DB::table('attempt_answers as r')->join('questions as p', 'p.id', '=', 'r.question_id')->where('p.question_type', 'short_answer')->value('r.id');

    $this->actingAs($e['estudiante']);
    navegar(route('secciones.cuestionarios.show', ['seccion' => $e['ulid'], 'cuestionario' => $cuestionario]))
        ->assertSee('Revisión de la nota')
        ->click('Solicitar revisión')
        ->assertSee('¿Qué quiere que se revise?')
        ->click('#cand-pregunta-'.$respuesta)
        ->type('#coment-pregunta-'.$respuesta, 'Escribí «perfectoo»: se me fue una letra de más, la respuesta es «perfecto».')
        ->type('#motivo', 'Pido que se revise la pregunta de respuesta corta por un error de tipeo.')
        ->click('Enviar solicitud')
        ->assertSee('Pendiente')
        ->assertSee('se me fue una letra de más');

    // El docente la ve en la pestaña Revisiones, sube la pregunta a 1 punto y responde.
    $item = (int) DB::table('grade_review_items')->value('id');
    $this->actingAs($e['docente']);
    navegar(route('secciones.revisiones', ['seccion' => $e['ulid']]))
        ->assertSee('Solicitudes de revisión')
        ->click('Revisar')
        ->assertSee('Respondió: perfectoo')
        ->assertSee('Correcta: perfecto')
        ->click('#ajustar-'.$item)
        ->type('#valor-'.$item, '1')
        ->type('#resp-'.$item, 'De acuerdo: es un error de tipeo, se acepta la respuesta.')
        ->click('Resolver')
        ->assertNoJavaScriptErrors();

    expect(DB::table('grade_review_requests')->value('status'))->toBe('resolved_changed')
        ->and((float) DB::table('grades')->value('score'))->toEqual(10.0);

    // El estudiante ve la nota corregida y la respuesta del docente.
    $this->actingAs($e['estudiante']);
    navegar(route('secciones.cuestionarios.show', ['seccion' => $e['ulid'], 'cuestionario' => $cuestionario]))
        ->assertSee('Resuelta: nota modificada')
        ->assertSee('Nota: 5 → 10')
        ->assertSee('De acuerdo: es un error de tipeo, se acepta la respuesta.')
        ->assertSee('10.0')
        ->assertNoJavaScriptErrors();
});
