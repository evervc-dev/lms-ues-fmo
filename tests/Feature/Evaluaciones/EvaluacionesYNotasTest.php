<?php

use App\Modules\Calificaciones\Livewire\Acta;
use App\Modules\Calificaciones\Livewire\ActasPorValidar;
use App\Modules\Calificaciones\Livewire\Cierre;
use App\Modules\Calificaciones\Livewire\NotasSeccion;
use App\Modules\Calificaciones\Services\CalificacionService;
use App\Modules\Evaluaciones\Livewire\Cuestionario;
use App\Modules\Evaluaciones\Livewire\Entregas;
use App\Modules\Evaluaciones\Livewire\Intento;
use App\Modules\Evaluaciones\Livewire\NuevaPregunta;
use App\Modules\Evaluaciones\Livewire\NuevaTarea;
use App\Modules\Evaluaciones\Livewire\NuevoCuestionario;
use App\Modules\Evaluaciones\Livewire\Tarea;
use App\Modules\Evaluaciones\Services\ActividadService;
use App\Modules\Evaluaciones\Services\CuestionarioService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake());

/** Tres cómputos de 30/30/40 en la sección del escenario. */
function computos(array $e): array
{
    return DbContext::system(fn () => collect([[1, 30], [2, 30], [3, 40]])->map(fn ($c) => DB::table('grading_components')->insertGetId([
        'section_id' => $e['seccion'], 'number' => $c[0], 'name' => "Cómputo {$c[0]}", 'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11', 'weight_percent' => $c[1], 'created_at' => now(), 'updated_at' => now(),
    ]))->all());
}

/** Crea una tarea como el docente y devuelve su ulid. */
function tareaDe(array $e, int $componente, array $cambios = []): string
{
    actingAs($e['docente']);
    $t = Livewire::test(NuevaTarea::class, ['seccion' => $e['ulid']])->set('titulo', 'Tarea 1')->set('componente', $componente)->set('peso', 50)
        ->set('abre', now()->subDay()->format('Y-m-d\TH:i'))->set('cierra', now()->addDay()->format('Y-m-d\TH:i'))->set('extensiones', 'pdf');
    foreach ($cambios as $k => $v) {
        $t->set($k, $v);
    }
    $t->call('guardar')->assertHasNoErrors();

    return (string) DB::table('assessments')->where('title', 'Tarea 1')->value('ulid');
}

it('CP-RF15-04 lets the student submit a task and rejects other extensions', function () {
    $e = escenario();
    $tarea = tareaDe($e, computos($e)[0]);
    actingAs($e['estudiante']);

    Livewire::test(Tarea::class, ['seccion' => $e['ulid'], 'tarea' => $tarea])->set('archivo', UploadedFile::fake()->create('x.exe', 10))->call('entregar')->assertHasErrors(['archivo']);
    Livewire::test(Tarea::class, ['seccion' => $e['ulid'], 'tarea' => $tarea])->set('archivo', UploadedFile::fake()->create('informe.pdf', 100))->call('entregar')->assertHasNoErrors();

    expect(DB::table('submissions')->where('enrollment_id', $e['matricula'])->value('is_late'))->toBeFalsy();
});

it('CP-RF15-04 rechaza el archivo que pasa del peso permitido con un mensaje claro', function () {
    $e = escenario();
    $tarea = tareaDe($e, computos($e)[0], ['mb' => 1]);
    actingAs($e['estudiante']);

    Livewire::test(Tarea::class, ['seccion' => $e['ulid'], 'tarea' => $tarea])->set('archivo', UploadedFile::fake()->create('informe.pdf', 2048))
        ->call('entregar')->assertHasErrors(['archivo' => __('El archivo supera :n MB.', ['n' => 1])]);

    expect(DB::table('submissions')->count())->toBe(0);
});

it('CP-RF15-01 rechaza la entrega un minuto después del cierre estricto', function () {
    $e = escenario();
    $tarea = tareaDe($e, computos($e)[0], ['politica' => 'strict']);
    DbContext::system(fn () => DB::table('assessments')->where('ulid', $tarea)->update(['closes_at' => now()->subMinute()]));
    actingAs($e['estudiante']);

    Livewire::test(Tarea::class, ['seccion' => $e['ulid'], 'tarea' => $tarea])->set('archivo', UploadedFile::fake()->create('informe.pdf', 100))
        ->call('entregar')->assertHasErrors(['archivo' => __('La tarea ya cerró.')]);

    expect(DB::table('submissions')->count())->toBe(0);
});

it('CP-RF15-03 descuenta la penalización de la entrega tardía al calificar y se la muestra al estudiante', function () {
    $e = escenario();
    $tarea = tareaDe($e, computos($e)[0], ['politica' => 'grace_penalty', 'penalizacion' => 20, 'gracia' => now()->addDays(3)->format('Y-m-d\TH:i')]);
    DbContext::system(fn () => DB::table('assessments')->where('ulid', $tarea)->update(['opens_at' => now()->subDays(3), 'closes_at' => now()->subHour()]));
    actingAs($e['estudiante']);
    Livewire::test(Tarea::class, ['seccion' => $e['ulid'], 'tarea' => $tarea])->set('archivo', UploadedFile::fake()->create('tarde.pdf', 50))->call('entregar');

    actingAs($e['docente']);
    Livewire::test(Entregas::class, ['seccion' => $e['ulid'], 'tarea' => $tarea])->call('abrir', DB::table('submissions')->value('id'))->set('nota', 9)->call('calificar');

    expect((float) DB::table('grades')->value('score'))->toEqual(7.2)
        ->and((float) DB::table('grades')->value('score_before_penalty'))->toEqual(9.0);

    actingAs($e['estudiante']);
    Livewire::test(Tarea::class, ['seccion' => $e['ulid'], 'tarea' => $tarea])->assertSee('penalización 20 %');
});

it('CP-RF15-02 acepta la entrega dentro de la gracia, la marca tardía y aplica su penalización', function () {
    $e = escenario();
    $tarea = tareaDe($e, computos($e)[0], ['politica' => 'grace_penalty', 'penalizacion' => 10, 'gracia' => now()->addDays(3)->format('Y-m-d\TH:i')]);
    DbContext::system(fn () => DB::table('assessments')->where('ulid', $tarea)->update(['opens_at' => now()->subDays(3), 'closes_at' => now()->subHour()]));
    actingAs($e['estudiante']);

    Livewire::test(Tarea::class, ['seccion' => $e['ulid'], 'tarea' => $tarea])->set('archivo', UploadedFile::fake()->create('tarde.pdf', 50))->call('entregar')->assertHasNoErrors();

    $entrega = DB::table('submissions')->first();
    expect((bool) $entrega->is_late)->toBeTrue()->and((float) $entrega->late_penalty_applied)->toEqual(10.0);

    actingAs($e['docente']);
    Livewire::test(Entregas::class, ['seccion' => $e['ulid'], 'tarea' => $tarea])->call('abrir', $entrega->id)->set('nota', 8)->call('calificar');
    expect((float) DB::table('grades')->value('score'))->toEqual(7.2);
});

it('CP-RF15-07 CP-RF17-10 oculta los nombres en la calificación anónima hasta publicar las notas y luego los muestra en la boleta', function () {
    $e = escenario();
    $tarea = tareaDe($e, computos($e)[0], ['anonima' => true]);
    actingAs($e['estudiante']);
    Livewire::test(Tarea::class, ['seccion' => $e['ulid'], 'tarea' => $tarea])->set('archivo', UploadedFile::fake()->create('a.pdf', 10))->call('entregar');

    actingAs($e['docente']);
    $nombre = $e['estudiante']->first_name.' '.$e['estudiante']->last_name;
    Livewire::test(Entregas::class, ['seccion' => $e['ulid'], 'tarea' => $tarea])->assertSee('Estudiante 1')->assertDontSee($nombre)
        ->call('abrir', DB::table('submissions')->value('id'))->set('nota', 9)->call('calificar')
        ->call('publicar')->assertSee($nombre);

    actingAs($e['estudiante']);
    Livewire::test(NotasSeccion::class, ['seccion' => $e['ulid']])->assertSee('Mi boleta')->assertSee('9.0');
});

it('runs a quiz attempt in the exam layout and grades it automatically', function () {
    $e = escenario();
    $c = computos($e);
    actingAs($e['docente']);
    $banco = app(CuestionarioService::class)->crearBanco($e['docente'], 'Banco PRN', $e['prn']);
    Livewire::test(NuevaPregunta::class, ['banco' => $banco])->set('tipo', 'true_false')->set('enunciado', 'PHP es interpretado')->set('correcta', 0)->call('agregar')->assertHasNoErrors();
    Livewire::test(NuevoCuestionario::class, ['seccion' => $e['ulid']])->set('titulo', 'Parcial 1')->set('componente', $c[1])->set('banco', $banco)->set('cantidad', 1)
        ->set('abre', now()->subHour()->format('Y-m-d\TH:i'))->set('cierra', now()->addHour()->format('Y-m-d\TH:i'))->call('guardar')->assertHasNoErrors();
    $ulid = (string) DB::table('assessments')->where('title', 'Parcial 1')->value('ulid');

    actingAs($e['estudiante']);
    Livewire::test(Cuestionario::class, ['seccion' => $e['ulid'], 'cuestionario' => $ulid])->call('comenzar')->assertRedirect();
    $intento = (string) DB::table('quiz_attempts')->value('ulid');
    $this->get(route('intentos.show', $intento))->assertOk()->assertSeeText('PHP es interpretado')->assertDontSee('Menú principal');

    $respuesta = DB::table('attempt_answers')->value('id');
    $verdadero = DB::table('question_options')->where('text', 'Verdadero')->value('id');
    Livewire::test(Intento::class, ['intento' => $intento])->set("opciones.{$respuesta}", $verdadero)->call('terminar');

    expect(DB::table('quiz_attempts')->value('status'))->toBe('graded')
        ->and((float) DB::table('quiz_attempts')->value('score'))->toEqual(10.0)
        ->and(DB::table('grades')->value('status'))->toBe('draft');
});

it('CP-RF16-01 da a cada estudiante 10 preguntas al azar de un banco de 50', function () {
    $e = escenario();
    $c = computos($e);
    $otro = usuarioCon('estudiante');
    DbContext::system(fn () => DB::table('enrollments')->insert(['student_id' => $otro->id, 'section_id' => $e['seccion'], 'period_id' => $e['periodo'], 'subject_id' => $e['prn'],
        'status' => 'active', 'enrolled_via' => 'key', 'enrolled_by' => $otro->id, 'created_at' => now(), 'updated_at' => now()]));
    actingAs($e['docente']);
    $banco = app(CuestionarioService::class)->crearBanco($e['docente'], 'Banco PRN', $e['prn']);
    $preguntas = DbContext::system(fn () => collect(range(1, 50))->map(fn (int $n) => DB::table('questions')->insertGetId([
        'bank_id' => $banco, 'question_type' => 'true_false', 'statement' => "Afirmación {$n}", 'created_at' => now(), 'updated_at' => now(),
    ]))->all());
    Livewire::test(NuevoCuestionario::class, ['seccion' => $e['ulid']])->set('titulo', 'Parcial 1')->set('componente', $c[1])->set('banco', $banco)->set('cantidad', 10)
        ->set('abre', now()->subHour()->format('Y-m-d\TH:i'))->set('cierra', now()->addHour()->format('Y-m-d\TH:i'))->call('guardar')->assertHasNoErrors();
    $ulid = (string) DB::table('assessments')->where('title', 'Parcial 1')->value('ulid');

    $recibidas = [];
    foreach ([$e['estudiante'], $otro] as $estudiante) {
        actingAs($estudiante);
        $intento = app(CuestionarioService::class)->iniciar($estudiante, $e['seccion'], $ulid);
        $recibidas[] = DB::table('attempt_answers')->join('quiz_attempts', 'quiz_attempts.id', '=', 'attempt_answers.attempt_id')
            ->where('quiz_attempts.ulid', $intento)->pluck('question_id')->map(fn ($id) => (int) $id)->all();
    }

    foreach ($recibidas as $ids) {
        expect($ids)->toHaveCount(10)
            ->and(array_unique($ids))->toHaveCount(10)
            ->and(array_diff($ids, $preguntas))->toBeEmpty();
    }
    // Dos selecciones de 10 entre 50 coinciden con probabilidad menor que 1 en 10 mil millones.
    expect($recibidas[0])->not->toEqualCanonicalizing($recibidas[1]);
});

it('CP-RF16-05 muestra al docente el porcentaje de aciertos por pregunta al cerrar el cuestionario', function () {
    $e = escenario();
    $c = computos($e);
    actingAs($e['docente']);
    $banco = app(CuestionarioService::class)->crearBanco($e['docente'], 'Banco PRN', $e['prn']);
    Livewire::test(NuevaPregunta::class, ['banco' => $banco])->set('tipo', 'true_false')->set('enunciado', 'PHP es interpretado')->set('correcta', 0)->call('agregar')->assertHasNoErrors();
    Livewire::test(NuevoCuestionario::class, ['seccion' => $e['ulid']])->set('titulo', 'Parcial 1')->set('componente', $c[1])->set('banco', $banco)->set('cantidad', 1)
        ->set('abre', now()->subHour()->format('Y-m-d\TH:i'))->set('cierra', now()->addHour()->format('Y-m-d\TH:i'))->call('guardar')->assertHasNoErrors();
    $ulid = (string) DB::table('assessments')->where('title', 'Parcial 1')->value('ulid');

    actingAs($e['estudiante']);
    Livewire::test(Cuestionario::class, ['seccion' => $e['ulid'], 'cuestionario' => $ulid])->call('comenzar');
    $intento = (string) DB::table('quiz_attempts')->value('ulid');
    $falso = DB::table('question_options')->where('text', 'Falso')->value('id');
    Livewire::test(Intento::class, ['intento' => $intento])->set('opciones.'.DB::table('attempt_answers')->value('id'), $falso)->call('terminar');

    actingAs($e['docente']);
    Livewire::test(Cuestionario::class, ['seccion' => $e['ulid'], 'cuestionario' => $ulid])->assertDontSee('Aciertos por pregunta');
    $this->travel(2)->hours();
    Livewire::test(Cuestionario::class, ['seccion' => $e['ulid'], 'cuestionario' => $ulid])
        ->assertSee('Aciertos por pregunta')->assertSee('PHP es interpretado')->assertSee('0 de 1 respuestas');
});

it('CP-RF17-03 computes the weighted final grade with missing grades as zero', function () {
    $e = escenario();
    $c = computos($e);
    DbContext::system(function () use ($e, $c) {
        foreach ([[$c[0], 10.0], [$c[1], 5.0]] as [$componente, $nota]) {
            $item = DB::table('grade_items')->insertGetId(['component_id' => $componente, 'section_id' => $e['seccion'], 'item_type' => 'manual', 'title' => 'Item', 'weight_percent' => 100, 'is_published' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('grades')->insert(['grade_item_id' => $item, 'enrollment_id' => $e['matricula'], 'section_id' => $e['seccion'], 'score' => $nota, 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('grade_items')->insert(['component_id' => $c[2], 'section_id' => $e['seccion'], 'item_type' => 'manual', 'title' => 'Sin nota', 'weight_percent' => 100, 'is_published' => true, 'created_at' => now(), 'updated_at' => now()]);
    });

    // 0.30 × 10 + 0.30 × 5 + 0.40 × 0 = 4.5
    expect(app(CalificacionService::class)->libro($e['docente'], $e['seccion'])['filas']->first()->final)->toEqual(4.5);
});

it('CP-RF17-02 calcula la nota final con la ponderación y redondea a 1 decimal, mitad hacia arriba', function () {
    $e = escenario();
    $c = computos($e);
    DbContext::system(function () use ($e, $c) {
        foreach ([[$c[0], 8.5], [$c[1], 7.0], [$c[2], 6.0]] as [$componente, $nota]) {
            $item = DB::table('grade_items')->insertGetId(['component_id' => $componente, 'section_id' => $e['seccion'], 'item_type' => 'manual', 'title' => 'Item', 'weight_percent' => 100, 'is_published' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('grades')->insert(['grade_item_id' => $item, 'enrollment_id' => $e['matricula'], 'section_id' => $e['seccion'], 'score' => $nota, 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        }
    });

    // 0.30 × 8.5 + 0.30 × 7.0 + 0.40 × 6.0 = 7.05 → 7.1
    expect(app(CalificacionService::class)->libro($e['docente'], $e['seccion'])['filas']->first()->final)->toEqual(7.1)
        ->and(app(CalificacionService::class)->boleta($e['estudiante'], $e['seccion'])['final'])->toEqual(7.1);
});

it('CP-RF17-09 muestra al estudiante su boleta por cómputo y por unidad', function () {
    $e = escenario();
    $c = computos($e);
    DbContext::system(function () use ($e, $c) {
        $unidad = DB::table('units')->insertGetId(['section_id' => $e['seccion'], 'title' => 'Unidad 2 · Recursividad', 'position' => 2, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([[8.0, $unidad], [6.0, $unidad], [9.0, null]] as [$nota, $unidadId]) {
            $item = DB::table('grade_items')->insertGetId(['component_id' => $c[0], 'section_id' => $e['seccion'], 'unit_id' => $unidadId, 'item_type' => 'manual', 'title' => 'Item', 'weight_percent' => 30, 'is_published' => true, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('grades')->insert(['grade_item_id' => $item, 'enrollment_id' => $e['matricula'], 'section_id' => $e['seccion'], 'score' => $nota, 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        }
    });

    $boleta = app(CalificacionService::class)->boleta($e['estudiante'], $e['seccion']);
    expect($boleta['porUnidad']->all())->toEqual(['Unidad 2 · Recursividad' => 7.0])
        ->and($boleta['computos']->pluck('name')->all())->toBe(['Cómputo 1', 'Cómputo 2', 'Cómputo 3']);

    actingAs($e['estudiante']);
    Livewire::test(NotasSeccion::class, ['seccion' => $e['ulid']])->assertSee('Por unidad')->assertSee('Unidad 2 · Recursividad')->assertSee('Cómputo 1');
});

it('CP-RF24-03 muestra al docente la bandeja de tareas y ensayos por calificar de sus secciones', function () {
    $e = escenario();
    $c = computos($e);
    $tarea = tareaDe($e, $c[0]);
    actingAs($e['estudiante']);
    Livewire::test(Tarea::class, ['seccion' => $e['ulid'], 'tarea' => $tarea])->set('archivo', UploadedFile::fake()->create('informe.pdf', 50))->call('entregar');

    actingAs($e['docente']);
    $banco = app(CuestionarioService::class)->crearBanco($e['docente'], 'Banco PRN', $e['prn']);
    Livewire::test(NuevaPregunta::class, ['banco' => $banco])->set('tipo', 'true_false')->set('enunciado', 'PHP es interpretado')->set('correcta', 0)->call('agregar');
    Livewire::test(NuevoCuestionario::class, ['seccion' => $e['ulid']])->set('titulo', 'Parcial 1')->set('componente', $c[1])->set('banco', $banco)->set('cantidad', 1)
        ->set('abre', now()->subHour()->format('Y-m-d\TH:i'))->set('cierra', now()->addHour()->format('Y-m-d\TH:i'))->call('guardar');
    actingAs($e['estudiante']);
    $intento = app(CuestionarioService::class)->iniciar($e['estudiante'], $e['seccion'], (string) DB::table('assessments')->where('title', 'Parcial 1')->value('ulid'));
    // Un intento con un ensayo queda esperando la nota del docente.
    DbContext::system(fn () => DB::table('quiz_attempts')->where('ulid', $intento)->update(['status' => 'pending_grading']));

    $bandeja = app(ActividadService::class)->pendientes($e['docente']);
    expect($bandeja->map(fn ($p) => $p->tipo.':'.$p->title)->all())->toEqualCanonicalizing(['tarea:Tarea 1', 'cuestionario:Parcial 1'])
        ->and(app(ActividadService::class)->pendientes(docenteAjeno($e)))->toBeEmpty();

    actingAs($e['docente'])->get(route('pendientes-de-calificar'))->assertOk()->assertSeeText(['Tarea 1', 'Parcial 1']);
});

it('CP-RF34-03 publishes the grade sheet and lets the coordinator validate it', function () {
    $e = escenario();
    // RF17: el acta se publica solo con los pesos al 100 % en ambos niveles: una actividad del 100 % por cómputo.
    DbContext::system(fn () => collect(computos($e))->each(fn (int $c, int $i) => DB::table('grade_items')->insert([
        'component_id' => $c, 'section_id' => $e['seccion'], 'item_type' => 'manual', 'title' => 'Parcial '.($i + 1), 'weight_percent' => 100,
        'is_published' => false, 'created_at' => now(), 'updated_at' => now(),
    ])));

    actingAs($e['docente']);
    Livewire::test(Acta::class, ['seccion' => $e['ulid']])->call('publicar');
    expect(DB::table('grade_sheets')->value('status'))->toBe('published');

    actingAs($e['jefe']);
    Livewire::test(Cierre::class)->call('autorizar')->assertDispatched('aviso', tipo: 'error');

    actingAs($e['coordinador']);
    Livewire::test(ActasPorValidar::class)->assertSee('PRN115')->call('validar', $e['seccion']);
    expect(DB::table('grade_sheets')->value('status'))->toBe('validated');
});

it('CP-RF34-06 lets the head authorize the closing with date and author once the grade sheets are validated', function () {
    $e = escenario();
    computos($e);
    DbContext::system(fn () => DB::table('grade_sheets')->insert([
        'section_id' => $e['seccion'], 'status' => 'validated', 'published_by' => $e['docente']->id, 'published_at' => now(),
        'validated_by' => $e['coordinador']->id, 'validated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]));

    actingAs($e['jefe']);
    Livewire::test(Cierre::class)->call('autorizar')->assertDispatched('aviso', tipo: 'exito');

    $cierre = DB::table('department_closures')->where('department_id', $e['dia'])->first();
    expect($cierre)->not->toBeNull()
        ->and((int) $cierre->authorized_by)->toBe($e['jefe']->id)
        ->and($cierre->authorized_at)->not->toBeNull();
});

it('CP-RF17-07 lets the coordinator correct a grade of a closed period with a reason', function () {
    $e = escenario();
    $c = computos($e);
    $nota = DbContext::system(function () use ($e, $c) {
        $item = DB::table('grade_items')->insertGetId(['component_id' => $c[0], 'section_id' => $e['seccion'], 'item_type' => 'manual', 'title' => 'Parcial', 'weight_percent' => 100, 'is_published' => true, 'created_at' => now(), 'updated_at' => now()]);
        $id = DB::table('grades')->insertGetId(['grade_item_id' => $item, 'enrollment_id' => $e['matricula'], 'section_id' => $e['seccion'], 'score' => 5, 'status' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('academic_periods')->where('id', $e['periodo'])->update(['status' => 'closed']);

        return $id;
    });

    actingAs($e['docente']);
    expect(fn () => app(CalificacionService::class)->calificar($e['docente'], (int) DB::table('grades')->value('grade_item_id'), $e['matricula'], 9))
        ->toThrow(ValidationException::class);

    actingAs($e['coordinador']);
    Livewire::test(NotasSeccion::class, ['seccion' => $e['ulid']])->call('abrirCorreccion', $nota)->set('nuevaNota', 6.5)->set('motivo', 'Revisión de examen.')->call('corregir')->assertHasNoErrors();
    expect((float) DB::table('grades')->where('id', $nota)->value('score'))->toEqual(6.5);
});

it('renders the evaluation screens for their roles', function () {
    $e = escenario();
    computos($e);

    actingAs($e['docente'])->get(route('secciones.actividades', ['seccion' => $e['ulid']]))->assertOk()->assertSeeText(['Cómputo 1', 'Nueva tarea']);
    actingAs($e['docente'])->get(route('pendientes-de-calificar'))->assertOk()->assertSeeText('Pendientes de calificar');
    actingAs($e['docente'])->get(route('bancos-de-preguntas'))->assertOk();
    actingAs($e['estudiante'])->get(route('mis-notas'))->assertOk()->assertSeeText(['Mis notas', 'PRN115']);
    actingAs($e['coordinador'])->get(route('secciones.notas', ['seccion' => $e['ulid']]))->assertOk()->assertSeeText('Libro de calificaciones');
    actingAs($e['estudiante'])->get(route('secciones.tareas.crear', ['seccion' => $e['ulid']]))->assertForbidden();
});

it('shows whole weights and rubric points without cutting their trailing zeros', function () {
    $e = escenario();
    $c = computos($e);
    actingAs($e['docente']);
    $tarea = tareaDe($e, $c[0], ['conRubrica' => true, 'criterios' => [['nombre' => 'Contenido', 'maximo' => 10]]]);

    // En SQLite un decimal entero llega como 30, no como 30.00: recortar ceros sin number_format lo dejaba en «3».
    $this->get(route('secciones.actividades', ['seccion' => $e['ulid']]))->assertOk()
        ->assertSeeText(['Cómputo 1 · 30 %', 'Cómputo 3 · 40 %', '50 % del cómputo']);
    $this->get(route('secciones.tareas.show', ['seccion' => $e['ulid'], 'tarea' => $tarea]))->assertOk()->assertSeeText('50 % del cómputo');
    $this->get(route('secciones.tareas.crear', ['seccion' => $e['ulid']]))->assertOk()->assertSeeText('(30 %)');
    $this->get(route('secciones.notas', ['seccion' => $e['ulid']]))->assertOk()->assertSeeText('Cómputo 3 (40 %)');
    Livewire::test(Entregas::class, ['seccion' => $e['ulid'], 'tarea' => $tarea])->assertSee('Contenido (10 pts)')->assertSeeHtml('<td class="text-right">10</td>');

    actingAs($e['estudiante'])->get(route('secciones.notas', ['seccion' => $e['ulid']]))->assertOk()->assertSeeText(['Cómputo 3 · 40 %', '(50 %)']);
});
