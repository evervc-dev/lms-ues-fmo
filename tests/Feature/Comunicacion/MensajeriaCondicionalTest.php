<?php

use App\Modules\Comunicacion\Jobs\EnviarMensajeMasivo;
use App\Modules\Comunicacion\Livewire\MensajeCondicional;
use App\Modules\Comunicacion\Livewire\MensajesEnviados;
use App\Modules\Comunicacion\Services\MensajeriaService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * RF31 · Mensajería condicional a estudiantes. Las pruebas usan el correo `array` y la cola síncrona del
 * entorno de pruebas: ninguna manda un correo de verdad.
 */

/** Matricula a un estudiante nuevo en la sección del escenario. */
function estudianteMatriculado(array $e, string $estado = 'active'): array
{
    $usuario = usuarioCon('estudiante');
    $matricula = DbContext::system(fn () => DB::table('enrollments')->insertGetId([
        'student_id' => $usuario->id, 'section_id' => $e['seccion'], 'period_id' => $e['periodo'], 'subject_id' => $e['prn'],
        'status' => $estado, 'enrolled_via' => 'key', 'enrolled_by' => $usuario->id, 'created_at' => now(), 'updated_at' => now(),
    ]));

    return ['usuario' => $usuario, 'matricula' => $matricula];
}

/** Una tarea con su actividad calificable; devuelve los ids del ítem y de la evaluación. */
function tareaConItem(array $e, string $titulo = 'Tarea 1'): array
{
    return DbContext::system(function () use ($e, $titulo) {
        $computo = DB::table('grading_components')->insertGetId(['section_id' => $e['seccion'], 'number' => 1, 'name' => 'Cómputo 1',
            'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11', 'weight_percent' => 100, 'created_at' => now(), 'updated_at' => now()]);
        $item = DB::table('grade_items')->insertGetId(['component_id' => $computo, 'section_id' => $e['seccion'], 'item_type' => 'assignment',
            'title' => $titulo, 'weight_percent' => 20, 'is_published' => false, 'created_at' => now(), 'updated_at' => now()]);
        $evaluacion = DB::table('assessments')->insertGetId(['ulid' => (string) str()->ulid(), 'section_id' => $e['seccion'], 'grade_item_id' => $item,
            'title' => $titulo, 'assessment_type' => 'assignment', 'opens_at' => now()->subWeek(), 'closes_at' => now()->subDay(),
            'max_attempts' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return ['item' => $item, 'evaluacion' => $evaluacion];
    });
}

function entregar(array $e, int $evaluacion, int $matricula): void
{
    DbContext::system(fn () => DB::table('submissions')->insert(['ulid' => (string) str()->ulid(), 'assessment_id' => $evaluacion,
        'section_id' => $e['seccion'], 'enrollment_id' => $matricula, 'attempt_number' => 1, 'status' => 'submitted', 'is_late' => false,
        'submitted_at' => now()->subDays(2), 'created_at' => now(), 'updated_at' => now()]));
}

function ponerNota(array $e, int $item, int $matricula, float $nota, string $estado = 'published'): void
{
    DbContext::system(fn () => DB::table('grades')->insert(['grade_item_id' => $item, 'enrollment_id' => $matricula, 'section_id' => $e['seccion'],
        'score' => $nota, 'score_before_penalty' => $nota, 'status' => $estado, 'published_at' => $estado === 'published' ? now() : null,
        'created_at' => now(), 'updated_at' => now()]));
}

function mensajeria(): MensajeriaService
{
    return app(MensajeriaService::class);
}

function correosEnviados(): Collection
{
    return collect(app('mailer')->getSymfonyTransport()->messages())->map(fn ($m) => $m->getOriginalMessage());
}

it('CP-RF31-01 con «no entregaron» lista solo a los matriculados sin entrega', function () {
    $e = escenario();
    $t = tareaConItem($e);
    $entrego = estudianteMatriculado($e);
    $noEntrego = estudianteMatriculado($e);
    $retirado = estudianteMatriculado($e, 'withdrawn');
    entregar($e, $t['evaluacion'], $entrego['matricula']);
    entregar($e, $t['evaluacion'], $e['matricula']);   // el estudiante del escenario también entregó

    actingAs($e['docente']);
    $lista = mensajeria()->destinatarios($e['docente'], $e['seccion'], $t['item'], 'not_submitted');

    expect($lista->pluck('id')->all())->toBe([$noEntrego['usuario']->id])
        ->and($lista->pluck('id'))->not->toContain($entrego['usuario']->id)
        ->and($lista->pluck('id'))->not->toContain($retirado['usuario']->id);
});

it('CP-RF31-02 con «nota menor a X» lista solo a quienes tienen nota publicada menor a X', function () {
    $e = escenario();
    $t = tareaConItem($e);
    $baja = estudianteMatriculado($e);
    $justa = estudianteMatriculado($e);
    $alta = estudianteMatriculado($e);
    $borrador = estudianteMatriculado($e);
    $sinNota = estudianteMatriculado($e);
    ponerNota($e, $t['item'], $baja['matricula'], 4.5);
    ponerNota($e, $t['item'], $justa['matricula'], 6.0);          // 6.0 no es menor a 6.0
    ponerNota($e, $t['item'], $alta['matricula'], 9.0);
    ponerNota($e, $t['item'], $borrador['matricula'], 3.0, 'draft');   // sin publicar: no cuenta

    actingAs($e['docente']);
    $lista = mensajeria()->destinatarios($e['docente'], $e['seccion'], $t['item'], 'score_below', 6.0);

    expect($lista->pluck('id')->all())->toBe([$baja['usuario']->id])
        ->and($lista->first()->nota)->toBe(4.5);
});

it('rechaza «no entregaron» sobre una actividad que no recibe entregas, y pide la nota en «nota menor a»', function () {
    $e = escenario();
    $computo = DbContext::system(fn () => DB::table('grading_components')->insertGetId(['section_id' => $e['seccion'], 'number' => 1, 'name' => 'C1',
        'starts_on' => '2026-07-27', 'ends_on' => '2026-12-11', 'weight_percent' => 100, 'created_at' => now(), 'updated_at' => now()]));
    $manual = DbContext::system(fn () => DB::table('grade_items')->insertGetId(['component_id' => $computo, 'section_id' => $e['seccion'],
        'item_type' => 'manual', 'title' => 'Participación', 'weight_percent' => 10, 'is_published' => false, 'created_at' => now(), 'updated_at' => now()]));

    expect(fn () => mensajeria()->destinatarios($e['docente'], $e['seccion'], $manual, 'not_submitted'))->toThrow(ValidationException::class)
        ->and(fn () => mensajeria()->destinatarios($e['docente'], $e['seccion'], $manual, 'score_below'))->toThrow(ValidationException::class);
});

it('CP-RF31-03 el docente ve la lista antes de enviar y puede quitar a alguien', function () {
    $e = escenario();
    $t = tareaConItem($e);
    $a = estudianteMatriculado($e);
    $b = estudianteMatriculado($e);
    actingAs($e['docente']);

    $asistente = Livewire::test(MensajeCondicional::class, ['seccion' => $e['ulid']])
        ->assertSet('paso', 1)
        ->set('item', $t['item'])->set('condicion', 'not_submitted')
        ->call('siguiente')->assertHasNoErrors()->assertSet('paso', 2);

    // Ve a todos los que cumplen la condición (incluido el estudiante del escenario) y los quita uno a uno.
    expect($asistente->get('destinatarios'))->toHaveCount(3);
    $asistente->call('alternar', $a['usuario']->id);
    expect($asistente->get('destinatarios')->pluck('id')->all())->not->toContain($a['usuario']->id)
        ->and($asistente->get('destinatarios'))->toHaveCount(2);

    $asistente->call('alternar', $a['usuario']->id);   // volver a ponerlo
    expect($asistente->get('destinatarios'))->toHaveCount(3);

    // Un id que no es candidato se ignora: no se puede «quitar» a quien no estaba.
    $asistente->call('alternar', 999999);
    expect($asistente->get('excluidos'))->toBe([]);
});

it('CP-RF31-03 no deja avanzar si el docente quitó a todos', function () {
    $e = escenario();
    $t = tareaConItem($e);
    entregar($e, $t['evaluacion'], $e['matricula']);   // todos entregaron menos uno
    $unico = estudianteMatriculado($e);
    actingAs($e['docente']);

    $asistente = Livewire::test(MensajeCondicional::class, ['seccion' => $e['ulid']])
        ->set('item', $t['item'])->call('siguiente')->assertSet('paso', 2)
        ->call('alternar', $unico['usuario']->id)
        ->call('siguiente')->assertHasErrors(['excluidos'])->assertSet('paso', 2);

    expect($asistente->get('destinatarios'))->toBeEmpty();
});

it('CP-RF31-04 cada estudiante recibe su propio correo, sin ver a los demás, y queda en el historial', function () {
    $e = escenario();
    $t = tareaConItem($e);
    $a = estudianteMatriculado($e);
    $b = estudianteMatriculado($e);
    entregar($e, $t['evaluacion'], $e['matricula']);
    actingAs($e['docente']);

    Livewire::test(MensajeCondicional::class, ['seccion' => $e['ulid']])
        ->set('item', $t['item'])->call('siguiente')->call('siguiente')
        ->set('asunto', 'Entrega pendiente')->set('texto', "Aún no recibimos su tarea.\n\nTiene hasta el viernes.")
        ->call('enviar')->assertHasNoErrors()->assertRedirect(route('secciones.mensajes', ['seccion' => $e['ulid']]));

    $correos = correosEnviados();
    $direcciones = [$a['usuario']->institutional_email, $b['usuario']->institutional_email];

    expect($correos)->toHaveCount(2);
    foreach ($correos as $correo) {
        $para = collect($correo->getTo())->map->getAddress()->all();
        $todo = $correo->getHeaders()->toString().$correo->getHtmlBody();

        expect($para)->toHaveCount(1)                                   // un solo destinatario por correo
            ->and($correo->getCc())->toBeEmpty()->and($correo->getBcc())->toBeEmpty()
            ->and($correo->getSubject())->toBe('Entrega pendiente')
            ->and($correo->getReplyTo()[0]->getAddress())->toBe($e['docente']->institutional_email);

        // Nadie ve a los otros destinatarios, ni en las cabeceras ni en el cuerpo.
        $otro = collect($direcciones)->reject(fn ($d) => $d === $para[0])->first();
        expect($todo)->not->toContain($otro);
    }
    expect(collect($correos)->flatMap(fn ($c) => collect($c->getTo())->map->getAddress())->sort()->values()->all())->toBe(collect($direcciones)->sort()->values()->all());

    // Historial: el mensaje, a quién y que ya se entregó.
    $historial = mensajeria()->historial($e['docente'], $e['seccion']);
    expect($historial)->toHaveCount(1)
        ->and($historial->first()->asunto)->toBe('Entrega pendiente')
        ->and($historial->first()->destinatarios)->toHaveCount(2)
        ->and($historial->first()->entregados)->toBe(2)
        ->and(DB::table('notifications')->where('event_code', 'mass_message.sent')->count())->toBe(2);

    Livewire::test(MensajesEnviados::class, ['seccion' => $e['ulid']])->assertSee('Entrega pendiente')->assertSee($a['usuario']->institutional_email);
});

it('el correo del docente no se puede silenciar con las preferencias (ADR-044)', function () {
    $e = escenario();
    $t = tareaConItem($e);
    $silenciado = estudianteMatriculado($e);
    entregar($e, $t['evaluacion'], $e['matricula']);
    DbContext::system(fn () => DB::table('notification_preferences')->insert(['user_id' => $silenciado['usuario']->id, 'event_code' => 'mass_message.sent', 'mail_enabled' => false]));

    mensajeria()->enviar($e['docente'], $e['seccion'], ['item' => $t['item'], 'condicion' => 'not_submitted', 'umbral' => null,
        'destinatarios' => [$silenciado['usuario']->id], 'asunto' => 'Aviso', 'texto' => 'Hola']);

    expect(correosEnviados())->toHaveCount(1);
});

it('dos mensajes seguidos del docente no se agrupan: cada uno dice algo distinto (ADR-044)', function () {
    $e = escenario();
    $t = tareaConItem($e);
    $a = estudianteMatriculado($e);
    entregar($e, $t['evaluacion'], $e['matricula']);

    foreach (['Primero', 'Segundo'] as $asunto) {
        mensajeria()->enviar($e['docente'], $e['seccion'], ['item' => $t['item'], 'condicion' => 'not_submitted', 'umbral' => null,
            'destinatarios' => [$a['usuario']->id], 'asunto' => $asunto, 'texto' => 'Texto de '.$asunto]);
    }

    $avisos = DB::table('notifications')->where('notifiable_id', $a['usuario']->id)->where('event_code', 'mass_message.sent')->get();
    expect($avisos)->toHaveCount(2)
        ->and($avisos->map(fn ($f) => json_decode($f->data, true)['title'])->sort()->values()->all())->toBe(['Primero', 'Segundo']);
});

it('el envío es idempotente: repetir el trabajo no repite correos ni avisos', function () {
    $e = escenario();
    $t = tareaConItem($e);
    $a = estudianteMatriculado($e);
    entregar($e, $t['evaluacion'], $e['matricula']);

    $id = mensajeria()->enviar($e['docente'], $e['seccion'], ['item' => $t['item'], 'condicion' => 'not_submitted', 'umbral' => null,
        'destinatarios' => [$a['usuario']->id], 'asunto' => 'Aviso', 'texto' => 'Hola']);

    // Simula un reintento de la cola: el trabajo vuelve a correr con todo ya entregado.
    DB::table('mass_message_recipients')->update(['delivered_at' => null]);
    EnviarMensajeMasivo::dispatchSync($id);

    expect(correosEnviados())->toHaveCount(1)
        ->and(DB::table('notifications')->where('event_code', 'mass_message.sent')->count())->toBe(1)
        ->and(DB::table('mass_message_recipients')->whereNull('delivered_at')->count())->toBe(0);
});

it('vuelve a calcular la lista al enviar: un id que no cumple la condición no recibe nada', function () {
    $e = escenario();
    $t = tareaConItem($e);
    $debe = estudianteMatriculado($e);
    $entrego = estudianteMatriculado($e);
    entregar($e, $t['evaluacion'], $entrego['matricula']);
    entregar($e, $t['evaluacion'], $e['matricula']);

    mensajeria()->enviar($e['docente'], $e['seccion'], ['item' => $t['item'], 'condicion' => 'not_submitted', 'umbral' => null,
        'destinatarios' => [$debe['usuario']->id, $entrego['usuario']->id], 'asunto' => 'Aviso', 'texto' => 'Hola']);

    expect(DB::table('mass_message_recipients')->pluck('user_id')->all())->toBe([$debe['usuario']->id]);
});

it('alcance S: solo el docente de la sección mensajea; otro docente, el estudiante y el coordinador no', function () {
    $e = escenario();
    $t = tareaConItem($e);
    $ruta = route('secciones.mensajes.crear', ['seccion' => $e['ulid']]);
    $lista = route('secciones.mensajes', ['seccion' => $e['ulid']]);

    // Permitido: el docente de la sección.
    actingAs($e['docente'])->get($ruta)->assertOk();
    actingAs($e['docente'])->get($lista)->assertOk();

    // Denegados por rol.
    actingAs($e['estudiante'])->get($ruta)->assertForbidden();
    actingAs($e['coordinador'])->get($ruta)->assertForbidden();
    actingAs($e['jefe'])->get($lista)->assertForbidden();

    // Denegado por alcance: es docente, pero no de esa sección.
    actingAs($e['docente'])->get(route('secciones.mensajes.crear', ['seccion' => $e['otraUlid']]))->assertForbidden();
    expect(fn () => mensajeria()->destinatarios($e['docente'], $e['otra'], $t['item'], 'not_submitted'))->toThrow(HttpException::class);
});

it('el libro de notas ofrece «Mensajear a estudiantes que…» solo a quien puede', function () {
    $e = escenario();

    actingAs($e['docente'])->get(route('secciones.notas', ['seccion' => $e['ulid']]))->assertOk()->assertSeeText('Mensajear a estudiantes que…');
    actingAs($e['coordinador'])->get(route('secciones.notas', ['seccion' => $e['ulid']]))->assertOk()->assertDontSeeText('Mensajear a estudiantes que…');
});

it('CP-RF31-03 respeta al enviar a quien el docente quitó de la lista', function () {
    $e = escenario();
    $t = tareaConItem($e);
    $queda = estudianteMatriculado($e);
    $quitado = estudianteMatriculado($e);
    entregar($e, $t['evaluacion'], $e['matricula']);
    actingAs($e['docente']);

    Livewire::test(MensajeCondicional::class, ['seccion' => $e['ulid']])
        ->set('item', $t['item'])->call('siguiente')
        ->call('alternar', $quitado['usuario']->id)
        ->call('siguiente')->set('asunto', 'Aviso')->set('texto', 'Hola')
        ->call('enviar')->assertHasNoErrors();

    expect(DB::table('mass_message_recipients')->pluck('user_id')->all())->toBe([$queda['usuario']->id])
        ->and(correosEnviados())->toHaveCount(1);
});
