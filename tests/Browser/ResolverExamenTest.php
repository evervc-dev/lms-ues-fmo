<?php

use App\Modules\Evaluaciones\Services\CuestionarioService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Flujo crítico 4 en navegador (Estrategia de pruebas, RF16): el docente sube un examen y el estudiante lo
 * resuelve en el Layout Examen. Los datos se arman como en el terreno de prueba de tinker: docente, materia,
 * estudiantes matriculados y un cuestionario de 3 preguntas (V/F, opción múltiple y respuesta corta).
 */

/**
 * @return array{ulid: string, seccion: string}
 */
function examenSubidoPorElDocente(array $e): array
{
    $cuestionarios = app(CuestionarioService::class);
    $componente = DbContext::system(fn () => DB::table('grading_components')->insertGetId([
        'section_id' => $e['seccion'], 'number' => 1, 'name' => 'Cómputo 1', 'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11', 'weight_percent' => 100, 'created_at' => now(), 'updated_at' => now(),
    ]));

    test()->actingAs($e['docente']);
    $banco = $cuestionarios->crearBanco($e['docente'], 'Banco PRN', $e['prn']);
    $cuestionarios->agregarPregunta($e['docente'], $banco, ['tipo' => 'true_false', 'enunciado' => 'PHP es un lenguaje interpretado', 'correcta' => 0]);
    $cuestionarios->agregarPregunta($e['docente'], $banco, ['tipo' => 'multiple_choice', 'enunciado' => '¿Qué framework usa este LMS?', 'opciones' => ['Django', 'Laravel', 'Rails', 'Spring'], 'correcta' => 1]);
    $cuestionarios->agregarPregunta($e['docente'], $banco, ['tipo' => 'short_answer', 'enunciado' => '¿Qué gestor de base de datos usa producción?', 'aceptadas' => 'PostgreSQL, Postgres']);
    $ulid = $cuestionarios->crearCuestionario($e['docente'], $e['seccion'], ['titulo' => 'Examen de prueba', 'instrucciones' => 'Responda las 3 preguntas.', 'componente' => $componente, 'peso' => 30,
        'abre' => now()->subMinutes(5), 'cierra' => now()->addHours(2), 'intentos' => 1, 'minutos' => 30, 'navegacion' => 'free', 'banco' => $banco, 'cantidad' => 3, 'tema' => null, 'dificultad' => null]);

    return ['ulid' => $ulid, 'seccion' => $e['ulid']];
}

it('lets the student solve the exam the teacher uploaded and grades it', function () {
    $e = escenario();
    $examen = examenSubidoPorElDocente($e);
    $this->actingAs($e['estudiante']);

    $pagina = navegar(route('secciones.cuestionarios.show', ['seccion' => $examen['seccion'], 'cuestionario' => $examen['ulid']]))
        ->assertSee('Examen de prueba')
        ->assertSee('Responda las 3 preguntas.');
    aceptarConfirmaciones($pagina)->click('Comenzar intento')->assertSee('Pregunta 1 de 3');
    aceptarConfirmaciones($pagina);

    // Las preguntas salen en orden aleatorio: se responde según el tipo que tocó en cada posición.
    $orden = DB::table('attempt_answers as r')->join('questions as p', 'p.id', '=', 'r.question_id')->orderBy('r.position')->pluck('p.question_type')->all();
    foreach ($orden as $i => $tipo) {
        match ($tipo) {
            'true_false' => $pagina->assertSee('PHP es un lenguaje interpretado')->click('Verdadero'),
            'multiple_choice' => $pagina->assertSee('¿Qué framework usa este LMS?')->click('Laravel'),
            default => $pagina->assertSee('¿Qué gestor de base de datos usa producción?')->type('input[aria-label="Respuesta"]', 'PostgreSQL'),
        };
        $pagina->assertSee('Guardado');
        if ($i < count($orden) - 1) {
            $pagina->click('Siguiente ▶')->assertSee('Pregunta '.($i + 2).' de 3');
        }
    }

    $pagina->click('Terminar intento')
        ->assertSee('Intento enviado.')
        ->assertSee('Calificado')
        ->assertNoJavaScriptErrors();

    expect(DB::table('quiz_attempts')->value('status'))->toBe('graded')
        ->and((float) DB::table('quiz_attempts')->value('score'))->toEqual(10.0)
        ->and(DB::table('attempt_answers')->whereNull('score')->count())->toBe(0);
});
