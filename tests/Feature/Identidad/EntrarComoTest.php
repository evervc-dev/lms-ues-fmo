<?php

use App\Modules\Identidad\Livewire\CambioRapido;
use App\Modules\Identidad\Livewire\EntrarComo;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * «Entrar como…» (docs/12 Desarrollo/Setup del entorno.md): solo con LMS_DEV_LOGIN y nunca en producción.
 */
it('returns 404 when the development login is disabled', function () {
    config(['lms.dev_login' => false]);

    $this->get(route('dev.entrar-como'))->assertNotFound();
});

it('returns 404 in production even when enabled', function () {
    config(['lms.dev_login' => true]);
    app()->detectEnvironment(fn () => 'production');

    $this->get(route('dev.entrar-como'))->assertNotFound();
});

it('renders the development login page with the guest layout', function () {
    config(['lms.dev_login' => true]);

    $this->get(route('dev.entrar-como'))->assertOk()->assertSeeText(['Entrar como…', 'Cuentas de desarrollo']);
});

it('lists the development accounts and logs in as the chosen one', function () {
    config(['lms.dev_login' => true]);
    $docente = usuarioCon('docente');
    DbContext::system(fn () => $docente->update(['institutional_email' => 'docente.dev@ues.edu.sv']));

    Livewire::test(EntrarComo::class)
        ->assertSeeText(['docente.dev@ues.edu.sv', 'Docente'])
        ->call('entrar', $docente->id)
        ->assertRedirect(route('inicio'));

    $this->assertAuthenticatedAs($docente);
});

it('does not log in as an inactive user', function () {
    config(['lms.dev_login' => true]);
    $inactivo = usuarioCon('estudiante');
    DbContext::system(fn () => $inactivo->update(['is_active' => false]));

    Livewire::test(EntrarComo::class)->call('entrar', $inactivo->id)->assertNotFound();

    $this->assertGuest();
});

it('finds other users by email or name', function () {
    config(['lms.dev_login' => true]);
    $estudiante = usuarioCon('estudiante');
    DbContext::system(fn () => $estudiante->update(['institutional_email' => 'gs21004@ues.edu.sv', 'first_name' => 'Gabriela']));

    Livewire::test(EntrarComo::class)
        ->set('buscar', 'gabri')
        ->assertSeeText('gs21004@ues.edu.sv')
        ->set('buscar', 'g')
        ->assertDontSeeText('gs21004@ues.edu.sv');
});

it('links the login page to the development login only when enabled', function () {
    config(['lms.dev_login' => true]);
    $this->get(route('login'))->assertOk()->assertSeeText('Entrar como…');

    config(['lms.dev_login' => false]);
    $this->get(route('login'))->assertOk()->assertDontSeeText('Entrar como…');
});

it('marca «Ambiente de pruebas» en todas las pantallas mientras «Entrar como…» está activo', function () {
    $e = escenario();

    config(['lms.dev_login' => true]);
    $this->actingAs($e['docente'])->get(route('inicio'))->assertOk()->assertSee('Ambiente de pruebas');

    config(['lms.dev_login' => false]);
    $this->actingAs($e['docente'])->get(route('inicio'))->assertOk()->assertDontSee('Ambiente de pruebas');
});

it('no ofrece la Ayuda en el login, que pide sesión', function () {
    $this->get(route('login'))->assertOk()->assertDontSee(route('ayuda'), false);
});

/**
 * Cambio de rol al vuelo (herramienta de desarrollo): reemplaza los roles de la cuenta elegida, los guarda
 * para restaurarlos y respeta la cuenta de emergencia.
 */
it('reemplaza los roles de la cuenta y entra con el rol elegido', function () {
    config(['lms.dev_login' => true]);
    $estudiante = usuarioCon('estudiante');

    Livewire::test(EntrarComo::class)
        ->call('seleccionar', $estudiante->id)
        ->set('rol', 'docente')
        ->call('confirmar')
        ->assertRedirect(route('inicio'));

    $this->assertAuthenticatedAs($estudiante);
    expect($estudiante->fresh()->roles->pluck('name')->all())->toBe(['docente']);
});

it('entra sin tocar los roles cuando no se elige ninguno', function () {
    config(['lms.dev_login' => true]);
    $estudiante = usuarioCon('estudiante');

    Livewire::test(EntrarComo::class)
        ->call('seleccionar', $estudiante->id)
        ->call('confirmar')
        ->assertRedirect(route('inicio'));

    expect($estudiante->fresh()->roles->pluck('name')->all())->toBe(['estudiante']);
});

it('restaura los roles que el cambio de rol reemplazó', function () {
    config(['lms.dev_login' => true]);
    $usuario = usuarioCon('coordinador', 'docente');

    Livewire::test(EntrarComo::class)->call('seleccionar', $usuario->id)->set('rol', 'estudiante')->call('confirmar');
    expect($usuario->fresh()->roles->pluck('name')->all())->toBe(['estudiante']);

    Livewire::test(CambioRapido::class)->call('restaurar');

    expect($usuario->fresh()->roles->pluck('name')->sort()->values()->all())->toBe(['coordinador', 'docente']);
});

it('no cambia los roles de la cuenta de emergencia', function () {
    config(['lms.dev_login' => true]);
    $emergencia = usuarioCon('administrador');
    DbContext::system(fn () => $emergencia->forceFill(['is_emergency' => true])->save());

    Livewire::test(EntrarComo::class)
        ->call('seleccionar', $emergencia->id)
        ->set('rol', 'estudiante')
        ->call('confirmar')
        ->assertHasErrors('rol');

    expect($emergencia->fresh()->roles->pluck('name')->all())->toBe(['administrador']);
    $this->assertGuest();
});

it('no permite el cambio de rol con el inicio de sesión de desarrollo apagado', function () {
    config(['lms.dev_login' => true]);
    $estudiante = usuarioCon('estudiante');
    $componente = Livewire::test(EntrarComo::class)->call('seleccionar', $estudiante->id)->set('rol', 'docente');

    config(['lms.dev_login' => false]);
    $componente->call('confirmar')->assertNotFound();

    expect($estudiante->fresh()->roles->pluck('name')->all())->toBe(['estudiante']);
    $this->assertGuest();
});

it('ofrece «Entrar como…» en el menú de usuario solo en desarrollo', function () {
    $e = escenario();

    config(['lms.dev_login' => true]);
    $this->actingAs($e['docente'])->get(route('inicio'))->assertOk()->assertSeeText('Entrar como…');

    config(['lms.dev_login' => false]);
    $this->actingAs($e['docente'])->get(route('inicio'))->assertOk()->assertDontSeeText('Entrar como…');
});
