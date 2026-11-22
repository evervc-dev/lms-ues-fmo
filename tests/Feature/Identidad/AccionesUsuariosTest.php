<?php

use App\Modules\Identidad\Livewire\Usuarios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Acciones de la lista de usuarios según la jerarquía (RF04, ADR-037): el Jefe cambia los roles del personal de su
 * departamento, pero no crea cuentas ni las desactiva; el Administrador hace las tres cosas.
 */
it('CP-RF04-30 el Jefe ve «Roles» en el personal de su departamento, sin crear ni desactivar cuentas', function () {
    $e = escenario();
    actingAs($e['jefe']);

    Livewire::test(Usuarios::class)
        ->assertViewHas('rolesEditables', fn (array $ids) => in_array($e['docente']->id, $ids, true) && ! in_array($e['jefe']->id, $ids, true))
        ->assertSee(route('usuarios.roles', ['usuario' => $e['docente']->id]), false)
        ->assertDontSee(__('Nuevo usuario'))
        ->assertDontSee(__('Desactivar'));

    $this->get(route('usuarios.roles', ['usuario' => $e['docente']->id]))->assertOk();
});

it('CP-RF04-30 el Administrador ve «Roles» y «Desactivar», y puede crear cuentas', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(Usuarios::class)
        ->assertSee(route('usuarios.roles', ['usuario' => $e['docente']->id]), false)
        ->assertSee(__('Nuevo usuario'))
        ->assertSee(__('Desactivar'));
});
