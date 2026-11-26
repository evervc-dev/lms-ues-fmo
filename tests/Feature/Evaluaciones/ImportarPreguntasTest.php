<?php

use App\Modules\Evaluaciones\Livewire\Banco;
use App\Modules\Evaluaciones\Services\ImportacionPreguntasService;
use App\Support\DbContext;
use App\Support\HojaCalculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function bancoDelDocente(array $e): int
{
    return DbContext::system(fn () => DB::table('question_banks')->insertGetId([
        'owner_id' => $e['docente']->id, 'subject_id' => $e['prn'], 'name' => 'Banco de prueba', 'created_at' => now(), 'updated_at' => now(),
    ]));
}

/** Un .xlsx con el formato y esas filas, como lo subiría el docente. */
function libroDePreguntas(array $filas): UploadedFile
{
    $ruta = tempnam(sys_get_temp_dir(), 'preguntas').'.xlsx';
    app(ImportacionPreguntasService::class)->escribir($ruta, $filas);

    return UploadedFile::fake()->createWithContent('preguntas.xlsx', (string) file_get_contents($ruta));
}

it('CP-RF16-10 descarga el formato con encabezados, instrucciones y un ejemplo de cada tipo', function () {
    $e = escenario();
    actingAs($e['docente']);

    $respuesta = $this->get(route('bancos-de-preguntas.formato'))->assertOk()->assertDownload('formato-banco-de-preguntas.xlsx');
    $filas = HojaCalculo::leer($respuesta->getFile()->getPathname(), 'xlsx');

    // La primera hoja es la que se importa: solo los encabezados, sin ejemplos que se colarían.
    expect($filas)->toBe([ImportacionPreguntasService::COLUMNAS]);

    actingAs($e['estudiante'])->get(route('bancos-de-preguntas.formato'))->assertForbidden();
});

it('CP-RF16-11 importa preguntas de los 4 tipos con sus opciones, la correcta y las respuestas aceptadas', function () {
    $e = escenario();
    $banco = bancoDelDocente($e);
    actingAs($e['docente']);

    $pantalla = Livewire::test(Banco::class, ['banco' => $banco])
        ->call('abrirImportacion')
        ->set('archivo', libroDePreguntas(ImportacionPreguntasService::ejemplos()))
        ->assertSet('previa.total', 4)
        ->assertSet('previa.errores', [])
        ->assertSee('4 preguntas listas para importar');

    $pantalla->call('importar')->assertHasNoErrors()->assertDispatched('cerrar-modal', 'importar-preguntas');

    $preguntas = DB::table('questions')->where('bank_id', $banco)->orderBy('id')->get();
    expect($preguntas->pluck('question_type')->all())->toBe(['multiple_choice', 'true_false', 'short_answer', 'essay'])
        ->and($preguntas[0]->topic)->toBe('SQL')
        ->and(DB::table('question_options')->where('question_id', $preguntas[0]->id)->where('is_correct', true)->value('text'))->toBe('INSERT')
        ->and(DB::table('question_options')->where('question_id', $preguntas[1]->id)->where('is_correct', true)->value('text'))->toBe('Falso')
        ->and(json_decode($preguntas[2]->accepted_answers, true))->toBe(['SQL', 'Structured Query Language'])
        ->and(DB::table('question_options')->where('question_id', $preguntas[3]->id)->count())->toBe(0);
});

it('CP-RF16-12 lista los errores por fila y no importa nada si alguna fila es inválida', function () {
    $e = escenario();
    $banco = bancoDelDocente($e);
    actingAs($e['docente']);
    $filas = [
        ImportacionPreguntasService::ejemplos()[0],
        ['', 'Sin tipo'],
        ['OM', '¿Cuál?', '', 'Uno', 'Dos', '', '', '', '', 'C'],
        ['VF', '¿Seguro?', '', '', '', '', '', '', '', 'Tal vez'],
        ['Respuesta corta', '¿Siglas?'],
        ['Pregunta abierta', 'Tipo que no existe'],
    ];

    Livewire::test(Banco::class, ['banco' => $banco])
        ->set('archivo', libroDePreguntas($filas))
        ->assertSet('previa.total', 1)
        ->assertSet('previa.errores', [
            'Fila 3: falta el tipo.',
            'Fila 4: la opción C marcada como correcta está vacía.',
            'Fila 5: en «correcta» escriba Verdadero o Falso.',
            'Fila 6: una pregunta de respuesta corta necesita al menos una respuesta aceptada.',
            'Fila 7: el tipo «Pregunta abierta» no existe; use Opción múltiple, Verdadero / falso, Respuesta corta o Ensayo.',
        ])
        ->call('importar')->assertHasErrors(['archivo']);

    expect(DB::table('questions')->where('bank_id', $banco)->count())->toBe(0);

    // Otro docente no ve el banco: no puede importar a él.
    $otro = usuarioCon('docente');
    $archivo = libroDePreguntas(ImportacionPreguntasService::ejemplos());
    expect(fn () => app(ImportacionPreguntasService::class)->importar($otro, $banco, $archivo->getRealPath(), 'xlsx'))
        ->toThrow(NotFoundHttpException::class);
});

it('CP-RF16-12 rechaza un archivo sin las columnas del formato', function () {
    $ruta = tempnam(sys_get_temp_dir(), 'preguntas');
    file_put_contents($ruta, "pregunta,respuesta\n¿Algo?,Sí\n");

    expect(fn () => app(ImportacionPreguntasService::class)->analizar($ruta, 'csv'))
        ->toThrow(ValidationException::class, 'Al archivo le faltan columnas (tipo, enunciado).');
});
