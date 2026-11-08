<?php

use App\Modules\Comunicacion\Livewire\Foro;
use App\Modules\Comunicacion\Livewire\HiloForo;
use App\Modules\Comunicacion\Notifications\Aviso;
use App\Modules\Comunicacion\Services\ForoService;
use App\Modules\Contenidos\Livewire\ContenidosSeccion;
use App\Modules\Contenidos\Livewire\Importar;
use App\Modules\Contenidos\Livewire\NuevoRecurso;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/** Una unidad con un recurso de lectura manual, en la sección del escenario o en la que se indique. */
function unidadConRecurso(array $e, string $completion = 'manual', ?int $seccion = null): array
{
    return DbContext::system(function () use ($e, $completion, $seccion) {
        $unidad = DB::table('units')->insertGetId(['section_id' => $seccion ?? $e['seccion'], 'title' => 'Unidad 1 · Fundamentos', 'position' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $ulid = (string) str()->ulid();
        $recurso = DB::table('resources')->insertGetId(['ulid' => $ulid, 'unit_id' => $unidad, 'resource_type' => 'page', 'title' => 'Lectura guiada', 'html_content' => '<p>Hola</p>',
            'completion_mode' => $completion, 'position' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return compact('unidad', 'recurso', 'ulid');
    });
}

it('CP-RF18-01 muestra las unidades al estudiante matriculado y cuenta como completado lo que marca como leído', function () {
    $e = escenario();
    $c = unidadConRecurso($e);
    actingAs($e['estudiante']);

    Livewire::test(ContenidosSeccion::class, ['seccion' => $e['ulid']])->assertSee('Unidad 1 · Fundamentos')->assertSee('Ya lo leí')->call('marcarLeido', $c['recurso']);

    expect(DB::table('resource_progress')->where('enrollment_id', $e['matricula'])->where('resource_id', $c['recurso'])->value('origin'))->toBe('manual');
});

it('CP-RF18-03 muestra al estudiante una barra con el avance de cada unidad', function () {
    $e = escenario();
    $primera = unidadDeContenidos($e['seccion'], 'Unidad 1 · Fundamentos');
    $segunda = unidadDeContenidos($e['seccion'], 'Unidad 2 · Recursividad', 2);
    $leido = recursoEnUnidad($primera, ['title' => 'Lectura 1']);
    recursoEnUnidad($primera, ['title' => 'Lectura 2', 'position' => 2]);
    recursoEnUnidad($segunda, ['title' => 'Lectura 3']);
    DbContext::system(fn () => DB::table('resource_progress')->insert(['resource_id' => $leido['id'], 'enrollment_id' => $e['matricula'], 'origin' => 'manual']));
    actingAs($e['estudiante']);

    Livewire::test(ContenidosSeccion::class, ['seccion' => $e['ulid']])
        ->assertSeeHtml('aria-label="Avance de Unidad 1 · Fundamentos"')->assertSeeHtml('aria-valuenow="50"')
        ->assertSeeHtml('aria-label="Avance de Unidad 2 · Recursividad"')->assertSeeHtml('aria-valuenow="0"');

    actingAs($e['docente']);
    Livewire::test(ContenidosSeccion::class, ['seccion' => $e['ulid']])->assertDontSeeHtml('aria-label="Avance de Unidad 1');
});

it('CP-RF13-01 registra con fecha y hora la apertura del recurso y completa el automático', function () {
    $e = escenario();
    $c = unidadConRecurso($e, 'automatic');

    actingAs($e['estudiante'])->get(route('secciones.recurso', ['seccion' => $e['ulid'], 'recurso' => $c['ulid']]))->assertOk()->assertSeeText('Lectura guiada');

    $apertura = DB::table('resource_access_logs')->where('enrollment_id', $e['matricula'])->sole();
    expect(DB::table('resource_access_logs')->where('enrollment_id', $e['matricula'])->count())->toBe(1)
        ->and($apertura->resource_id)->toBe($c['recurso'])
        ->and($apertura->event_type)->toBe('open')
        ->and($apertura->occurred_at)->not->toBeNull()
        ->and(DB::table('resource_progress')->where('enrollment_id', $e['matricula'])->value('origin'))->toBe('rule');
});

it('CP-RF11-01 rechaza al estudiante no matriculado los contenidos y el recurso de esa sección', function () {
    $e = escenario();
    $ajeno = unidadConRecurso($e, 'manual', $e['otra']);

    actingAs($e['estudiante'])->get(route('secciones.contenidos', ['seccion' => $e['otraUlid']]))->assertForbidden();
    actingAs($e['estudiante'])->get(route('secciones.recurso', ['seccion' => $e['otraUlid'], 'recurso' => $ajeno['ulid']]))->assertForbidden();

    expect(DB::table('resource_access_logs')->count())->toBe(0);
});

it('CP-RF11-08 mueve a la papelera el recurso que el docente elimina y lo lista durante 30 días', function () {
    Storage::fake();
    $e = escenario();
    $c = unidadConRecurso($e);
    actingAs($e['docente']);

    Livewire::test(NuevoRecurso::class, ['seccion' => $e['ulid'], 'unidad' => $c['unidad']])
        ->set('tipo', 'file')->set('tituloRecurso', 'Guía 1')
        ->set('archivo', UploadedFile::fake()->create('guia.pdf', 100, 'application/pdf'))
        ->call('agregar')->assertHasNoErrors();
    Livewire::test(ContenidosSeccion::class, ['seccion' => $e['ulid']])->call('eliminar', $c['recurso']);

    expect(DB::table('resources')->where('title', 'Guía 1')->value('file_path'))->not->toBeNull()
        ->and(DB::table('resources')->where('id', $c['recurso'])->value('deleted_at'))->not->toBeNull();

    actingAs($e['docente'])->get(route('secciones.papelera', ['seccion' => $e['ulid']]))->assertOk()->assertSeeText('Lectura guiada');
});

it('CP-RF11-08 impide al estudiante abrir la papelera de la sección', function () {
    $e = escenario();

    actingAs($e['estudiante'])->get(route('secciones.papelera', ['seccion' => $e['ulid']]))->assertForbidden();
});

it('imports the units of a previous section of the same subject', function () {
    $e = escenario();
    $anterior = DbContext::system(function () use ($e) {
        $periodo = DB::table('academic_periods')->insertGetId(['name' => '2026-I', 'start_date' => '2026-02-02', 'end_date' => '2026-06-19', 'status' => 'closed', 'created_at' => now(), 'updated_at' => now()]);
        $seccion = DB::table('sections')->insertGetId(['ulid' => (string) str()->ulid(), 'period_id' => $periodo, 'subject_id' => $e['prn'], 'code' => '01', 'max_capacity' => 40, 'created_at' => now(), 'updated_at' => now()]);
        $unidad = DB::table('units')->insertGetId(['section_id' => $seccion, 'title' => 'Unidad heredada', 'position' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('resources')->insert(['ulid' => (string) str()->ulid(), 'unit_id' => $unidad, 'resource_type' => 'link', 'title' => 'Manual PHP', 'url' => 'https://php.net', 'completion_mode' => 'manual', 'position' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return $seccion;
    });
    actingAs($e['docente']);

    Livewire::test(Importar::class, ['seccion' => $e['ulid']])->set('origen', $anterior)->call('importar');

    expect(DB::table('units')->where('section_id', $e['seccion'])->value('title'))->toBe('Unidad heredada')
        ->and(DB::table('content_copies')->count())->toBe(1);
});

it('shows the teacher the access heat map', function () {
    $e = escenario();
    unidadConRecurso($e);

    actingAs($e['docente'])->get(route('secciones.analitica', ['seccion' => $e['ulid']]))->assertOk()->assertSeeText(['Mapa de calor', 'Lectura guiada']);
});

it('CP-RF30-01 y CP-RF30-03 anida la respuesta bajo su mensaje y el hilo cerrado no admite respuestas nuevas', function () {
    $e = escenario();
    actingAs($e['estudiante']);
    Livewire::test(Foro::class, ['seccion' => $e['ulid']])->set('titulo', 'Duda de recursividad')->set('mensaje', '¿Cómo se detiene?')->call('crear');
    $hilo = (int) DB::table('forum_threads')->value('id');
    $raiz = (int) DB::table('forum_posts')->value('id');

    actingAs($e['docente']);
    Livewire::test(HiloForo::class, ['seccion' => $e['ulid'], 'hilo' => $hilo])
        ->call('responderA', $raiz)->set('mensaje', 'Con el caso base.')->call('responder')->assertHasNoErrors()
        ->call('moderar', 'cerrar', null);

    expect(DB::table('forum_posts')->where('parent_post_id', $raiz)->value('body_html'))->toBe('Con el caso base.')
        ->and((bool) DB::table('forum_threads')->where('id', $hilo)->value('is_closed'))->toBeTrue();

    actingAs($e['estudiante']);
    Livewire::test(HiloForo::class, ['seccion' => $e['ulid'], 'hilo' => $hilo])->set('mensaje', 'Gracias')->call('responder')->assertHasErrors(['mensaje']);
});

it('CP-RF30-02 el mensaje que el docente oculta deja de verse para los estudiantes y se conserva', function () {
    $e = escenario();
    $foro = app(ForoService::class);
    actingAs($e['estudiante']);
    $hilo = $foro->crearHilo($e['estudiante'], $e['seccion'], 'Duda de recursividad', 'Mensaje fuera de lugar');
    $mensaje = (int) DB::table('forum_posts')->where('thread_id', $hilo)->value('id');

    actingAs($e['docente']);
    $foro->moderar($e['docente'], $e['seccion'], $hilo, 'ocultar', $mensaje);

    expect($foro->hilo($e['estudiante'], $e['seccion'], $hilo)['raiz'])->toBeEmpty()
        ->and($foro->hilo($e['docente'], $e['seccion'], $hilo)['raiz']->pluck('is_hidden')->map(fn ($oculto) => (bool) $oculto)->all())->toBe([true])
        ->and(DB::table('forum_posts')->where('id', $mensaje)->value('body_html'))->toBe('Mensaje fuera de lugar');
});

it('CP-RF30-02 el estudiante no puede ocultar mensajes del foro', function () {
    $e = escenario();
    $foro = app(ForoService::class);
    actingAs($e['estudiante']);
    $hilo = $foro->crearHilo($e['estudiante'], $e['seccion'], 'Duda', 'Mensaje');
    $mensaje = (int) DB::table('forum_posts')->where('thread_id', $hilo)->value('id');

    expect(fn () => $foro->moderar($e['estudiante'], $e['seccion'], $hilo, 'ocultar', $mensaje))->toThrow(AuthorizationException::class);

    expect((bool) DB::table('forum_posts')->where('id', $mensaje)->value('is_hidden'))->toBeFalse();
});

it('CP-RF30-04 avisa al autor del mensaje respondido por push y correo, pero no a quien responde', function () {
    Notification::fake();
    $e = escenario();
    $foro = app(ForoService::class);
    actingAs($e['estudiante']);
    $hilo = $foro->crearHilo($e['estudiante'], $e['seccion'], 'Duda de recursividad', '¿Cómo se detiene?');
    $raiz = (int) DB::table('forum_posts')->where('thread_id', $hilo)->value('id');

    actingAs($e['docente']);
    $foro->responder($e['docente'], $e['seccion'], $hilo, 'Con el caso base.', $raiz);

    Notification::assertSentTo($e['estudiante'], Aviso::class, fn (Aviso $aviso, array $canales) => $aviso->evento === 'forum.reply'
        && str_contains($aviso->cuerpo, 'respondió en «Duda de recursividad»') && $canales === ['broadcast', 'mail']);
    Notification::assertNotSentTo($e['docente'], Aviso::class);

    // El estudiante responde en su propio hilo: el aviso va al autor del hilo, que es él mismo, así que no sale.
    actingAs($e['estudiante']);
    $foro->responder($e['estudiante'], $e['seccion'], $hilo, 'Gracias.');
    Notification::assertSentToTimes($e['estudiante'], Aviso::class, 1);
});

it('CP-RF30-04 respeta la preferencia: con el correo de foros desactivado el aviso llega solo a la plataforma', function () {
    Notification::fake();
    $e = escenario();
    $foro = app(ForoService::class);
    DbContext::system(fn () => DB::table('notification_preferences')->insert(['user_id' => $e['estudiante']->id, 'event_code' => 'forum.reply', 'mail_enabled' => false]));
    actingAs($e['estudiante']);
    $hilo = $foro->crearHilo($e['estudiante'], $e['seccion'], 'Duda', 'Mensaje');

    actingAs($e['docente']);
    $foro->responder($e['docente'], $e['seccion'], $hilo, 'Respuesta');

    Notification::assertSentTo($e['estudiante'], Aviso::class, fn (Aviso $aviso, array $canales) => $aviso->evento === 'forum.reply' && $canales === ['broadcast']);
    expect(DB::table('notifications')->where('notifiable_id', $e['estudiante']->id)->where('event_code', 'forum.reply')->count())->toBe(1);
});
