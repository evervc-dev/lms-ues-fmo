<?php

use App\Models\User;
use App\Modules\Comunicacion\Events\AvisoAgrupado;
use App\Modules\Comunicacion\Livewire\Campana;
use App\Modules\Comunicacion\Livewire\Notificaciones;
use App\Modules\Comunicacion\Notifications\Aviso;
use App\Modules\Comunicacion\Services\NotificacionService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * RF23 · Notificaciones y comunicación centralizada. Las pruebas usan los fakes de Laravel: ninguna levanta
 * Reverb ni un servidor de correo.
 */
function aviso(string $titulo = 'Nota publicada: Parcial 1', ?string $dedup = null, string $evento = 'grade.published'): Aviso
{
    return new Aviso($evento, $titulo, 'Ya puede ver su nota.', 'http://localhost/mis-notas', $dedup);
}

function enviarAviso(User $usuario, Aviso $aviso): void
{
    app(NotificacionService::class)->enviar([$usuario->id], $aviso);
}

function silenciarCorreo(User $usuario, string $evento = 'grade.published'): void
{
    DbContext::system(fn () => DB::table('notification_preferences')->insert([
        'user_id' => $usuario->id, 'event_code' => $evento, 'mail_enabled' => false,
    ]));
}

it('CP-RF23-01 crea el aviso en la bandeja y lo manda por push y correo en la cola notificaciones', function () {
    Notification::fake();
    $e = escenario();

    enviarAviso($e['estudiante'], aviso(dedup: 'grade.published:item:1'));

    // La bandeja es inmediata: la fila existe sin esperar a ningún trabajo.
    $fila = DB::table('notifications')->where('notifiable_id', $e['estudiante']->id)->first();
    expect($fila->event_code)->toBe('grade.published')
        ->and(json_decode($fila->data, true)['title'])->toBe('Nota publicada: Parcial 1');

    // El push y el correo salen con el id de esa fila, por la cola dedicada (RNF12).
    Notification::assertSentTo($e['estudiante'], Aviso::class, function (Aviso $enviado, array $canales) use ($fila) {
        return $enviado->id === $fila->id && $enviado->queue === 'notificaciones' && $canales === ['broadcast', 'mail'];
    });
});

it('CP-RF23-01 registra en notification_deliveries cuándo salió cada aviso por cada canal', function () {
    $e = escenario();

    enviarAviso($e['estudiante'], aviso(dedup: 'grade.published:item:1'));

    // Con la cola síncrona de las pruebas el push y el correo salen ya: quedan los tres canales.
    $canales = DB::table('notification_deliveries')->pluck('status', 'channel')->all();
    expect($canales)->toBe(['database' => 'sent', 'broadcast' => 'sent', 'mail' => 'sent'])
        ->and(DB::table('notification_deliveries')->whereNull('sent_at')->count())->toBe(0);
});

it('CP-RF23-01 manda el correo al correo institucional, con el título y el enlace del aviso', function () {
    $e = escenario();

    enviarAviso($e['estudiante'], aviso(dedup: 'grade.published:item:1'));

    $correo = app('mailer')->getSymfonyTransport()->messages()->first()?->getOriginalMessage();
    expect($correo)->not->toBeNull()
        ->and($correo->getTo()[0]->getAddress())->toBe($e['estudiante']->institutional_email)
        ->and($correo->getSubject())->toBe('Nota publicada: Parcial 1')
        ->and($correo->getHtmlBody())->toContain('http://localhost/mis-notas');
});

it('CP-RF23-02 si el usuario desactivó el correo para ese tipo de aviso, solo lo recibe en la plataforma', function () {
    Notification::fake();
    $e = escenario();
    silenciarCorreo($e['estudiante']);

    enviarAviso($e['estudiante'], aviso(dedup: 'grade.published:item:1'));

    expect(DB::table('notifications')->where('notifiable_id', $e['estudiante']->id)->count())->toBe(1);
    Notification::assertSentTo($e['estudiante'], Aviso::class, fn (Aviso $enviado, array $canales) => $canales === ['broadcast']);
});

it('CP-RF23-02 la preferencia es por tipo de aviso: silenciar uno no silencia los demás', function () {
    Notification::fake();
    $e = escenario();
    silenciarCorreo($e['estudiante'], 'grade.published');

    enviarAviso($e['estudiante'], aviso('Prórroga concedida', 'extension.granted:1', 'extension.granted'));

    Notification::assertSentTo($e['estudiante'], Aviso::class, fn (Aviso $enviado, array $canales) => $canales === ['broadcast', 'mail']);
});

it('CP-RF23-03 agrupa en uno los avisos del mismo tipo que llegan en poco tiempo', function () {
    Event::fake([AvisoAgrupado::class]);
    $e = escenario();

    enviarAviso($e['estudiante'], aviso('Nota publicada: Parcial 1', 'grade.published:item:1'));
    enviarAviso($e['estudiante'], aviso('Nota publicada: Parcial 2', 'grade.published:item:2'));
    enviarAviso($e['estudiante'], aviso('Nota publicada: Tarea 1', 'grade.published:item:3'));

    $filas = DB::table('notifications')->where('notifiable_id', $e['estudiante']->id)->get();
    $datos = json_decode($filas->first()->data, true);

    expect($filas)->toHaveCount(1)
        ->and($datos['count'])->toBe(3)
        ->and($datos['title'])->toBe('3 avisos nuevos: calificación publicada')
        ->and($datos['body'])->toBe('Último: Nota publicada: Tarea 1');

    // Cada aviso sumado le avisa a la campana; no es una fila nueva, así que no hay otro correo.
    Event::assertDispatchedTimes(AvisoAgrupado::class, 2);
    expect(DB::table('notification_deliveries')->where('channel', 'mail')->count())->toBe(1);
});

it('CP-RF23-03 no agrupa avisos de tipos distintos', function () {
    $e = escenario();

    enviarAviso($e['estudiante'], aviso('Nota publicada', 'grade.published:item:1', 'grade.published'));
    enviarAviso($e['estudiante'], aviso('Prórroga concedida', 'extension.granted:1', 'extension.granted'));

    expect(DB::table('notifications')->where('notifiable_id', $e['estudiante']->id)->count())->toBe(2);
});

it('CP-RF23-03 pasada la ventana de 10 minutos, o con el aviso ya leído, empieza un grupo nuevo', function () {
    $e = escenario();

    // La ventana es de 10 minutos (decisión de ADR-043): se fija aquí con literales, no con la constante, para
    // que cambiarla sin tocar el vault haga fallar la prueba.
    enviarAviso($e['estudiante'], aviso(dedup: 'grade.published:item:1'));
    $this->travel(9)->minutes();
    enviarAviso($e['estudiante'], aviso(dedup: 'grade.published:item:2'));

    expect(DB::table('notifications')->where('notifiable_id', $e['estudiante']->id)->count())->toBe(1);

    $this->travel(2)->minutes();   // 11 minutos desde el primero
    enviarAviso($e['estudiante'], aviso(dedup: 'grade.published:item:3'));

    expect(DB::table('notifications')->where('notifiable_id', $e['estudiante']->id)->count())->toBe(2);

    // Un aviso ya leído tampoco se reabre: el siguiente es nuevo.
    DB::table('notifications')->update(['read_at' => now()]);
    enviarAviso($e['estudiante'], aviso(dedup: 'grade.published:item:4'));

    expect(DB::table('notifications')->where('notifiable_id', $e['estudiante']->id)->count())->toBe(3);
});

it('no repite un aviso con la misma clave de idempotencia, ni suelto ni ya agrupado (RNF12)', function () {
    $e = escenario();

    enviarAviso($e['estudiante'], aviso(dedup: 'grade.published:item:1'));
    enviarAviso($e['estudiante'], aviso(dedup: 'grade.published:item:1'));
    enviarAviso($e['estudiante'], aviso(dedup: 'grade.published:item:2'));
    enviarAviso($e['estudiante'], aviso(dedup: 'grade.published:item:2'));

    $datos = json_decode(DB::table('notifications')->where('notifiable_id', $e['estudiante']->id)->value('data'), true);

    expect(DB::table('notifications')->where('notifiable_id', $e['estudiante']->id)->count())->toBe(1)
        ->and($datos['count'])->toBe(2);   // los dos eventos distintos, una sola vez cada uno
});

it('no envía nada a las cuentas sin rol (ADR-038)', function () {
    Notification::fake();
    $sinRol = DbContext::system(fn () => User::factory()->create());

    enviarAviso($sinRol, aviso(dedup: 'grade.published:item:1'));

    expect(DB::table('notifications')->count())->toBe(0);
    Notification::assertNothingSent();
});

it('entrega a cada destinatario lo suyo cuando el aviso va a varios', function () {
    $e = escenario();
    $otro = usuarioCon('estudiante');
    silenciarCorreo($otro);

    app(NotificacionService::class)->enviar([$e['estudiante']->id, $otro->id], aviso(dedup: 'grade.published:item:1'));

    expect(DB::table('notifications')->count())->toBe(2)
        ->and(DB::table('notification_deliveries')->where('channel', 'mail')->count())->toBe(1);
});

it('alcance P: la campana solo lee y marca los avisos del propio usuario', function () {
    $e = escenario();
    enviarAviso($e['estudiante'], aviso(dedup: 'grade.published:item:1'));
    $ajeno = DB::table('notifications')->where('notifiable_id', $e['estudiante']->id)->value('id');

    actingAs($e['docente']);
    $campana = Livewire::test(Campana::class);
    expect($campana->viewData('sinLeer'))->toBe(0)
        ->and($campana->viewData('recientes'))->toBeEmpty();

    $campana->call('abrir', $ajeno);
    expect(DB::table('notifications')->where('id', $ajeno)->value('read_at'))->toBeNull();

    actingAs($e['estudiante']);
    $propia = Livewire::test(Campana::class);
    expect($propia->viewData('sinLeer'))->toBe(1);

    $propia->call('abrir', $ajeno);
    expect(DB::table('notifications')->where('id', $ajeno)->value('read_at'))->not->toBeNull();
});

it('la campana muestra los últimos 10 avisos y marca todos como leídos', function () {
    $e = escenario();
    $eventos = array_keys(DB::table('notification_events')->pluck('description', 'code')->all());
    foreach (array_slice($eventos, 0, 12) as $i => $codigo) {
        enviarAviso($e['estudiante'], aviso("Aviso {$i}", "{$codigo}:{$i}", $codigo));
    }
    actingAs($e['estudiante']);

    $campana = Livewire::test(Campana::class);
    expect($campana->viewData('recientes'))->toHaveCount(10)
        ->and($campana->viewData('sinLeer'))->toBe(12);

    $campana->call('marcarTodas');
    expect($campana->viewData('sinLeer'))->toBe(0);
});

it('escucha en vivo su canal privado y, si el WebSocket no conecta, consulta cada 15 s', function () {
    $e = escenario();
    actingAs($e['estudiante']);

    $campana = Livewire::test(Campana::class);

    $campana->assertSeeHtml('wire:poll.15s');
    expect(array_keys((new Campana)->getListeners()))->toBe([
        'echo-private:App.Models.User.'.$e['estudiante']->id.',.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated',
        'echo-private:App.Models.User.'.$e['estudiante']->id.',.avisos.actualizados',
    ]);
});

it('CP-RF23-01 la campana suena y avisa cuando sube el número, y baja al marcar leídos', function () {
    $estudiante = usuarioCon('estudiante');
    $campana = Livewire::actingAs($estudiante)->test(Campana::class)->assertSet('sinLeer', 0);

    Notification::fake();
    enviarAviso($estudiante, aviso(dedup: 'campana-1'));
    $campana->call('refrescar', ['title' => 'Nota publicada: Parcial 1'])
        ->assertSet('sinLeer', 1)
        ->assertDispatched('campana-nueva')
        ->assertDispatched('aviso', tipo: 'info', mensaje: 'Nota publicada: Parcial 1');

    $campana->call('refrescar')->assertNotDispatched('campana-nueva');

    $estudiante->unreadNotifications()->update(['read_at' => now()]);
    $campana->dispatch('avisos-actualizados')->assertSet('sinLeer', 0)->assertNotDispatched('campana-nueva');
});

it('CP-RF23-04 abrir un aviso de la bandeja lo marca leído y lleva a la pantalla del evento', function () {
    $e = escenario();
    enviarAviso($e['estudiante'], aviso(dedup: 'grade.published:item:1'));
    $id = (string) DB::table('notifications')->value('id');
    actingAs($e['estudiante']);

    Livewire::test(Notificaciones::class)->assertSeeText(__('Ir al evento'))
        ->call('abrir', $id)
        ->assertDispatched('avisos-actualizados')
        ->assertRedirect('http://localhost/mis-notas');

    expect(DB::table('notifications')->value('read_at'))->not->toBeNull();
});

it('CP-RF23-04 un aviso sin pantalla a la que ir solo se marca leído', function () {
    $e = escenario();
    enviarAviso($e['estudiante'], new Aviso('class.cancelled', 'Clase cancelada: toda la facultad', 'El 15/09/2026 no hay clases. Motivo: asueto.', null, 'class.cancelled:1'));
    $id = (string) DB::table('notifications')->value('id');
    actingAs($e['estudiante']);

    Livewire::test(Notificaciones::class)->assertDontSeeText(__('Ir al evento'))->call('abrir', $id)->assertNoRedirect();

    expect(DB::table('notifications')->value('read_at'))->not->toBeNull();
});

it('CP-RF23-04 la campana abre el aviso en la pantalla del evento', function () {
    $e = escenario();
    enviarAviso($e['estudiante'], aviso(dedup: 'grade.published:item:1'));
    $id = (string) DB::table('notifications')->value('id');
    actingAs($e['estudiante']);

    Livewire::test(Campana::class)->assertSeeText(__('Ir al evento'))->call('abrir', $id)->assertRedirect('http://localhost/mis-notas');

    expect(DB::table('notifications')->value('read_at'))->not->toBeNull();
});
