<?php

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
