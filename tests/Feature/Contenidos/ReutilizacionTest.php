<?php

use App\Models\User;
use App\Modules\Contenidos\Livewire\Importar;
use App\Modules\Contenidos\Services\ContenidoService;
use App\Modules\Evaluaciones\Livewire\Actividades;
use App\Modules\Evaluaciones\Services\ActividadService;
use App\Modules\Evaluaciones\Services\CuestionarioService;
use App\Modules\Evaluaciones\Services\RubricaService;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake());

/**
 * Reutilización de contenido entre períodos (RF14, ADR-066): unidades con sus recursos (cada archivo con su copia
 * física) y evaluaciones sin programar, de una sección de un período anterior. La rúbrica se copia con su tarea
 * (ADR-065) y el banco de otro docente se comparte (decidido por el usuario el 01/11/2026).
 */

/**
 * Sección de Programación I del período 2026-I con una unidad (archivo, carpeta y enlace), una tarea con rúbrica y
 * gracia ya calificada y un cuestionario sobre el banco de otro docente, con un intento.
 *
 * @return array{seccion: int, archivo: string, carpeta: string, banco: int, colega: User}
 */
function seccionAnterior(array $e): array
{
    $colega = docenteAjeno($e);

    return DbContext::system(function () use ($e, $colega) {
        $t = fn (array $fila) => $fila + ['created_at' => now(), 'updated_at' => now()];
        // Se siembra abierto y se cierra al final: en PostgreSQL un trigger no deja escribir notas en un período cerrado.
        $periodo = DB::table('academic_periods')->insertGetId($t(['name' => '2026-I', 'start_date' => '2026-02-02', 'end_date' => '2026-06-19', 'status' => 'planned']));
        $seccion = DB::table('sections')->insertGetId($t(['ulid' => (string) Str::ulid(), 'period_id' => $periodo, 'subject_id' => $e['prn'], 'code' => '01', 'max_capacity' => 40]));
        $matricula = DB::table('enrollments')->insertGetId($t(['student_id' => $e['estudiante']->id, 'section_id' => $seccion, 'period_id' => $periodo, 'subject_id' => $e['prn'],
            'status' => 'active', 'enrolled_via' => 'key', 'enrolled_by' => $e['estudiante']->id]));
        $unidad = DB::table('units')->insertGetId($t(['section_id' => $seccion, 'title' => 'Unidad heredada', 'position' => 1]));

        $archivo = "contenidos/{$seccion}/guia.pdf";
        Storage::put($archivo, 'guía de laboratorio');
        $carpeta = "contenidos/{$seccion}/carpetas/".Str::ulid();
        Storage::put("{$carpeta}/datos/ejemplo.csv", 'a,b');
        DB::table('resources')->insert($t(['ulid' => (string) Str::ulid(), 'unit_id' => $unidad, 'resource_type' => 'file', 'title' => 'Guía', 'file_path' => $archivo, 'pdf_mode' => 'viewer', 'completion_mode' => 'manual', 'position' => 1]));
        $recursoCarpeta = DB::table('resources')->insertGetId($t(['ulid' => (string) Str::ulid(), 'unit_id' => $unidad, 'resource_type' => 'folder', 'title' => 'Datos', 'file_path' => $carpeta, 'completion_mode' => 'manual', 'position' => 2]));
        DB::table('resource_files')->insert($t(['resource_id' => $recursoCarpeta, 'path' => 'datos/ejemplo.csv', 'name' => 'ejemplo.csv', 'bytes' => 3]));
        DB::table('resources')->insert($t(['ulid' => (string) Str::ulid(), 'unit_id' => $unidad, 'resource_type' => 'link', 'title' => 'Manual PHP', 'url' => 'https://php.net', 'completion_mode' => 'manual', 'position' => 3]));

        $componente = DB::table('grading_components')->insertGetId($t(['section_id' => $seccion, 'number' => 1, 'name' => 'Cómputo 1', 'starts_on' => '2026-02-02', 'ends_on' => '2026-06-19', 'weight_percent' => 100]));
        $item = DB::table('grade_items')->insertGetId($t(['component_id' => $componente, 'section_id' => $seccion, 'item_type' => 'assignment', 'title' => 'Informe', 'weight_percent' => 40, 'is_published' => true]));
        $rubrica = app(RubricaService::class)->insertar($e['docente']->id, 'Informe', [['nombre' => 'Contenido', 'maximo' => 6], ['nombre' => 'Forma', 'maximo' => 4]]);
        $tarea = DB::table('assessments')->insertGetId($t(['ulid' => (string) Str::ulid(), 'section_id' => $seccion, 'grade_item_id' => $item, 'assessment_type' => 'assignment', 'title' => 'Informe',
            'instructions' => 'Entregue el informe en PDF.', 'opens_at' => '2026-03-01 07:00', 'closes_at' => '2026-03-15 23:59', 'max_attempts' => 2, 'rubric_id' => $rubrica, 'is_anonymous' => true]));
        DB::table('assignment_settings')->insert(['assessment_id' => $tarea, 'is_group' => false, 'late_policy' => 'grace_penalty', 'grace_until' => '2026-03-17 23:59',
            'late_penalty_percent' => 10, 'allowed_extensions' => json_encode(['pdf']), 'max_file_mb' => 5]);
        DB::table('submissions')->insert($t(['ulid' => (string) Str::ulid(), 'assessment_id' => $tarea, 'section_id' => $seccion, 'enrollment_id' => $matricula, 'attempt_number' => 1,
            'status' => 'submitted', 'is_late' => false, 'late_penalty_applied' => 0, 'submitted_at' => '2026-03-10 10:00']));
        DB::table('grades')->insert($t(['grade_item_id' => $item, 'enrollment_id' => $matricula, 'section_id' => $seccion, 'score' => 8, 'status' => 'published']));

        $banco = DB::table('question_banks')->insertGetId($t(['owner_id' => $colega->id, 'subject_id' => $e['prn'], 'name' => 'Banco del colega']));
        DB::table('questions')->insert($t(['bank_id' => $banco, 'question_type' => 'true_false', 'statement' => 'PHP es interpretado']));
        $itemQuiz = DB::table('grade_items')->insertGetId($t(['component_id' => $componente, 'section_id' => $seccion, 'item_type' => 'quiz', 'title' => 'Parcial 1', 'weight_percent' => 60, 'is_published' => true]));
        $quiz = DB::table('assessments')->insertGetId($t(['ulid' => (string) Str::ulid(), 'section_id' => $seccion, 'grade_item_id' => $itemQuiz, 'assessment_type' => 'quiz', 'title' => 'Parcial 1',
            'opens_at' => '2026-04-01 08:00', 'closes_at' => '2026-04-01 10:00', 'max_attempts' => 1]));
        DB::table('quiz_settings')->insert(['assessment_id' => $quiz, 'time_limit_minutes' => 45, 'autosave_seconds' => 30, 'navigation' => 'linear', 'review_published' => true]);
        DB::table('quiz_question_rules')->insert($t(['assessment_id' => $quiz, 'bank_id' => $banco, 'quantity' => 1]));
        DB::table('quiz_attempts')->insert(['ulid' => (string) Str::ulid(), 'assessment_id' => $quiz, 'enrollment_id' => $matricula, 'attempt_number' => 1, 'started_at' => '2026-04-01 08:05', 'deadline_at' => '2026-04-01 08:50', 'status' => 'graded', 'score' => 10]);

        DB::table('academic_periods')->where('id', $periodo)->update(['status' => 'closed']);

        return ['seccion' => $seccion, 'archivo' => $archivo, 'carpeta' => $carpeta, 'banco' => $banco, 'colega' => $colega];
    });
}

/** Un cómputo vacío en la sección del período activo. */
function computoDestino(array $e): int
{
    return DbContext::system(fn () => DB::table('grading_components')->insertGetId(['section_id' => $e['seccion'], 'number' => 1, 'name' => 'Cómputo 1',
        'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11', 'weight_percent' => 100, 'created_at' => now(), 'updated_at' => now()]));
}

it('CP-RF14-01 copia las unidades con sus recursos, cada archivo con su propia copia, sin entregas ni notas', function () {
    $e = escenario();
    $anterior = seccionAnterior($e);
    $antes = ['entregas' => DB::table('submissions')->count(), 'notas' => DB::table('grades')->count(), 'intentos' => DB::table('quiz_attempts')->count()];

    actingAs($e['docente']);
    Livewire::test(Importar::class, ['seccion' => $e['ulid']])->set('origen', $anterior['seccion'])->call('importar')->assertHasNoErrors();

    $recursos = DB::table('resources')->join('units', 'units.id', '=', 'resources.unit_id')->where('units.section_id', $e['seccion'])->orderBy('resources.position')
        ->get(['resources.id', 'resources.resource_type', 'resources.file_path', 'resources.url']);
    expect(DB::table('units')->where('section_id', $e['seccion'])->value('title'))->toBe('Unidad heredada')
        ->and($recursos->pluck('resource_type')->all())->toBe(['file', 'folder', 'link'])
        ->and($recursos[0]->file_path)->not->toBe($anterior['archivo'])->toStartWith("contenidos/{$e['seccion']}/")
        ->and(Storage::get($recursos[0]->file_path))->toBe('guía de laboratorio')
        ->and($recursos[1]->file_path)->not->toBe($anterior['carpeta'])
        ->and(Storage::get($recursos[1]->file_path.'/datos/ejemplo.csv'))->toBe('a,b')
        ->and(DB::table('resource_files')->where('resource_id', $recursos[1]->id)->value('path'))->toBe('datos/ejemplo.csv')
        ->and($recursos[2]->url)->toBe('https://php.net')
        ->and(DB::table('submissions')->count())->toBe($antes['entregas'])
        ->and(DB::table('grades')->count())->toBe($antes['notas'])
        ->and(DB::table('quiz_attempts')->count())->toBe($antes['intentos'])
        ->and(DB::table('content_copies')->count())->toBe(1);

    // Borrar el archivo original ya no deja sin archivo a la sección nueva.
    Storage::delete($anterior['archivo']);
    expect(Storage::exists($recursos[0]->file_path))->toBeTrue();
});

it('CP-RF14-01 solo copia de un período anterior y no copia dos veces la misma sección', function () {
    $e = escenario();
    $anterior = seccionAnterior($e);
    $mismoPeriodo = DbContext::system(function () use ($e) {
        $seccion = DB::table('sections')->insertGetId(['ulid' => (string) Str::ulid(), 'period_id' => $e['periodo'], 'subject_id' => $e['prn'], 'code' => '02', 'max_capacity' => 40, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('units')->insert(['section_id' => $seccion, 'title' => 'Unidad del mismo ciclo', 'position' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return $seccion;
    });
    $contenidos = app(ContenidoService::class);

    expect($contenidos->origenesParaImportar($e['docente'], $e['seccion'])->pluck('id')->all())->toBe([$anterior['seccion']])
        ->and(fn () => $contenidos->importar($e['docente'], $mismoPeriodo, $e['seccion']))->toThrow(AuthorizationException::class);

    $contenidos->importar($e['docente'], $anterior['seccion'], $e['seccion']);
    expect((bool) $contenidos->origenesParaImportar($e['docente'], $e['seccion'])->first()->importada)->toBeTrue()
        ->and(fn () => $contenidos->importar($e['docente'], $anterior['seccion'], $e['seccion']))->toThrow(ValidationException::class, 'Ya importó el contenido de esa sección')
        ->and(DB::table('units')->where('section_id', $e['seccion'])->count())->toBe(1);
});

it('CP-RF14-01 solo el docente de la sección importa contenido', function () {
    $e = escenario();
    $anterior = seccionAnterior($e);
    $contenidos = app(ContenidoService::class);

    expect(fn () => $contenidos->importar($e['estudiante'], $anterior['seccion'], $e['seccion']))->toThrow(AuthorizationException::class)
        ->and(fn () => $contenidos->importar($anterior['colega'], $anterior['seccion'], $e['seccion']))->toThrow(AuthorizationException::class)
        ->and(DB::table('content_copies')->count())->toBe(0);
});

it('CP-RF14-02 la evaluación importada queda sin fechas ni cómputo y el estudiante no la ve', function () {
    $e = escenario();
    $anterior = seccionAnterior($e);
    app(ContenidoService::class)->importar($e['docente'], $anterior['seccion'], $e['seccion']);

    $copias = DB::table('assessments')->where('section_id', $e['seccion'])->orderBy('id')->get();
    $tarea = $copias->firstWhere('assessment_type', 'assignment');
    $quiz = $copias->firstWhere('assessment_type', 'quiz');
    expect($copias)->toHaveCount(2)
        ->and($copias->pluck('opens_at')->filter()->all())->toBe([])
        ->and($copias->pluck('closes_at')->filter()->all())->toBe([])
        ->and($copias->pluck('grade_item_id')->filter()->all())->toBe([])
        ->and($tarea->instructions)->toBe('Entregue el informe en PDF.')
        ->and((int) $tarea->max_attempts)->toBe(2)
        ->and((bool) $tarea->is_anonymous)->toBeTrue()
        ->and(DB::table('assignment_settings')->where('assessment_id', $tarea->id)->first(['late_policy', 'grace_until', 'max_file_mb']))
        ->toEqual((object) ['late_policy' => 'grace_penalty', 'grace_until' => null, 'max_file_mb' => 5])
        // La rúbrica se copia con la tarea (ADR-065): otra fila, mismos criterios.
        ->and($tarea->rubric_id)->not->toBeNull()
        ->and(DB::table('rubric_criteria')->where('rubric_id', $tarea->rubric_id)->orderBy('position')->pluck('name')->all())->toBe(['Contenido', 'Forma'])
        ->and(DB::table('rubrics')->count())->toBe(2)
        // El banco del colega se comparte con quien importa; la revisión vuelve a quedar sin publicar.
        ->and(DB::table('quiz_question_rules')->where('assessment_id', $quiz->id)->value('bank_id'))->toBe($anterior['banco'])
        ->and(DB::table('question_bank_shares')->where('bank_id', $anterior['banco'])->where('user_id', $e['docente']->id)->exists())->toBeTrue()
        ->and((bool) DB::table('quiz_settings')->where('assessment_id', $quiz->id)->value('review_published'))->toBeFalse();

    // El docente las ve para programarlas; el estudiante no las ve ni puede abrirlas.
    actingAs($e['docente']);
    Livewire::test(Actividades::class, ['seccion' => $e['ulid']])->assertSee('Sin programar')->assertSee('Informe')->assertSee('Parcial 1');
    $actividades = app(ActividadService::class);
    expect($actividades->sinProgramar($e['estudiante'], $e['seccion']))->toBeEmpty()
        ->and($actividades->porComputo($e['estudiante'], $e['seccion'])->flatMap(fn ($c) => $c->actividades))->toBeEmpty()
        ->and(fn () => $actividades->tarea($e['estudiante'], $e['seccion'], $tarea->ulid))->toThrow(HttpException::class)
        ->and(fn () => app(CuestionarioService::class)->iniciar($e['estudiante'], $e['seccion'], $quiz->ulid))->toThrow(HttpException::class);
    actingAs($e['estudiante'])->get(route('secciones.actividades', ['seccion' => $e['ulid']]))->assertOk()->assertDontSee('Informe')->assertDontSee('Sin programar');
});

it('CP-RF14-02 el docente programa la evaluación importada y desde entonces la ve el estudiante', function () {
    $e = escenario();
    $anterior = seccionAnterior($e);
    $componente = computoDestino($e);
    app(ContenidoService::class)->importar($e['docente'], $anterior['seccion'], $e['seccion']);
    $tarea = DB::table('assessments')->where('section_id', $e['seccion'])->where('assessment_type', 'assignment')->first();

    actingAs($e['docente']);
    $pantalla = Livewire::test(Actividades::class, ['seccion' => $e['ulid']])->call('abrirProgramar', $tarea->ulid)->assertSet('conGracia', true)
        ->set('abre', now()->subDay()->format('Y-m-d\TH:i'))->set('cierra', now()->addWeek()->format('Y-m-d\TH:i'))->set('componente', $componente)->set('peso', 40)
        ->call('programar')->assertHasErrors('gracia');
    $pantalla->set('gracia', now()->addWeek()->addDays(2)->format('Y-m-d\TH:i'))->set('peso', 140)->call('programar')->assertHasErrors('peso');
    $pantalla->set('peso', 40)->call('programar')->assertHasNoErrors();

    $programada = DB::table('assessments')->where('id', $tarea->id)->first();
    expect($programada->opens_at)->not->toBeNull()
        ->and((float) DB::table('grade_items')->where('id', $programada->grade_item_id)->value('weight_percent'))->toEqual(40.0)
        ->and(DB::table('assignment_settings')->where('assessment_id', $tarea->id)->value('grace_until'))->not->toBeNull()
        ->and(fn () => app(ActividadService::class)->programar($e['docente'], $e['seccion'], $tarea->ulid, ['abre' => now(), 'cierra' => now()->addDay(), 'componente' => $componente, 'peso' => 10]))
        ->toThrow(HttpException::class);

    $actividades = app(ActividadService::class)->porComputo($e['estudiante'], $e['seccion'])->flatMap(fn ($c) => $c->actividades);
    expect($actividades->pluck('title')->all())->toBe(['Informe'])
        ->and(fn () => app(ActividadService::class)->programar($anterior['colega'], $e['seccion'], (string) DB::table('assessments')->where('section_id', $e['seccion'])->where('assessment_type', 'quiz')->value('ulid'),
            ['abre' => now(), 'cierra' => now()->addDay(), 'componente' => $componente, 'peso' => 10]))->toThrow(HttpException::class);
});

it('CP-RF14-02 la base de datos exige las dos fechas o ninguna', function () {
    $e = escenario();

    expect(fn () => DbContext::system(fn () => DB::table('assessments')->insert(['ulid' => (string) Str::ulid(), 'section_id' => $e['seccion'], 'assessment_type' => 'quiz',
        'title' => 'A medias', 'opens_at' => now(), 'closes_at' => null, 'created_at' => now(), 'updated_at' => now()])))->toThrow(QueryException::class);
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'El CHECK assessments_schedule solo existe en PostgreSQL (ADR-033).');
