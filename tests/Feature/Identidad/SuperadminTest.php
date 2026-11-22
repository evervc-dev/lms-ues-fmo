<?php

use App\Models\User;
use App\Modules\Academica\Services\CarreraService;
use App\Modules\Identidad\Livewire\AccesoLocal;
use App\Modules\Identidad\Services\JerarquiaService;
use App\Support\Alcance;
use App\Support\DbContext;
use App\Support\Navegacion\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as PerfilGoogle;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Cuenta superadministradora (RF03, ADR-082): ve todo con alcance G, no hace acciones de estudiante ni de docencia,
 * entra con contraseña por /acceso-local y no por Google, y nadie le cambia los roles desde la interfaz.
 */
const CLAVE_SUPERADMIN = 'clave-de-prueba-larga';

function superadmin(): User
{
    usuarioCon('docente'); // siembra roles y permisos
    config(['identidad.superadmin.email' => 'superadmin@ues.edu.sv', 'identidad.superadmin.password' => CLAVE_SUPERADMIN]);
    test()->artisan('superadmin:crear')->assertSuccessful();

    return User::query()->where('institutional_email', 'superadmin@ues.edu.sv')->firstOrFail();
}

it('CP-RF03-08 crea la cuenta solo con SUPERADMIN_PASSWORD, con el rol exclusivo, y es idempotente', function () {
    usuarioCon('docente');
    config(['identidad.superadmin.password' => null]);
    $this->artisan('superadmin:crear')->assertFailed();
    expect(User::query()->where('institutional_email', 'superadmin@ues.edu.sv')->exists())->toBeFalse();

    $cuenta = superadmin();
    $this->artisan('superadmin:crear')->assertSuccessful();

    expect(User::query()->where('institutional_email', 'superadmin@ues.edu.sv')->count())->toBe(1)
        ->and($cuenta->getRoleNames()->all())->toBe(['superadmin'])
        ->and($cuenta->is_active)->toBeTrue()
        ->and($cuenta->password)->not->toBe(CLAVE_SUPERADMIN);
});

it('CP-RF03-08 entra por /acceso-local con la contraseña y registra el intento', function () {
    $cuenta = superadmin();

    $this->get(route('acceso-local'))->assertOk();
    Livewire::test(AccesoLocal::class)->set('correo', 'superadmin@ues.edu.sv')->set('clave', CLAVE_SUPERADMIN)
        ->call('entrar')->assertHasNoErrors()->assertRedirect(route('inicio'));

    $this->assertAuthenticatedAs($cuenta);
    expect(DB::table('login_history')->where('user_id', $cuenta->id)->where('result', 'success')->exists())->toBeTrue();
});

it('CP-RF03-08 rechaza una contraseña errada y una cuenta que no es superadmin, y bloquea tras 5 intentos', function () {
    $cuenta = superadmin();
    $otro = usuarioCon('administrador');
    DbContext::system(fn () => $otro->forceFill(['password' => bcrypt(CLAVE_SUPERADMIN)])->save());
    RateLimiter::clear('acceso-local:superadmin@ues.edu.sv|127.0.0.1');

    Livewire::test(AccesoLocal::class)->set('correo', $otro->institutional_email)->set('clave', CLAVE_SUPERADMIN)
        ->call('entrar')->assertHasErrors('correo');
    $this->assertGuest();

    foreach (range(1, 5) as $intento) {
        Livewire::test(AccesoLocal::class)->set('correo', 'superadmin@ues.edu.sv')->set('clave', 'otra')->call('entrar')->assertHasErrors('correo');
    }
    Livewire::test(AccesoLocal::class)->set('correo', 'superadmin@ues.edu.sv')->set('clave', CLAVE_SUPERADMIN)
        ->call('entrar')->assertHasErrors('correo');

    $this->assertGuest();
    expect(DB::table('login_history')->where('user_id', $cuenta->id)->where('failure_reason', 'bad_credentials')->count())->toBe(5)
        ->and(DB::table('login_history')->where('failure_reason', 'throttled')->exists())->toBeTrue();
});

it('CP-RF03-08 Google no deja entrar a la cuenta superadministradora', function () {
    superadmin();
    config(['services.google' => ['client_id' => 'cliente', 'client_secret' => 'secreto', 'redirect' => '/auth/google/callback', 'allowed_domain' => 'ues.edu.sv']]);
    Socialite::fake('google', PerfilGoogle::fake(['id' => 'sub-super', 'email' => 'superadmin@ues.edu.sv', 'hd' => 'ues.edu.sv', 'email_verified' => true]));

    $this->get(route('auth.google.callback'))->assertRedirect(route('login'));

    $this->assertGuest();
    expect(DB::table('login_history')->where('failure_reason', 'local_only')->exists())->toBeTrue();
});

it('CP-RF03-08 ve todo con alcance G, sin acciones de estudiante ni de docencia', function () {
    $e = escenario();
    $cuenta = superadmin();
    $alcance = app(Alcance::class);

    foreach (['carreras.ver', 'asignaturas.gestionar', 'auditoria.ver', 'calificaciones.ver', 'asistencia.ver', 'contenidos.ver', 'reportes.ver', 'usuarios.gestionar'] as $permiso) {
        expect($alcance->de($cuenta, $permiso))->toBe('G');
    }
    foreach (['asistencia.marcar', 'tareas.entregar', 'calificaciones.gestionar', 'actas.publicar', 'asistencia.sesion.abrir', 'cierre.autorizar'] as $permiso) {
        expect($cuenta->hasPermissionTo($permiso))->toBeFalse();
    }

    expect(app(CarreraService::class)->departamentos($cuenta)->pluck('code')->all())->toBe(['DCNM', 'DIA']);
    actingAs($cuenta)->get(route('departamentos'))->assertOk();
    actingAs($cuenta)->get(route('carreras.show', ['carrera' => $e['isi']]))->assertOk();
    actingAs($cuenta)->get(route('catalogo'))->assertOk();
});

it('CP-RF03-08 el menú muestra todos los grupos menos Estudio y nadie le cambia los roles', function () {
    $cuenta = superadmin();
    $administrador = usuarioCon('administrador');

    $grupos = collect(app(Menu::class)->para($cuenta)['grupos'])->pluck('titulo')->all();
    expect($grupos)->toContain('Administración', 'Jefatura', 'Coordinación')->not->toContain('Estudio');

    expect(app(JerarquiaService::class)->puedeCambiarRoles($administrador, $cuenta))->toBeFalse()
        ->and(app(JerarquiaService::class)->rolesAsignables($administrador))->not->toContain('superadmin');
    actingAs($administrador)->get(route('usuarios.roles', ['usuario' => $cuenta->id]))->assertForbidden();
});
