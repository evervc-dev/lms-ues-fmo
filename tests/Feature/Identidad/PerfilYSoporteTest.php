<?php

use App\Models\User;
use App\Modules\Identidad\Livewire\Perfil;
use App\Modules\Identidad\Livewire\RolesPermisos;
use App\Modules\Identidad\Livewire\SolicitudesDispositivo;
use App\Modules\Identidad\Livewire\Usuarios;
use App\Modules\Identidad\Services\DispositivoService;
use App\Modules\Identidad\Services\PerfilService;
use App\Modules\Identidad\Services\UsuarioService;
use App\Support\DbContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('shows the student personal data read only', function () {
    $e = escenario();

    actingAs($e['estudiante'])->get(route('perfil'))
        ->assertOk()->assertSeeText(['Datos personales', 'GS21004', 'Ingeniería de Sistemas Informáticos', 'Dispositivo de asistencia']);
});

it('hides the device tab from staff', function () {
    $e = escenario();

    actingAs($e['docente'])->get(route('perfil'))->assertOk()->assertDontSeeText('Dispositivo de asistencia');
    actingAs($e['docente'])->get(route('perfil.dispositivo'))->assertForbidden();
});

it('turns an email notice off and on again', function () {
    $e = escenario();
    actingAs($e['estudiante']);

    Livewire::test(Perfil::class, ['pestana' => 'notificaciones'])->call('alternarCorreo', 'grade.published');
    expect(DB::table('notification_preferences')->where('user_id', $e['estudiante']->id)->value('mail_enabled'))->toBeFalsy();

    Livewire::test(Perfil::class, ['pestana' => 'notificaciones'])->call('alternarCorreo', 'grade.published');
    expect(DB::table('notification_preferences')->where('user_id', $e['estudiante']->id)->value('mail_enabled'))->toBeTruthy();
});

it('CP-RF27-03 lets a student request a phone change once', function () {
    $e = escenario();
    actingAs($e['estudiante']);

    Livewire::test(Perfil::class, ['pestana' => 'dispositivo'])->set('motivo', 'Perdí mi teléfono')->call('solicitarCambio')->assertHasNoErrors();
    Livewire::test(Perfil::class, ['pestana' => 'dispositivo'])->set('motivo', 'Otra vez')->call('solicitarCambio')->assertHasErrors(['motivo']);

    expect(DB::table('device_change_requests')->where('student_id', $e['estudiante']->id)->count())->toBe(1);
});

it('CP-RF27-05 lets support approve a phone change and revokes the old phone', function () {
    $e = escenario();
    DbContext::system(function () use ($e) {
        DB::table('student_devices')->insert(['student_id' => $e['estudiante']->id, 'credential_id' => 'abc', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('device_change_requests')->insert(['student_id' => $e['estudiante']->id, 'reason' => 'Me lo robaron', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
    });
    actingAs($e['soporte']);

    Livewire::test(SolicitudesDispositivo::class)->assertSee('Me lo robaron')->call('aprobar');

    expect(DB::table('student_devices')->where('student_id', $e['estudiante']->id)->value('status'))->toBe('revoked')
        ->and(DB::table('device_change_requests')->value('status'))->toBe('approved');
});

it('CP-RF27-05 requires a reason to reject a phone change', function () {
    $e = escenario();
    DbContext::system(fn () => DB::table('device_change_requests')->insert(['student_id' => $e['estudiante']->id, 'reason' => 'Teléfono nuevo', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]));
    actingAs($e['soporte']);

    Livewire::test(SolicitudesDispositivo::class)->call('rechazar')->assertHasErrors(['motivoRechazo']);
    Livewire::test(SolicitudesDispositivo::class)->set('motivoRechazo', 'No coincide la identidad')->call('rechazar')->assertHasNoErrors();

    expect(DB::table('device_change_requests')->value('status'))->toBe('rejected');
});

it('grants and revokes permissions but never removes role management from administrators', function () {
    $e = escenario();
    actingAs($e['administrador']);

    Livewire::test(RolesPermisos::class)->call('alternar', 'docente', 'reportes.ver');
    expect(Role::findByName('docente')->hasPermissionTo('reportes.ver'))->toBeFalse();

    Livewire::test(RolesPermisos::class)->call('alternar', 'administrador', 'roles.gestionar')->assertDispatched('aviso', tipo: 'error');
    expect(Role::findByName('administrador')->hasPermissionTo('roles.gestionar'))->toBeTrue();
});

it('CP-RF27-06 muestra en «Resueltas» el motivo con que Soporte rechazó la solicitud', function () {
    $e = escenario();
    DbContext::system(fn () => DB::table('device_change_requests')->insert(['student_id' => $e['estudiante']->id, 'reason' => 'Teléfono nuevo', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]));
    actingAs($e['soporte']);

    Livewire::test(SolicitudesDispositivo::class)->set('motivoRechazo', 'El carné no coincide con la cuenta')->call('rechazar')->assertHasNoErrors();

    expect(DB::table('device_change_requests')->value('resolution_note'))->toBe('El carné no coincide con la cuenta');
    Livewire::withQueryParams(['estado' => 'resueltas'])->test(SolicitudesDispositivo::class)
        ->assertSeeText('Motivo del rechazo')->assertSeeText('El carné no coincide con la cuenta');
});

it('lleva de la solicitud al diagnóstico de asistencia del mismo estudiante', function () {
    $e = escenario();
    DbContext::system(fn () => DB::table('device_change_requests')->insert(['student_id' => $e['estudiante']->id, 'reason' => 'Me lo robaron', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]));

    actingAs($e['soporte'])->get(route('dispositivos'))->assertOk()
        ->assertSee(route('asistencia.diagnostico', ['buscar' => 'GS21004']), false);
});

it('cuenta las solicitudes pendientes junto a la opción del menú de Soporte', function () {
    $e = escenario();
    DbContext::system(fn () => DB::table('device_change_requests')->insert([
        ['student_id' => $e['estudiante']->id, 'reason' => 'Uno', 'status' => 'pending', 'resolved_by' => null, 'resolved_at' => null, 'created_at' => now(), 'updated_at' => now()],
        ['student_id' => $e['estudiante']->id, 'reason' => 'Dos', 'status' => 'approved', 'resolved_by' => $e['soporte']->id, 'resolved_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]));

    actingAs($e['soporte'])->get(route('inicio'))->assertOk()
        ->assertSee(__('1 pendiente'))
        ->assertDontSeeText('Usuarios (consulta)');
});

it('CP-RF23-05 ofrece a cada rol solo las preferencias de aviso que le pueden llegar y siempre la pestaña', function () {
    $e = escenario();
    $preferencias = fn (User $u) => app(PerfilService::class)->preferencias($u)->pluck('codigo')->all();

    expect($preferencias($e['estudiante']))->toContain('grade.published')->not->toContain('grade_sheet.published')
        ->and($preferencias($e['docente']))->toContain('grade_sheet.returned')->not->toContain('grade.published')
        ->and($preferencias($e['coordinador']))->toContain('grade_sheet.published')
        ->and($preferencias($e['soporte']))->toBe(['device_request.pending', 'device_request.resolved'])
        ->and($preferencias($e['administrador']))->toBe([]);

    // H-13 (QA 27/10): la pestaña depende del permiso, no de que haya avisos; sin ellos, la pantalla lo explica.
    actingAs($e['administrador'])->get(route('perfil'))->assertOk()->assertSee(route('perfil.notificaciones'), false);
    actingAs($e['estudiante'])->get(route('perfil'))->assertOk()->assertSee(route('perfil.notificaciones'), false);
    actingAs($e['administrador'])->get(route('perfil.notificaciones'))->assertOk()->assertSeeText('No hay avisos por correo que configurar');
});

it('no ofrece apagar el correo de un aviso que siempre debe llegar', function () {
    $e = escenario();

    expect(app(PerfilService::class)->preferencias($e['estudiante'])->firstWhere('codigo', 'mass_message.sent')->obligatorio)->toBeTrue();

    actingAs($e['estudiante'])->get(route('perfil.notificaciones'))->assertOk()->assertSeeText('Siempre por correo');
    Livewire::actingAs($e['estudiante'])->test(Perfil::class, ['pestana' => 'notificaciones'])->call('alternarCorreo', 'mass_message.sent')->assertForbidden();
});

it('filtra en la lista de usuarios a los estudiantes sin teléfono de asistencia', function () {
    $e = escenario();
    actingAs($e['soporte']);

    Livewire::test(Usuarios::class)->set('telefono', 'sin')->assertSee($e['estudiante']->institutional_email);
    Livewire::test(Usuarios::class)->set('telefono', 'con')->assertDontSee($e['estudiante']->institutional_email);

    DbContext::system(fn () => DB::table('student_devices')->insert(['student_id' => $e['estudiante']->id, 'credential_id' => 'abc', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]));

    Livewire::test(Usuarios::class)->set('telefono', 'sin')->assertDontSee($e['estudiante']->institutional_email);
    Livewire::test(Usuarios::class)->set('telefono', 'con')->assertSee($e['estudiante']->institutional_email);
});

it('busca usuarios por el inicio de cada palabra del nombre, no por cualquier parte', function () {
    $e = escenario();
    DbContext::system(function () use ($e) {
        // Nombres fijos para todas las cuentas del escenario: los de la factoría son al azar.
        DB::table('users')->update(['first_name' => 'Zoila', 'last_name' => 'Zúniga']);
        DB::table('users')->where('id', $e['estudiante']->id)->update(['first_name' => 'Jan Carlos', 'last_name' => 'Pérez']);
        DB::table('users')->where('id', $e['docente']->id)->update(['first_name' => 'Alejandra', 'last_name' => 'Gómez']);
    });
    $buscar = fn (string $texto) => app(UsuarioService::class)->listar($e['soporte'], ['buscar' => $texto])->pluck('first_name')->all();
    actingAs($e['soporte']);

    expect($buscar('Jan'))->toBe(['Jan Carlos'])
        ->and($buscar('ale'))->toBe(['Alejandra'])
        ->and($buscar('gómez alejandra'))->toBe(['Alejandra']);
});

it('CP-RF27-07 avisa a quienes atienden la bandeja cuando llega una solicitud de cambio de teléfono', function () {
    $e = escenario();
    $otroSoporte = usuarioCon('soporte_tecnico');
    actingAs($e['estudiante']);

    app(DispositivoService::class)->solicitarCambio($e['estudiante'], 'Se me cayó al río');

    $avisos = DB::table('notifications')->where('event_code', 'device_request.pending')->get();

    expect($avisos->pluck('notifiable_id')->sort()->values()->all())->toBe(collect([$e['soporte']->id, $otroSoporte->id])->sort()->values()->all())
        ->and(json_decode($avisos->first()->data, true)['body'])->toContain('Se me cayó al río')
        ->and(DB::table('notifications')->where('notifiable_id', $e['estudiante']->id)->count())->toBe(0);
});

it('CP-RF27-07 al resolver avisa al estudiante y al resto del equipo, no a quien resolvió', function () {
    $e = escenario();
    $otroSoporte = usuarioCon('soporte_tecnico');
    DbContext::system(fn () => DB::table('device_change_requests')->insert(['student_id' => $e['estudiante']->id, 'reason' => 'Me lo robaron', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]));
    actingAs($e['soporte']);

    Livewire::test(SolicitudesDispositivo::class)->call('aprobar');

    $resueltos = DB::table('notifications')->where('event_code', 'device_request.resolved')->pluck('notifiable_id');

    expect($resueltos->sort()->values()->all())->toBe(collect([$e['estudiante']->id, $otroSoporte->id])->sort()->values()->all())
        ->and($resueltos)->not->toContain($e['soporte']->id);
});
