<?php

use App\Modules\Identidad\Livewire\Integraciones;
use App\Modules\Identidad\Services\IntegracionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Integraciones (RNF09, QA admin 21/11): el correo con servidor local o en modo registro se marca «Revisar» y se puede
 * mandar un correo de prueba al Administrador.
 */
function correoCompleto(string $mailer, string $host): void
{
    config(['mail.default' => $mailer, 'mail.mailers.smtp.host' => $host, 'mail.mailers.smtp.port' => 587, 'mail.from.address' => 'lms@ues.edu.sv']);
}

it('marca «Revisar» el correo con servidor local fuera del entorno local o en modo registro', function (string $mailer, string $host, string $estado) {
    $e = escenario();
    correoCompleto($mailer, $host);

    $correo = app(IntegracionService::class)->listar($e['administrador'])['correo'];

    expect($correo['estado'])->toBe($estado)
        ->and($correo['configurada'])->toBe($estado === 'ok');
})->with([
    'smtp local' => ['smtp', '127.0.0.1', 'revisar'],
    'modo registro' => ['log', 'smtp.gmail.com', 'revisar'],
    'smtp real' => ['smtp', 'smtp.gmail.com', 'ok'],
]);

it('envía un correo de prueba al Administrador y avisa el error si falla', function () {
    $e = escenario();
    actingAs($e['administrador']);
    correoCompleto('array', 'smtp.gmail.com');

    Livewire::test(Integraciones::class)
        ->assertSee(['Enviar correo de prueba', 'Revisar', 'Detalles técnicos'])
        ->call('enviarPrueba')
        ->assertDispatched('aviso', tipo: 'exito');

    Mail::shouldReceive('raw')->andThrow(new RuntimeException('Connection refused'));
    Livewire::test(Integraciones::class)->call('enviarPrueba')->assertDispatched('aviso', tipo: 'error');
});

it('no deja a otros roles enviar el correo de prueba', function () {
    $e = escenario();

    expect(fn () => app(IntegracionService::class)->enviarCorreoDePrueba($e['jefe']))->toThrow(AuthorizationException::class);
});
