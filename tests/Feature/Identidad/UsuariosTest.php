<?php

use App\Models\User;
use App\Modules\Identidad\Livewire\CrearUsuario;
use App\Modules\Identidad\Livewire\EntrarComo;
use App\Modules\Identidad\Livewire\RolesPermisos;
use App\Modules\Identidad\Livewire\Usuarios;
use App\Modules\Identidad\Services\UsuarioService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Usuarios (RF04): el Administrador gestiona, Soporte consulta, el resto no entra.
 */
it('lists users with their roles for the administrator', function () {
    $e = escenario();

    actingAs($e['administrador'])->get(route('usuarios'))
        ->assertOk()
        ->assertSeeText(['Usuarios', $e['docente']->institutional_email, 'Nuevo usuario']);
});

it('lets technical support create users but not deactivate them', function () {
    $e = escenario();

    actingAs($e['soporte'])->get(route('usuarios'))->assertOk()->assertSeeText('Nuevo usuario')->assertDontSeeText('Desactivar');
});

it('forbids users to the other roles', function () {
    $e = escenario();

    actingAs($e['coordinador'])->get(route('usuarios'))->assertForbidden();
});

it('filters users by role and text', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(Usuarios::class)
        ->set('rol', 'estudiante')
        ->assertSee($e['estudiante']->institutional_email)
        ->assertDontSee($e['docente']->institutional_email);
});

it('creates an institutional account with its role', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(CrearUsuario::class)
        ->set('correo', 'ab12345@ues.edu.sv')->set('nombres', 'Ana')->set('apellidos', 'Ruiz')->set('rolNuevo', 'docente')
        ->set('departamentoDocente', $e['dia'])
        ->call('crear')
        ->assertHasNoErrors()
        ->assertRedirect();

    $usuario = User::query()->where('institutional_email', 'ab12345@ues.edu.sv')->first();
    expect($usuario->hasRole('docente'))->toBeTrue()
        ->and(DB::table('teacher_profiles')->where('user_id', $usuario->id)->value('department_id'))->toBe($e['dia']);
});

it('CP-RF04-03 pide solo la parte antes de la @ y completa el correo con @ues.edu.sv', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(CrearUsuario::class)
        ->set('correo', 'Ll22017')->assertSet('correo', 'll22017')
        ->set('nombres', 'Laura')->set('apellidos', 'López')->set('rolNuevo', 'estudiante')
        ->set('carreraEstudiante', $e['isi'])->set('planEstudiante', $e['plan'])->set('cicloEstudiante', 1)
        ->call('crear')
        ->assertHasNoErrors()
        ->assertRedirect();

    $usuario = User::query()->where('institutional_email', 'll22017@ues.edu.sv')->first();
    expect($usuario?->hasRole('estudiante'))->toBeTrue()
        ->and(DB::table('student_profiles')->where('user_id', $usuario->id)->value('student_code'))->toBe('ll22017');

    Livewire::test(CrearUsuario::class)->set('correo', 'ab12345@ues.edu.sv')->assertSet('correo', 'ab12345')
        ->set('correo', 'ana@gmail.com')->set('nombres', 'Ana')->set('apellidos', 'Ruiz')
        ->call('siguiente')->assertHasErrors(['correo'])->assertSee(__('Escriba solo la parte antes de :dominio, p. ej. ll22017.', ['dominio' => '@ues.edu.sv']));
});

it('CP-RF04-03 rechaza correos fuera del dominio institucional', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(CrearUsuario::class)
        ->set('correo', 'ana@gmail.com')->set('nombres', 'Ana')->set('apellidos', 'Ruiz')->set('rolNuevo', 'soporte_tecnico')
        ->call('crear')
        ->assertHasErrors(['correo']);

    expect(User::query()->where('institutional_email', 'ana@gmail.com')->exists())->toBeFalse();
});

it('CP-RF04-04 no crea un duplicado de un correo existente', function () {
    $e = escenario();
    $correo = $e['docente']->institutional_email;
    actingAs($e['administrador']);

    Livewire::test(CrearUsuario::class)
        ->set('correo', mb_strtoupper($correo))->set('nombres', 'Ana')->set('apellidos', 'Ruiz')->set('rolNuevo', 'soporte_tecnico')
        ->call('crear')
        ->assertHasErrors(['correo']);

    expect(User::query()->whereRaw('lower(institutional_email) = ?', [mb_strtolower($correo)])->count())->toBe(1);
});

it('CP-RF04-05 desactiva la cuenta con motivo, cierra sus sesiones, le impide entrar, conserva su historial y la reactiva', function () {
    config(['lms.dev_login' => true]);
    $e = escenario();
    DB::table('sessions')->insert(['id' => 'sesion-del-docente', 'user_id' => $e['docente']->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);
    actingAs($e['administrador']);

    Livewire::test(Usuarios::class)
        ->call('confirmarDesactivar', $e['docente']->id)
        ->set('motivo', 'Finalizó su contrato.')
        ->call('desactivar')
        ->assertHasNoErrors();

    expect($e['docente']->fresh()->is_active)->toBeFalse()->and($e['docente']->fresh()->deactivated_by)->toBe($e['administrador']->id)
        ->and(DB::table('sessions')->where('user_id', $e['docente']->id)->exists())->toBeFalse();
    Livewire::test(EntrarComo::class)->call('entrar', $e['docente']->id)->assertNotFound();
    actingAs($e['administrador'])->get(route('usuarios.show', $e['docente']->id))->assertOk()->assertSeeText('PRN115 · 01');

    Livewire::test(Usuarios::class)->call('reactivar', $e['docente']->id)->assertHasNoErrors();
    Livewire::test(EntrarComo::class)->call('entrar', $e['docente']->id)->assertRedirect(route('inicio'));

    $this->assertAuthenticatedAs($e['docente']->fresh());
});

it('CP-RF04-06 audita el alta, la concesión y la remoción de roles y la baja de un usuario', function () {
    $e = escenario();
    $usuarios = app(UsuarioService::class);
    actingAs($e['administrador']);

    $nuevo = $usuarios->crear($e['administrador'], ['correo' => 'ab12345@ues.edu.sv', 'nombres' => 'Ana', 'apellidos' => 'Ruiz', 'rol' => 'soporte_tecnico']);
    $usuarios->cambiarRoles($e['administrador'], $nuevo->id, ['soporte_tecnico', 'docente'], ['docente' => ['departamento' => $e['dia']]]);
    $usuarios->cambiarRoles($e['administrador'], $nuevo->id, ['docente']);
    $usuarios->desactivar($e['administrador'], $nuevo->id, 'Finalizó su contrato.');

    DbContext::auditScope(all: true);
    $auditoria = DB::table('audit_logs')->where('actor_user_id', $e['administrador']->id)->get(['entity_type', 'entity_id', 'action', 'event_type']);

    expect($auditoria->where('entity_type', 'users')->where('entity_id', $nuevo->id)->pluck('action')->all())->toContain('insert', 'update')
        ->and($auditoria->where('entity_type', 'model_has_roles')->where('action', 'insert')->count())->toBeGreaterThanOrEqual(2)
        ->and($auditoria->where('entity_type', 'model_has_roles')->where('action', 'delete')->count())->toBeGreaterThanOrEqual(1)
        ->and($auditoria->pluck('event_type')->filter()->all())->toContain('user.created', 'user.roles_changed', 'user.deactivated');
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'Requiere PostgreSQL');

it('shows the user record with current assignments', function () {
    $e = escenario();

    actingAs($e['administrador'])->get(route('usuarios.show', $e['docente']->id))
        ->assertOk()
        ->assertSeeText(['Asignaciones vigentes', 'PRN115 · 01']);
});

it('CP-RF04-05 no deja desactivar al último Administrador activo', function () {
    // Con alcance G (ADR-036) el actor válido siempre es otro Administrador activo,
    // así que la regla solo se alcanza forzando un actor inactivo (defensa en profundidad del Service).
    $actor = usuarioCon('administrador');
    $administrador = usuarioCon('administrador');
    DbContext::system(fn () => $actor->update(['is_active' => false]));
    actingAs($actor);

    expect(fn () => app(UsuarioService::class)->desactivar($actor->fresh(), $administrador->id, 'Prueba'))
        ->toThrow(ValidationException::class, 'último Administrador activo')
        ->and($administrador->fresh()->is_active)->toBeTrue();
});

it('CP-RF04-02 lleva el alta por pasos y no deja avanzar con el paso incompleto', function () {
    $e = escenario();
    actingAs($e['administrador']);

    $alta = Livewire::test(CrearUsuario::class)->assertSet('paso', 1);

    // El paso 1 vacío no avanza.
    $alta->call('siguiente')->assertHasErrors(['correo', 'nombres', 'apellidos'])->assertSet('paso', 1);

    $alta->set('correo', 'ab12345@ues.edu.sv')->set('nombres', 'Ana')->set('apellidos', 'Ruiz')
        ->call('siguiente')->assertHasNoErrors()->assertSet('paso', 2);

    $alta->set('rolNuevo', 'docente')->set('departamentoDocente', $e['dia'])
        ->call('siguiente')->assertHasNoErrors()->assertSet('paso', 3);

    // Se puede volver atrás, pero no saltar adelante desde el indicador.
    $alta->call('anterior')->assertSet('paso', 2);
    $alta->call('irAlPaso', 1)->assertSet('paso', 1);
    $alta->call('irAlPaso', 3)->assertSet('paso', 1);

    // Al enviar se valida todo de nuevo, aunque el formulario llegue sin pasar por los pasos.
    Livewire::test(CrearUsuario::class)->call('crear')->assertHasErrors(['correo'])->assertSet('paso', 1);
});

it('CP-RF04-19 limpia todos los filtros de la lista de una sola vez', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(Usuarios::class)
        ->set('buscar', 'ruiz')->set('rol', 'docente')->set('estado', 'inactivos')->set('telefono', 'sin')
        ->assertSee('Limpiar filtros')
        ->call('limpiar')
        ->assertSet('buscar', '')->assertSet('rol', '')->assertSet('estado', '')->assertSet('telefono', '')
        ->assertDontSee('Limpiar filtros');
});

it('CP-RF03-06 filtra la matriz de permisos por nombre y la limpia', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(RolesPermisos::class)
        ->assertSee('asistencia.marcar')->assertSee('roles.gestionar')
        ->set('buscar', 'asistencia.marcar')
        ->assertSee('asistencia.marcar')->assertDontSee('roles.gestionar')
        ->set('buscar', 'no-existe-este-permiso')
        ->assertSee('Ningún permiso coincide con esa búsqueda.')
        ->call('limpiar')
        ->assertSee('roles.gestionar');
});

/* ------------------------------------------------------------------ CP-RF04-04 (QA sesión 4: el aviso se ve) */

it('CP-RF04-04 avisa del correo repetido al salir del campo y no deja pasar del primer paso', function () {
    $e = escenario();
    actingAs($e['administrador']);
    $usuario = strstr($e['docente']->institutional_email, '@', true);

    Livewire::test(CrearUsuario::class)
        ->set('correo', mb_strtoupper($usuario))->assertHasErrors(['correo'])->assertSee('Ya existe un usuario con ese correo.')
        ->set('correo', 'zz99999')->assertHasNoErrors()
        ->set('correo', $usuario)->set('nombres', 'Ana')->set('apellidos', 'Ruiz')
        ->call('siguiente')->assertHasErrors(['correo'])->assertSet('paso', 1);
});

it('CP-RF04-04 si el correo se ocupa antes de confirmar, vuelve al primer paso y muestra el aviso', function () {
    $e = escenario();
    actingAs($e['administrador']);

    $pantalla = Livewire::test(CrearUsuario::class)
        ->set('correo', 'ab12345')->set('nombres', 'Ana')->set('apellidos', 'Ruiz')->set('rolNuevo', 'soporte_tecnico')
        ->call('siguiente')->call('siguiente')->assertSet('paso', 3);

    DbContext::system(fn () => User::factory()->create(['institutional_email' => 'ab12345@ues.edu.sv']));

    $pantalla->call('crear')->assertHasErrors(['correo'])->assertSet('paso', 1)->assertSee('Ya existe un usuario con ese correo.')->assertNoRedirect();
});

/* ------------------------------------------------------------------ CP-RF04-23 (formato del nombre, ADR-060) */

it('CP-RF04-23 el alta rechaza un nombre sin espacios y guarda el resto como nombre propio', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(CrearUsuario::class)
        ->set('correo', 'rg21008')->set('nombres', 'Rocío')->set('apellidos', str_repeat('m', 80))
        ->call('siguiente')->assertHasErrors(['apellidos'])->assertSet('paso', 1)
        ->set('nombres', 'rocío GUADALUPE')->set('apellidos', 'garcía de la o')->set('rolNuevo', 'soporte_tecnico')
        ->call('crear')->assertHasNoErrors()->assertRedirect();

    $usuario = User::query()->where('institutional_email', 'rg21008@ues.edu.sv')->first();
    expect($usuario->first_name)->toBe('Rocío Guadalupe')->and($usuario->last_name)->toBe('García de la O');

    expect(fn () => app(UsuarioService::class)->crear($e['administrador'], ['correo' => 'zz21009@ues.edu.sv', 'nombres' => 'Ana', 'apellidos' => str_repeat('m', 80), 'rol' => 'soporte_tecnico']))
        ->toThrow(ValidationException::class);
});

it('CP-RF04-23 la edición aplica el mismo formato', function () {
    $e = escenario();
    actingAs($e['administrador']);

    expect(fn () => app(UsuarioService::class)->actualizarDatos($e['administrador'], $e['docente']->id, ['nombres' => 'Ana', 'apellidos' => str_repeat('m', 80)]))
        ->toThrow(ValidationException::class);

    app(UsuarioService::class)->actualizarDatos($e['administrador'], $e['docente']->id, ['nombres' => 'MARÍA josé', 'apellidos' => 'hernández DEL cid']);
    expect($e['docente']->fresh()->only('first_name', 'last_name'))->toBe(['first_name' => 'María José', 'last_name' => 'Hernández del Cid']);
});
