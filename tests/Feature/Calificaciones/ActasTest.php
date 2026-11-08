<?php

use App\Models\User;
use App\Modules\Calificaciones\Events\ActaDevuelta;
use App\Modules\Calificaciones\Events\ActaPublicada;
use App\Modules\Calificaciones\Livewire\Acta;
use App\Modules\Calificaciones\Livewire\ActasPorValidar;
use App\Modules\Calificaciones\Livewire\Cierre;
use App\Modules\Calificaciones\Livewire\NotasSeccion;
use App\Modules\Calificaciones\Services\CalificacionService;
use App\Modules\Comunicacion\Notifications\Aviso;
use App\Modules\Evaluaciones\Livewire\Cuestionario;
use App\Modules\Evaluaciones\Livewire\Entregas;
use App\Modules\Evaluaciones\Livewire\NuevaTarea;
use App\Modules\Evaluaciones\Livewire\Tarea;
use App\Modules\Evaluaciones\Services\ActividadService;
use App\Modules\Evaluaciones\Services\CuestionarioService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake());

/**
 * RF34 · Validación de actas y cierre departamental: publicación, bloqueo de notas, validación, devolución,
 * avisos y autorización del cierre por el Jefe. El cierre del período (CP-RF34-07 y 08) está en CierrePeriodoTest.
 */
const ACTA_BLOQUEADA = 'El acta está publicada: pida al Coordinador que la devuelva para cambiar notas.';

/**
 * Un cómputo del 100 % con actividades de peso igual, sin publicar.
 *
 * @param  array<string, mixed>  $e
 * @return array<int, int> id de cada actividad
 */
function actaActividades(array $e, int $cantidad = 2): array
{
    return DbContext::system(function () use ($e, $cantidad) {
        $componente = DB::table('grading_components')->insertGetId([
            'section_id' => $e['seccion'], 'number' => 1, 'name' => 'Cómputo 1', 'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11',
            'weight_percent' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return collect(range(1, $cantidad))->map(fn (int $n) => DB::table('grade_items')->insertGetId([
            'component_id' => $componente, 'section_id' => $e['seccion'], 'item_type' => 'manual', 'title' => "Actividad {$n}",
            'weight_percent' => 100 / $cantidad, 'is_published' => false, 'created_at' => now(), 'updated_at' => now(),
        ]))->all();
    });
}

/**
 * Nota en borrador de una matrícula en una actividad; devuelve el id de la nota.
 *
 * @param  array<string, mixed>  $e
 */
function actaNota(array $e, int $actividad, ?int $matricula = null, float $nota = 5): int
{
    return DbContext::system(fn () => DB::table('grades')->insertGetId([
        'grade_item_id' => $actividad, 'enrollment_id' => $matricula ?? $e['matricula'], 'section_id' => $e['seccion'], 'score' => $nota,
        'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
    ]));
}

/**
 * Deja el acta de una sección en el estado indicado (null la deja sin fila, como una sección sin acta).
 *
 * @param  array<string, mixed>  $e
 */
function actaFijar(array $e, ?string $estado, ?string $motivo = null, ?int $seccion = null): void
{
    if ($estado === null) {
        return;
    }
    DbContext::system(fn () => DB::table('grade_sheets')->updateOrInsert(['section_id' => $seccion ?? $e['seccion']], [
        'status' => $estado, 'return_reason' => $motivo,
        'published_by' => $estado === 'draft' ? null : $e['docente']->id, 'published_at' => $estado === 'draft' ? null : now(),
        'validated_by' => $estado === 'validated' ? $e['coordinador']->id : null, 'validated_at' => $estado === 'validated' ? now() : null,
        'created_at' => now(), 'updated_at' => now(),
    ]));
}

function actaEstado(array $e, ?int $seccion = null): ?string
{
    return DB::table('grade_sheets')->where('section_id', $seccion ?? $e['seccion'])->value('status');
}

function actaPeriodoCerrado(array $e): void
{
    DbContext::system(fn () => DB::table('academic_periods')->where('id', $e['periodo'])->update(['status' => 'closed']));
}

/**
 * Otra sección de PRN115 en el mismo período (para listar secciones pendientes).
 *
 * @param  array<string, mixed>  $e
 */
function actaSeccion(array $e, string $codigo, ?string $estado): int
{
    $id = DbContext::system(fn () => DB::table('sections')->insertGetId([
        'ulid' => (string) str()->ulid(), 'period_id' => $e['periodo'], 'subject_id' => $e['prn'], 'code' => $codigo, 'max_capacity' => 40,
        'created_at' => now(), 'updated_at' => now(),
    ]));
    actaFijar($e, $estado, seccion: $id);

    return $id;
}

/**
 * Matrícula de un estudiante nuevo en la sección del escenario.
 *
 * @param  array<string, mixed>  $e
 */
function actaMatricula(array $e, string $estado = 'active'): int
{
    $estudiante = usuarioCon('estudiante');

    return DbContext::system(fn () => DB::table('enrollments')->insertGetId([
        'student_id' => $estudiante->id, 'section_id' => $e['seccion'], 'period_id' => $e['periodo'], 'subject_id' => $e['prn'],
        'status' => $estado, 'enrolled_via' => 'admin', 'enrolled_by' => $e['administrador']->id, 'created_at' => now(), 'updated_at' => now(),
    ]));
}

/**
 * Docente que imparte solo la otra sección (MAT115): sin alcance sobre la del escenario.
 *
 * @param  array<string, mixed>  $e
 */
function actaDocenteAjeno(array $e): User
{
    $docente = usuarioCon('docente');
    DbContext::system(fn () => DB::table('teaching_assignments')->insert([
        'section_id' => $e['otra'], 'teacher_id' => $docente->id, 'is_responsible' => true, 'started_at' => '2026-07-01', 'created_at' => now(), 'updated_at' => now(),
    ]));

    return $docente;
}

/**
 * Segundo docente asignado a la sección del escenario.
 *
 * @param  array<string, mixed>  $e
 */
function actaCoDocente(array $e): User
{
    $docente = usuarioCon('docente');
    DbContext::system(fn () => DB::table('teaching_assignments')->insert([
        'section_id' => $e['seccion'], 'teacher_id' => $docente->id, 'is_responsible' => false, 'started_at' => '2026-07-01', 'created_at' => now(), 'updated_at' => now(),
    ]));

    return $docente;
}

/**
 * Coordinador de otra carrera (LIC, de DCNM, que solo tiene MAT115): sin alcance sobre PRN115.
 *
 * @param  array<string, mixed>  $e
 */
function actaCoordinadorAjeno(array $e): User
{
    $coordinador = usuarioCon('coordinador');
    DbContext::system(function () use ($e, $coordinador) {
        $carrera = DB::table('careers')->insertGetId(['department_id' => $e['dcnm'], 'code' => 'LIC', 'name' => 'Licenciatura en Matemática', 'created_at' => now(), 'updated_at' => now()]);
        $plan = DB::table('study_plans')->insertGetId(['career_id' => $carrera, 'name' => 'Plan LIC', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('career_subjects')->insert(['study_plan_id' => $plan, 'subject_id' => $e['mat'], 'cycle' => 1]);
        DB::table('career_coordinators')->insert(['user_id' => $coordinador->id, 'career_id' => $carrera, 'valid_from' => '2025-01-01', 'created_at' => now(), 'updated_at' => now()]);
    });

    return $coordinador;
}

/**
 * Jefe del otro departamento (DCNM).
 *
 * @param  array<string, mixed>  $e
 */
function actaJefeAjeno(array $e): User
{
    $jefe = usuarioCon('jefe_departamento');
    DbContext::system(fn () => DB::table('department_heads')->insert(['user_id' => $jefe->id, 'department_id' => $e['dcnm'], 'valid_from' => '2025-01-01', 'created_at' => now(), 'updated_at' => now()]));

    return $jefe;
}

/**
 * Ejecuta la acción y devuelve su código HTTP si la rechazó por permiso o alcance (403), o null si no.
 */
function actaCodigo(Closure $accion): ?int
{
    try {
        $accion();
    } catch (HttpException $excepcion) {
        return $excepcion->getStatusCode();
    } catch (AuthorizationException) {
        return 403;
    }

    return null;
}

/**
 * Ejecuta la acción y devuelve los errores de validación (vacío si no hubo).
 *
 * @return array<string, array<int, string>>
 */
function actaErrores(Closure $accion): array
{
    try {
        $accion();
    } catch (ValidationException $excepcion) {
        return $excepcion->errors();
    }

    return [];
}

/**
 * Tarea con una entrega del estudiante, sin calificar.
 *
 * @param  array<string, mixed>  $e
 * @return array{ulid: string, entrega: int}
 */
function actaTareaEntregada(array $e): array
{
    $componente = DbContext::system(fn () => DB::table('grading_components')->insertGetId([
        'section_id' => $e['seccion'], 'number' => 1, 'name' => 'Cómputo 1', 'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11',
        'weight_percent' => 100, 'created_at' => now(), 'updated_at' => now(),
    ]));
    actingAs($e['docente']);
    Livewire::test(NuevaTarea::class, ['seccion' => $e['ulid']])->set('titulo', 'Informe')->set('componente', $componente)->set('peso', 50)
        ->set('abre', now()->subDay()->format('Y-m-d\TH:i'))->set('cierra', now()->addDay()->format('Y-m-d\TH:i'))->set('extensiones', 'pdf')
        ->call('guardar')->assertHasNoErrors();
    $ulid = (string) DB::table('assessments')->where('title', 'Informe')->value('ulid');

    actingAs($e['estudiante']);
    Livewire::test(Tarea::class, ['seccion' => $e['ulid'], 'tarea' => $ulid])->set('archivo', UploadedFile::fake()->create('informe.pdf', 50))->call('entregar')->assertHasNoErrors();

    return ['ulid' => $ulid, 'entrega' => (int) DB::table('submissions')->value('id')];
}

/**
 * Cuestionario de una pregunta que el estudiante ya empezó y respondió. Con `$enviar` lo envía (un ensayo queda
 * pendiente de calificar); sin él, el intento sigue en curso.
 *
 * @param  array<string, mixed>  $e
 * @param  'essay'|'true_false'  $tipo
 * @return array{intento: string, respuesta: int}
 */
function actaCuestionario(array $e, string $tipo, bool $enviar): array
{
    $componente = DbContext::system(fn () => DB::table('grading_components')->insertGetId([
        'section_id' => $e['seccion'], 'number' => 1, 'name' => 'Cómputo 1', 'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11',
        'weight_percent' => 100, 'created_at' => now(), 'updated_at' => now(),
    ]));
    $cuestionarios = app(CuestionarioService::class);
    actingAs($e['docente']);
    $banco = $cuestionarios->crearBanco($e['docente'], 'Banco PRN', $e['prn']);
    $cuestionarios->agregarPregunta($e['docente'], $banco, $tipo === 'essay'
        ? ['tipo' => 'essay', 'enunciado' => 'Explique el patrón MVC']
        : ['tipo' => 'true_false', 'enunciado' => 'PHP es interpretado', 'correcta' => 0]);
    $ulid = $cuestionarios->crearCuestionario($e['docente'], $e['seccion'], ['titulo' => 'Parcial 1', 'instrucciones' => null, 'componente' => $componente, 'peso' => 50,
        'abre' => now()->subHour(), 'cierra' => now()->addHour(), 'intentos' => 1, 'minutos' => 30, 'navegacion' => 'free', 'banco' => $banco, 'cantidad' => 1, 'tema' => null, 'dificultad' => null]);

    actingAs($e['estudiante']);
    $intento = $cuestionarios->iniciar($e['estudiante'], $e['seccion'], $ulid);
    $respuesta = (int) DB::table('attempt_answers')->value('id');
    $verdadero = $tipo === 'essay' ? null : (int) DB::table('question_options')->where('text', 'Verdadero')->value('id');
    $cuestionarios->guardarRespuesta($e['estudiante'], $intento, $respuesta, $verdadero, $tipo === 'essay' ? 'Separa modelo, vista y controlador.' : null);
    if ($enviar) {
        $cuestionarios->terminar($e['estudiante'], $intento);
    }

    return ['intento' => $intento, 'respuesta' => $respuesta];
}

/* ------------------------------------------------------------------ actaBloqueada */

it('actaBloqueada es verdadera solo con el acta publicada o validada', function (?string $estado, bool $bloqueada) {
    $e = escenario();
    actaFijar($e, $estado, $estado === 'returned' ? 'Revise el cómputo 2.' : null);

    expect(app(CalificacionService::class)->actaBloqueada($e['seccion']))->toBe($bloqueada);
})->with([
    'sin acta' => [null, false],
    'borrador' => ['draft', false],
    'devuelta' => ['returned', false],
    'publicada' => ['published', true],
    'validada' => ['validated', true],
]);

it('actaBloqueada mira solo el acta de la sección consultada', function () {
    $e = escenario();
    actaFijar($e, 'validated');

    expect(app(CalificacionService::class)->actaBloqueada($e['seccion']))->toBeTrue()
        ->and(app(CalificacionService::class)->actaBloqueada($e['otra']))->toBeFalse();
});

/* ------------------------------------------------------------------ CP-RF34-01 */

it('CP-RF34-01 el docente publica el acta con todas las notas y pasa a published con su autor y fecha', function () {
    $e = escenario();
    foreach (actaActividades($e) as $actividad) {
        actaNota($e, $actividad, nota: 8);
    }
    actingAs($e['docente']);

    Livewire::test(Acta::class, ['seccion' => $e['ulid']])->call('publicar')->assertDispatched('aviso', tipo: 'exito');

    $acta = DB::table('grade_sheets')->where('section_id', $e['seccion'])->first();
    expect($acta->status)->toBe('published')
        ->and((int) $acta->published_by)->toBe($e['docente']->id)
        ->and($acta->published_at)->not->toBeNull();
});

it('CP-RF34-01 con el acta publicada o validada el docente ya no puede calificar una actividad y la nota no cambia', function (string $estado) {
    $e = escenario();
    [$actividad] = actaActividades($e);
    $nota = actaNota($e, $actividad, nota: 5);
    actaFijar($e, $estado);
    actingAs($e['docente']);

    $errores = actaErrores(fn () => app(CalificacionService::class)->calificar($e['docente'], $actividad, $e['matricula'], 9));

    expect($errores)->toHaveKey('acta')
        ->and($errores['acta'][0])->toBe(ACTA_BLOQUEADA)
        ->and((float) DB::table('grades')->where('id', $nota)->value('score'))->toEqual(5.0);
})->with(['published', 'validated']);

it('CP-RF34-01 con el acta publicada o validada el docente ya no puede publicar las notas de una actividad', function (string $estado) {
    $e = escenario();
    [$actividad] = actaActividades($e);
    actaNota($e, $actividad);
    actaFijar($e, $estado);
    actingAs($e['docente']);

    $errores = actaErrores(fn () => app(CalificacionService::class)->publicar($e['docente'], $actividad));

    expect($errores)->toHaveKey('acta')
        ->and($errores['acta'][0])->toBe(ACTA_BLOQUEADA)
        ->and((bool) DB::table('grade_items')->where('id', $actividad)->value('is_published'))->toBeFalse()
        ->and(DB::table('grades')->where('grade_item_id', $actividad)->value('status'))->toBe('draft');
})->with(['published', 'validated']);

it('CP-RF34-01 con el acta publicada el docente ya no puede calificar una entrega', function () {
    $e = escenario();
    $tarea = actaTareaEntregada($e);
    actaFijar($e, 'published');
    actingAs($e['docente']);

    $errores = actaErrores(fn () => app(ActividadService::class)->calificarEntrega($e['docente'], $e['seccion'], $tarea['entrega'], 8, [], null));

    expect($errores)->toHaveKey('acta')
        ->and($errores['acta'][0])->toBe(ACTA_BLOQUEADA)
        ->and(DB::table('grades')->count())->toBe(0);
});

it('CP-RF34-01 con el acta publicada el docente ya no puede calificar un ensayo', function () {
    $e = escenario();
    $respuesta = actaCuestionario($e, 'essay', enviar: true)['respuesta'];
    actaFijar($e, 'published');
    actingAs($e['docente']);

    $errores = actaErrores(fn () => app(CuestionarioService::class)->calificarEnsayo($e['docente'], $respuesta, 0.5));

    expect($errores)->toHaveKey('acta')
        ->and($errores['acta'][0])->toBe(ACTA_BLOQUEADA)
        ->and(DB::table('attempt_answers')->where('id', $respuesta)->value('score'))->toBeNull();
});

it('CP-RF34-01 sin el acta publicada (borrador, devuelta o sin acta) el docente sí califica y publica notas', function (?string $estado) {
    $e = escenario();
    [$actividad] = actaActividades($e);
    actaFijar($e, $estado, $estado === 'returned' ? 'Revise el cómputo 2.' : null);
    actingAs($e['docente']);
    $servicio = app(CalificacionService::class);

    $servicio->calificar($e['docente'], $actividad, $e['matricula'], 9);
    $servicio->publicar($e['docente'], $actividad);

    expect((float) DB::table('grades')->where('grade_item_id', $actividad)->value('score'))->toEqual(9.0)
        ->and(DB::table('grades')->where('grade_item_id', $actividad)->value('status'))->toBe('published');
})->with([
    'sin acta' => [null],
    'borrador' => ['draft'],
    'devuelta' => ['returned'],
]);

it('CP-RF34-01 el acta bloqueada no impide al Coordinador corregir una nota con el período cerrado', function () {
    $e = escenario();
    [$actividad] = actaActividades($e, 1);
    $nota = actaNota($e, $actividad, nota: 5);
    actaFijar($e, 'validated');
    actaPeriodoCerrado($e);
    actingAs($e['coordinador']);

    app(CalificacionService::class)->corregir($e['coordinador'], $nota, 6.5, 'Revisión de examen.');

    expect((float) DB::table('grades')->where('id', $nota)->value('score'))->toEqual(6.5);
});

it('CP-RF34-01 el acta publicada no frena la nota automática de un cuestionario que el estudiante termina', function () {
    $e = escenario();
    $cuestionario = actaCuestionario($e, 'true_false', enviar: false);
    actaFijar($e, 'published');
    actingAs($e['estudiante']);

    app(CuestionarioService::class)->terminar($e['estudiante'], $cuestionario['intento']);

    expect(DB::table('quiz_attempts')->where('ulid', $cuestionario['intento'])->value('status'))->toBe('graded')
        ->and((float) DB::table('grades')->where('enrollment_id', $e['matricula'])->value('score'))->toEqual(10.0);
});

it('CP-RF34-01 solo el docente de la sección publica el acta: otro docente, el estudiante, el coordinador y el jefe reciben 403', function (string $rol) {
    $e = escenario();
    $e['docenteAjeno'] = actaDocenteAjeno($e);
    Event::fake([ActaPublicada::class]);
    actingAs($e[$rol]);

    expect(actaCodigo(fn () => app(CalificacionService::class)->publicarActa($e[$rol], $e['seccion'])))->toBe(403)
        ->and(actaEstado($e))->toBeNull();
    Event::assertNotDispatched(ActaPublicada::class);
})->with(['docenteAjeno', 'estudiante', 'coordinador', 'jefe']);

it('CP-RF34-01 no deja al docente publicar el acta dos veces', function (string $estado) {
    $e = escenario();
    actaFijar($e, $estado);
    actingAs($e['docente']);

    $errores = actaErrores(fn () => app(CalificacionService::class)->publicarActa($e['docente'], $e['seccion']));

    expect($errores)->toHaveKey('acta')->and(actaEstado($e))->toBe($estado);
})->with(['published', 'validated']);

it('CP-RF34-01 la pantalla del acta es solo del docente: el estudiante y el coordinador reciben 403', function (string $rol) {
    $e = escenario();

    actingAs($e[$rol])->get(route('secciones.acta', ['seccion' => $e['ulid']]))->assertForbidden();
})->with(['estudiante', 'coordinador']);

it('CP-RF34-01 el docente que no imparte la sección no abre su acta', function () {
    $e = escenario();
    $ajeno = actaDocenteAjeno($e);

    actingAs($ajeno)->get(route('secciones.acta', ['seccion' => $e['ulid']]))->assertForbidden();
});

/* ------------------------------------------------------------------ CP-RF34-02 */

it('CP-RF34-02 acta() cuenta como faltantes los pares matrícula activa por actividad sin nota', function () {
    $e = escenario();
    [$primera, $segunda] = actaActividades($e);
    $otra = actaMatricula($e);
    actaMatricula($e, 'withdrawn');   // una matrícula retirada no cuenta
    actaNota($e, $primera, $e['matricula'], 8);
    actingAs($e['docente']);

    // 2 matrículas activas × 2 actividades = 4 pares; solo 1 tiene nota.
    expect(app(CalificacionService::class)->acta($e['docente'], $e['seccion'])['faltantes'])->toBe(3);

    actaNota($e, $segunda, $e['matricula'], 7);
    actaNota($e, $primera, $otra, 6);
    actaNota($e, $segunda, $otra, 6);

    expect(app(CalificacionService::class)->acta($e['docente'], $e['seccion'])['faltantes'])->toBe(0);
});

it('CP-RF34-02 una sección sin actividades no tiene notas faltantes', function () {
    $e = escenario();
    actingAs($e['docente']);

    expect(app(CalificacionService::class)->acta($e['docente'], $e['seccion'])['faltantes'])->toBe(0);
});

it('CP-RF34-02 la vista advierte cuántas notas faltantes contarán como 0', function () {
    $e = escenario();
    [$primera] = actaActividades($e);
    actaMatricula($e);
    actaNota($e, $primera, $e['matricula'], 8);
    actingAs($e['docente']);

    // 2 estudiantes × 2 actividades, con una sola nota: faltan 3.
    Livewire::test(Acta::class, ['seccion' => $e['ulid']])->assertSee('3 notas faltantes contarán como 0')->assertSeeHtml('wire:confirm');
});

it('CP-RF34-02 sin notas faltantes la vista no muestra la advertencia', function () {
    $e = escenario();
    foreach (actaActividades($e) as $actividad) {
        actaNota($e, $actividad, nota: 8);
    }
    actingAs($e['docente']);

    Livewire::test(Acta::class, ['seccion' => $e['ulid']])->assertDontSee('notas faltantes');
});

it('CP-RF34-02 con notas faltantes la advertencia no bloquea: el acta se publica igual', function () {
    $e = escenario();
    actaActividades($e);
    actingAs($e['docente']);

    Livewire::test(Acta::class, ['seccion' => $e['ulid']])->call('publicar')->assertDispatched('aviso', tipo: 'exito');

    expect(actaEstado($e))->toBe('published');
});

it('CP-RF34-02 el docente que no imparte la sección no ve su acta ni sus notas faltantes (403)', function () {
    $e = escenario();
    $ajeno = actaDocenteAjeno($e);
    actaActividades($e);
    actingAs($ajeno);

    expect(actaCodigo(fn () => app(CalificacionService::class)->acta($ajeno, $e['seccion'])))->toBe(403);
});

it('CP-RF34-02 el estudiante matriculado no recibe el libro con las notas de sus compañeros (403)', function () {
    $e = escenario();
    [$actividad] = actaActividades($e, 1);
    $otra = actaMatricula($e);
    actaNota($e, $actividad, $otra, 9);
    actingAs($e['estudiante']);

    expect(actaCodigo(fn () => app(CalificacionService::class)->libro($e['estudiante'], $e['seccion'])))->toBe(403);
});

it('CP-RF34-02 el docente y el Coordinador sí reciben el libro de la sección', function () {
    $e = escenario();
    actaActividades($e, 1);

    foreach ([$e['docente'], $e['coordinador']] as $usuario) {
        actingAs($usuario);
        expect(app(CalificacionService::class)->libro($usuario, $e['seccion'])['filas'])->not->toBeEmpty();
    }
});

it('CP-RF34-02 el estudiante matriculado no recibe el acta con las notas de sus compañeros (403)', function () {
    $e = escenario();
    actaActividades($e);
    actingAs($e['estudiante']);

    expect(actaCodigo(fn () => app(CalificacionService::class)->acta($e['estudiante'], $e['seccion'])))->toBe(403);
});

/* ------------------------------------------------------------------ CP-RF34-01 (el error llega a la pantalla) */

it('CP-RF34-01 la pantalla de notas avisa al docente en vez de fallar en silencio con el acta publicada', function () {
    $e = escenario();
    [$actividad] = actaActividades($e, 1);
    actaNota($e, $actividad);
    actaFijar($e, 'published');
    actingAs($e['docente']);

    Livewire::test(NotasSeccion::class, ['seccion' => $e['ulid']])
        ->call('publicar', $actividad)
        ->assertDispatched('aviso', tipo: 'error', mensaje: ACTA_BLOQUEADA);

    expect(DB::table('grades')->where('grade_item_id', $actividad)->value('status'))->toBe('draft');
});

it('CP-RF34-01 la pantalla de entregas avisa al docente al calificar y al publicar con el acta publicada', function () {
    $e = escenario();
    $tarea = actaTareaEntregada($e);
    actaFijar($e, 'published');
    actingAs($e['docente']);

    Livewire::test(Entregas::class, ['seccion' => $e['ulid'], 'tarea' => $tarea['ulid']])
        ->set('entregaId', $tarea['entrega'])->set('nota', 8.0)
        ->call('calificar')
        ->assertDispatched('aviso', tipo: 'error', mensaje: ACTA_BLOQUEADA);

    Livewire::test(Entregas::class, ['seccion' => $e['ulid'], 'tarea' => $tarea['ulid']])
        ->call('publicar')
        ->assertDispatched('aviso', tipo: 'error', mensaje: ACTA_BLOQUEADA);
});

it('CP-RF34-01 la pantalla del cuestionario avisa al docente al calificar un ensayo con el acta publicada', function () {
    $e = escenario();
    $cuestionario = actaCuestionario($e, 'essay', enviar: true);
    $ulid = (string) DB::table('assessments')->where('title', 'Parcial 1')->value('ulid');
    actaFijar($e, 'published');
    actingAs($e['docente']);

    Livewire::test(Cuestionario::class, ['seccion' => $e['ulid'], 'cuestionario' => $ulid])
        ->set("ensayos.{$cuestionario['respuesta']}", 10)
        ->call('calificarEnsayo', $cuestionario['respuesta'])
        ->assertDispatched('aviso', tipo: 'error', mensaje: ACTA_BLOQUEADA);
});

/* ------------------------------------------------------------------ CP-RF34-09 (la validación deja de ser vigente) */

it('CP-RF34-09 al devolver un acta validada se borran el validador y su fecha', function () {
    $e = escenario();
    actaFijar($e, 'validated');
    actingAs($e['coordinador']);

    app(CalificacionService::class)->devolverActa($e['coordinador'], $e['seccion'], 'Revise el cómputo 2.');

    $acta = DB::table('grade_sheets')->where('section_id', $e['seccion'])->first();
    expect($acta->status)->toBe('returned')
        ->and($acta->validated_by)->toBeNull()
        ->and($acta->validated_at)->toBeNull();
});

it('CP-RF34-09 al volver a publicar un acta devuelta no queda el validador anterior', function () {
    $e = escenario();
    actaActividades($e);
    actaFijar($e, 'validated');
    actingAs($e['coordinador']);
    app(CalificacionService::class)->devolverActa($e['coordinador'], $e['seccion'], 'Revise el cómputo 2.');

    actingAs($e['docente']);
    app(CalificacionService::class)->publicarActa($e['docente'], $e['seccion']);

    $acta = DB::table('grade_sheets')->where('section_id', $e['seccion'])->first();
    expect($acta->status)->toBe('published')
        ->and($acta->validated_by)->toBeNull()
        ->and($acta->validated_at)->toBeNull()
        ->and($acta->return_reason)->toBeNull();
});

/* ------------------------------------------------------------------ CP-RF34-04 (cada devolución conserva su motivo) */

it('CP-RF34-04 dos devoluciones seguidas al mismo docente no se agrupan: cada motivo llega entero', function () {
    $e = escenario();
    $otra = actaSeccion($e, '03', 'published');
    DbContext::system(fn () => DB::table('teaching_assignments')->insert([
        'section_id' => $otra, 'teacher_id' => $e['docente']->id, 'is_responsible' => true, 'started_at' => '2026-07-01', 'created_at' => now(), 'updated_at' => now(),
    ]));
    actaFijar($e, 'published');
    actingAs($e['coordinador']);

    app(CalificacionService::class)->devolverActa($e['coordinador'], $e['seccion'], 'Falta la nota del cómputo 2.');
    app(CalificacionService::class)->devolverActa($e['coordinador'], $otra, 'Revise la nota de Pérez.');

    $avisos = DB::table('notifications')->where('notifiable_id', $e['docente']->id)->where('event_code', 'grade_sheet.returned')
        ->get()->map(fn ($n) => json_decode($n->data, true)['body']);
    expect($avisos)->toHaveCount(2)
        ->and($avisos->filter(fn ($c) => str_contains($c, 'Falta la nota del cómputo 2.')))->toHaveCount(1)
        ->and($avisos->filter(fn ($c) => str_contains($c, 'Revise la nota de Pérez.')))->toHaveCount(1);
});

/* ------------------------------------------------------------------ CP-RF34-03 (auditoría) */

it('CP-RF34-03 audita la validación del acta con su autor, el estado anterior y el nuevo', function () {
    $e = escenario();
    actaFijar($e, 'published');
    actingAs($e['coordinador']);

    app(CalificacionService::class)->validarActa($e['coordinador'], $e['seccion']);

    DbContext::auditScope(all: true);
    $acta = DB::table('grade_sheets')->where('section_id', $e['seccion'])->first();
    $auditoria = DB::table('audit_logs')->where('entity_type', 'grade_sheets')->where('entity_id', $acta->id)->where('action', 'update')->get();

    expect($auditoria)->toHaveCount(1)
        ->and((int) $auditoria->first()->actor_user_id)->toBe($e['coordinador']->id)
        ->and(json_decode($auditoria->first()->old_values, true)['status'])->toBe('published')
        ->and(json_decode($auditoria->first()->new_values, true)['status'])->toBe('validated')
        ->and((int) $acta->validated_by)->toBe($e['coordinador']->id);
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'Requiere PostgreSQL');

it('CP-RF34-03 solo el Coordinador de la carrera valida el acta: el docente, el jefe y el estudiante reciben 403', function (string $rol) {
    $e = escenario();
    actaFijar($e, 'published');
    actingAs($e[$rol]);

    expect(actaCodigo(fn () => app(CalificacionService::class)->validarActa($e[$rol], $e['seccion'])))->toBe(403)
        ->and(actaEstado($e))->toBe('published');
})->with(['docente', 'jefe', 'estudiante']);

/* ------------------------------------------------------------------ CP-RF34-04 */

it('CP-RF34-04 el Coordinador devuelve el acta con observaciones y pasa a returned con el motivo', function () {
    $e = escenario();
    actaFijar($e, 'published');
    actingAs($e['coordinador']);

    Livewire::test(ActasPorValidar::class)->call('confirmarDevolucion', $e['seccion'])->set('motivo', 'Falta la nota del cómputo 2.')->call('devolver')
        ->assertHasNoErrors()->assertDispatched('aviso', tipo: 'exito');

    $acta = DB::table('grade_sheets')->where('section_id', $e['seccion'])->first();
    expect($acta->status)->toBe('returned')->and($acta->return_reason)->toBe('Falta la nota del cómputo 2.');
});

it('CP-RF34-04 exige observaciones de al menos 5 caracteres para devolver', function () {
    $e = escenario();
    actaFijar($e, 'published');
    actingAs($e['coordinador']);

    Livewire::test(ActasPorValidar::class)->call('confirmarDevolucion', $e['seccion'])->set('motivo', 'no')->call('devolver')->assertHasErrors(['motivo']);

    expect(actaEstado($e))->toBe('published');
});

it('CP-RF34-04 devolver despliega ActaDevuelta con la sección y el motivo, y publicar despliega ActaPublicada', function () {
    $e = escenario();
    actaActividades($e);
    Event::fake([ActaPublicada::class, ActaDevuelta::class]);

    actingAs($e['docente']);
    app(CalificacionService::class)->publicarActa($e['docente'], $e['seccion']);
    Event::assertDispatched(ActaPublicada::class, fn (ActaPublicada $evento) => $evento->seccionId === $e['seccion']);
    Event::assertNotDispatched(ActaDevuelta::class);

    actingAs($e['coordinador']);
    app(CalificacionService::class)->devolverActa($e['coordinador'], $e['seccion'], 'Revise el cómputo 2.');
    Event::assertDispatched(ActaDevuelta::class, fn (ActaDevuelta $evento) => $evento->seccionId === $e['seccion'] && $evento->motivo === 'Revise el cómputo 2.');
});

it('CP-RF34-04 el docente asignado recibe el aviso grade_sheet.returned con el motivo, en la cola notificaciones', function () {
    Notification::fake();
    $e = escenario();
    $coDocente = actaCoDocente($e);
    $ajeno = actaDocenteAjeno($e);
    actaFijar($e, 'published');
    actingAs($e['coordinador']);

    app(CalificacionService::class)->devolverActa($e['coordinador'], $e['seccion'], 'Falta la nota del cómputo 2.');

    foreach ([$e['docente'], $coDocente] as $docente) {
        $fila = DB::table('notifications')->where('notifiable_id', $docente->id)->where('event_code', 'grade_sheet.returned')->first();
        expect($fila)->not->toBeNull()->and(json_decode($fila->data, true)['body'])->toContain('Falta la nota del cómputo 2.');
        Notification::assertSentTo($docente, Aviso::class, fn (Aviso $aviso) => $aviso->evento === 'grade_sheet.returned'
            && str_contains($aviso->cuerpo, 'Falta la nota del cómputo 2.') && $aviso->queue === 'notificaciones');
    }
    foreach ([$ajeno, $e['estudiante'], $e['coordinador'], $e['jefe']] as $otro) {
        Notification::assertNotSentTo($otro, Aviso::class);
        expect(DB::table('notifications')->where('notifiable_id', $otro->id)->count())->toBe(0);
    }
});

it('CP-RF34-04 el docente ve las observaciones, corrige y vuelve a publicar: pasa a published y se limpia el motivo', function () {
    $e = escenario();
    [$actividad] = actaActividades($e, 1);
    actaNota($e, $actividad, nota: 5);
    actaFijar($e, 'published');
    actingAs($e['coordinador']);
    app(CalificacionService::class)->devolverActa($e['coordinador'], $e['seccion'], 'Falta la nota del cómputo 2.');

    actingAs($e['docente']);
    app(CalificacionService::class)->calificar($e['docente'], $actividad, $e['matricula'], 9);
    $pantalla = Livewire::test(Acta::class, ['seccion' => $e['ulid']])->assertSee('Observaciones del Coordinador')->assertSee('Falta la nota del cómputo 2.');
    $pantalla->call('publicar')->assertDispatched('aviso', tipo: 'exito');

    $acta = DB::table('grade_sheets')->where('section_id', $e['seccion'])->first();
    expect($acta->status)->toBe('published')->and($acta->return_reason)->toBeNull()
        ->and((float) DB::table('grades')->where('grade_item_id', $actividad)->value('score'))->toEqual(9.0);
});

it('CP-RF34-04 solo el Coordinador de la carrera devuelve el acta: el docente, el jefe y el estudiante reciben 403 y no se avisa a nadie', function (string $rol) {
    $e = escenario();
    actaFijar($e, 'published');
    Event::fake([ActaDevuelta::class]);
    actingAs($e[$rol]);

    expect(actaCodigo(fn () => app(CalificacionService::class)->devolverActa($e[$rol], $e['seccion'], 'Revise el cómputo 2.')))->toBe(403)
        ->and(actaEstado($e))->toBe('published');
    Event::assertNotDispatched(ActaDevuelta::class);
})->with(['docente', 'jefe', 'estudiante']);

it('CP-RF34-04 si la devolución se rechaza no despliega el evento', function () {
    $e = escenario();
    actaFijar($e, 'draft');
    Event::fake([ActaDevuelta::class]);
    actingAs($e['coordinador']);

    expect(actaErrores(fn () => app(CalificacionService::class)->devolverActa($e['coordinador'], $e['seccion'], 'Revise el cómputo 2.')))->toHaveKey('acta');
    Event::assertNotDispatched(ActaDevuelta::class);
});

it('CP-RF34-04 audita la devolución del acta con el motivo', function () {
    $e = escenario();
    actaFijar($e, 'published');
    actingAs($e['coordinador']);

    app(CalificacionService::class)->devolverActa($e['coordinador'], $e['seccion'], 'Falta la nota del cómputo 2.');

    DbContext::auditScope(all: true);
    $acta = DB::table('grade_sheets')->where('section_id', $e['seccion'])->first();
    $auditoria = DB::table('audit_logs')->where('entity_type', 'grade_sheets')->where('entity_id', $acta->id)->where('action', 'update')->get();

    expect($auditoria)->toHaveCount(1)
        ->and((int) $auditoria->first()->actor_user_id)->toBe($e['coordinador']->id)
        ->and(json_decode($auditoria->first()->new_values, true)['status'])->toBe('returned');
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'Requiere PostgreSQL');

/* ------------------------------------------------------------------ Aviso al publicar */

it('CP-RF34-01 publicar avisa grade_sheet.published a los coordinadores con alcance sobre la sección y a nadie más', function () {
    $e = escenario();
    actaActividades($e);
    $ajeno = actaCoordinadorAjeno($e);
    // Otra carrera que también ofrece PRN115 en su plan: su coordinador también tiene alcance.
    $otroCoordinador = usuarioCon('coordinador');
    DbContext::system(function () use ($e, $otroCoordinador) {
        $carrera = DB::table('careers')->insertGetId(['department_id' => $e['dia'], 'code' => 'ISC', 'name' => 'Ingeniería en Sistemas Computacionales', 'created_at' => now(), 'updated_at' => now()]);
        $plan = DB::table('study_plans')->insertGetId(['career_id' => $carrera, 'name' => 'Plan ISC', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('career_subjects')->insert(['study_plan_id' => $plan, 'subject_id' => $e['prn'], 'cycle' => 1]);
        DB::table('career_coordinators')->insert(['user_id' => $otroCoordinador->id, 'career_id' => $carrera, 'valid_from' => '2025-01-01', 'created_at' => now(), 'updated_at' => now()]);
    });
    actingAs($e['docente']);

    app(CalificacionService::class)->publicarActa($e['docente'], $e['seccion']);

    $avisados = DB::table('notifications')->where('event_code', 'grade_sheet.published')->pluck('notifiable_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
    expect($avisados)->toBe(collect([$e['coordinador']->id, $otroCoordinador->id])->sort()->values()->all())
        ->and(DB::table('notifications')->where('notifiable_id', $ajeno->id)->count())->toBe(0)
        ->and(DB::table('notifications')->whereIn('notifiable_id', [$e['jefe']->id, $e['docente']->id, $e['estudiante']->id])->count())->toBe(0);
});

it('CP-RF34-01 el aviso de acta publicada sale por la cola notificaciones y no sale si el docente no puede publicar', function () {
    Notification::fake();
    $e = escenario();
    actaActividades($e);

    actingAs($e['estudiante']);
    actaCodigo(fn () => app(CalificacionService::class)->publicarActa($e['estudiante'], $e['seccion']));
    Notification::assertNothingSent();

    actingAs($e['docente']);
    app(CalificacionService::class)->publicarActa($e['docente'], $e['seccion']);
    Notification::assertSentTo($e['coordinador'], Aviso::class, fn (Aviso $aviso) => $aviso->evento === 'grade_sheet.published' && $aviso->queue === 'notificaciones');
    Notification::assertNotSentTo($e['docente'], Aviso::class);
});

/* ------------------------------------------------------------------ CP-RF34-05 */

it('CP-RF34-05 el Jefe no autoriza el cierre con actas sin validar y se listan las secciones pendientes por código y grupo', function () {
    $e = escenario();
    actaFijar($e, 'validated');
    actaSeccion($e, '02', null);
    actaSeccion($e, '03', 'published');
    actingAs($e['jefe']);

    $errores = actaErrores(fn () => app(CalificacionService::class)->autorizarCierre($e['jefe']));

    expect($errores)->toHaveKey('cierre')
        ->and($errores['cierre'][0])->toContain('PRN115-02')->toContain('PRN115-03')->not->toContain('PRN115-01')->not->toContain('MAT115')
        ->and($errores['cierre'][0])->toMatch('/\b2\b/')->toMatch('/\b3\b/')
        ->and(DB::table('department_closures')->count())->toBe(0);
});

it('CP-RF34-05 las secciones devueltas o en borrador también quedan como pendientes', function () {
    $e = escenario();
    actaFijar($e, 'returned', 'Revise el cómputo 2.');
    actingAs($e['jefe']);

    $errores = actaErrores(fn () => app(CalificacionService::class)->autorizarCierre($e['jefe']));

    expect($errores['cierre'][0])->toContain('PRN115-01');
});

it('CP-RF34-05 la pantalla del cierre muestra el error con las secciones pendientes y no autoriza', function () {
    $e = escenario();
    actaFijar($e, 'published');
    actingAs($e['jefe']);

    Livewire::test(Cierre::class)->call('autorizar')
        ->assertDispatched('aviso', fn (string $evento, array $parametros) => ($parametros['tipo'] ?? null) === 'error' && str_contains($parametros['mensaje'] ?? '', 'PRN115-01'));

    expect(DB::table('department_closures')->count())->toBe(0);
});

it('CP-RF34-05 el Jefe solo ve las pendientes de su departamento', function () {
    $e = escenario();
    $jefeDcnm = actaJefeAjeno($e);
    actaFijar($e, 'published');
    actingAs($jefeDcnm);

    $errores = actaErrores(fn () => app(CalificacionService::class)->autorizarCierre($jefeDcnm));

    expect($errores['cierre'][0])->toContain('MAT115-01')->not->toContain('PRN115');
});

it('CP-RF34-05 solo el Jefe autoriza el cierre: el administrador, el coordinador, el docente, el estudiante y soporte reciben 403', function (string $rol) {
    $e = escenario();
    actaFijar($e, 'validated');
    actingAs($e[$rol]);

    expect(actaCodigo(fn () => app(CalificacionService::class)->autorizarCierre($e[$rol])))->toBe(403)
        ->and(DB::table('department_closures')->count())->toBe(0);
})->with(['administrador', 'coordinador', 'docente', 'estudiante', 'soporte']);

it('CP-RF34-05 la ruta del cierre es solo del Jefe', function (string $rol) {
    $e = escenario();

    actingAs($e[$rol])->get(route('cierre'))->assertForbidden();
})->with(['coordinador', 'docente', 'estudiante']);

/* ------------------------------------------------------------------ CP-RF34-09 */

it('CP-RF34-09 el Coordinador devuelve un acta validada, el docente corrige y el ciclo se repite', function () {
    $e = escenario();
    [$actividad] = actaActividades($e, 1);
    actaNota($e, $actividad, nota: 5);
    actaFijar($e, 'validated');
    $servicio = app(CalificacionService::class);

    foreach ([9.0, 8.0] as $nota) {
        actingAs($e['coordinador']);
        $servicio->devolverActa($e['coordinador'], $e['seccion'], 'Hay que cambiar una nota.');
        expect(actaEstado($e))->toBe('returned');

        actingAs($e['docente']);
        $servicio->calificar($e['docente'], $actividad, $e['matricula'], $nota);
        $servicio->publicarActa($e['docente'], $e['seccion']);
        expect(actaEstado($e))->toBe('published')->and(DB::table('grade_sheets')->value('return_reason'))->toBeNull();

        actingAs($e['coordinador']);
        $servicio->validarActa($e['coordinador'], $e['seccion']);
        expect(actaEstado($e))->toBe('validated')
            ->and((float) DB::table('grades')->where('grade_item_id', $actividad)->value('score'))->toEqual($nota);
    }
});

it('CP-RF34-09 con el acta validada el docente no cambia la nota y debe pedir la devolución', function () {
    $e = escenario();
    [$actividad] = actaActividades($e, 1);
    $nota = actaNota($e, $actividad, nota: 5);
    actaFijar($e, 'validated');
    actingAs($e['docente']);

    $errores = actaErrores(fn () => app(CalificacionService::class)->calificar($e['docente'], $actividad, $e['matricula'], 9));

    expect($errores['acta'][0])->toBe(ACTA_BLOQUEADA)->and((float) DB::table('grades')->where('id', $nota)->value('score'))->toEqual(5.0);
});

it('CP-RF34-09 con el período cerrado no se devuelve el acta validada y aplica la corrección del Coordinador', function () {
    $e = escenario();
    [$actividad] = actaActividades($e, 1);
    $nota = actaNota($e, $actividad, nota: 5);
    actaFijar($e, 'validated');
    actaPeriodoCerrado($e);
    actingAs($e['coordinador']);
    $servicio = app(CalificacionService::class);

    $errores = actaErrores(fn () => $servicio->devolverActa($e['coordinador'], $e['seccion'], 'Hay que cambiar una nota.'));
    expect($errores)->toHaveKey('acta')->and(actaEstado($e))->toBe('validated');

    $servicio->corregir($e['coordinador'], $nota, 7.5, 'Revisión de examen.');
    expect((float) DB::table('grades')->where('id', $nota)->value('score'))->toEqual(7.5)->and(actaEstado($e))->toBe('validated');
});

it('CP-RF34-09 solo se valida un acta publicada: borrador, devuelta, sin acta y validada se rechazan', function (?string $estado) {
    $e = escenario();
    actaFijar($e, $estado, $estado === 'returned' ? 'Revise el cómputo 2.' : null);
    actingAs($e['coordinador']);

    $errores = actaErrores(fn () => app(CalificacionService::class)->validarActa($e['coordinador'], $e['seccion']));

    expect($errores)->toHaveKey('acta')->and(actaEstado($e))->toBe($estado);
})->with([
    'sin acta' => [null],
    'borrador' => ['draft'],
    'devuelta' => ['returned'],
    'validada' => ['validated'],
]);

it('CP-RF34-09 solo se devuelve un acta publicada o validada: borrador, devuelta y sin acta se rechazan', function (?string $estado) {
    $e = escenario();
    actaFijar($e, $estado, $estado === 'returned' ? 'Revise el cómputo 2.' : null);
    actingAs($e['coordinador']);

    $errores = actaErrores(fn () => app(CalificacionService::class)->devolverActa($e['coordinador'], $e['seccion'], 'Revise el cómputo 2.'));

    expect($errores)->toHaveKey('acta')->and(actaEstado($e))->toBe($estado);
})->with([
    'sin acta' => [null],
    'borrador' => ['draft'],
    'devuelta' => ['returned'],
]);

it('CP-RF34-09 el Coordinador de otra carrera no valida ni devuelve el acta (403)', function () {
    $e = escenario();
    $ajeno = actaCoordinadorAjeno($e);
    actaFijar($e, 'published');
    actingAs($ajeno);
    $servicio = app(CalificacionService::class);

    expect(actaCodigo(fn () => $servicio->validarActa($ajeno, $e['seccion'])))->toBe(403)
        ->and(actaCodigo(fn () => $servicio->devolverActa($ajeno, $e['seccion'], 'Revise el cómputo 2.')))->toBe(403)
        ->and(actaEstado($e))->toBe('published');
});

it('CP-RF34-09 el Coordinador de otra carrera no ve el acta en su lista por validar', function () {
    $e = escenario();
    $ajeno = actaCoordinadorAjeno($e);
    actaFijar($e, 'published');

    actingAs($ajeno);
    Livewire::test(ActasPorValidar::class)->assertDontSee('PRN115');

    actingAs($e['coordinador']);
    Livewire::test(ActasPorValidar::class)->assertSee('PRN115');
});

it('CP-RF34-09 la lista de actas por validar es solo del Coordinador: docente, jefe y estudiante reciben 403', function (string $rol) {
    $e = escenario();

    actingAs($e[$rol])->get(route('actas'))->assertForbidden();
})->with(['docente', 'jefe', 'estudiante']);

it('CP-RF34-10 avisa que la tabla está vacía en vez de dejar un cuerpo en blanco', function () {
    // <x-ui.table> comprobaba el hueco con isEmpty(), y un @foreach sin elementos deja saltos de línea:
    // el aviso no llegaba a salir nunca y una tabla que tardaba se confundía con una vacía.
    $e = escenario();
    actingAs($e['coordinador']);

    Livewire::test(ActasPorValidar::class)->set('estado', 'validated')->assertSee('No hay actas en este estado.');
});
