<?php

use App\Models\User;
use App\Support\DbContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as PerfilGoogle;

uses(RefreshDatabase::class);

/**
 * Inicio de sesión con Google @ues.edu.sv (RF01, ADR-007). Socialite se simula: el perfil es lo que Google
 * devolvería en el callback, con los claims `hd` y `email_verified` que el servidor valida.
 */
beforeEach(function () {
    config(['services.google' => [
        'client_id' => 'cliente-prueba.apps.googleusercontent.com',
        'client_secret' => 'secreto-prueba',
        'redirect' => '/auth/google/callback',
        'allowed_domain' => 'ues.edu.sv',
    ]]);
});

/**
 * @param  array<string, mixed>  $claims
 */
function perfilGoogle(string $correo, array $claims = []): PerfilGoogle
{
    return PerfilGoogle::fake(['id' => 'sub-'.md5($correo), 'email' => $correo, 'hd' => 'ues.edu.sv', 'email_verified' => true, ...$claims]);
}

it('redirects to Google with the institutional domain and an anti-CSRF state', function () {
    $respuesta = $this->get(route('auth.google'))->assertRedirect();

    expect($respuesta->headers->get('Location'))
        ->toStartWith('https://accounts.google.com/o/oauth2/auth')
        ->toContain('hd=ues.edu.sv', 'state=', 'client_id=cliente-prueba.apps.googleusercontent.com');
});

it('does not redirect to Google when the OAuth client is not configured', function () {
    config(['services.google.client_secret' => null]);

    $this->get(route('auth.google'))->assertRedirect(route('login'))->assertSessionHasErrors('google');
    $this->get(route('login'))->assertSeeText('El inicio de sesión con Google no está configurado; contacte al administrador.');
});

it('CP-RF01-01 logs in an active registered user, links the Google sub and opens their role panel', function () {
    $docente = usuarioCon('docente');
    Socialite::fake('google', perfilGoogle($docente->institutional_email, ['id' => 'sub-docente']));

    $this->get(route('auth.google.callback'))->assertRedirect(route('inicio'));

    $this->assertAuthenticatedAs($docente);
    expect($docente->fresh())->google_sub->toBe('sub-docente')->last_login_at->not->toBeNull();
    $this->get(route('inicio'))->assertOk();
});

it('identifies a returning user by the Google sub', function () {
    $docente = usuarioCon('docente');
    DbContext::system(fn () => $docente->update(['google_sub' => 'sub-docente']));
    Socialite::fake('google', perfilGoogle('nuevo.alias@ues.edu.sv', ['id' => 'sub-docente']));

    $this->get(route('auth.google.callback'))->assertRedirect(route('inicio'));

    $this->assertAuthenticatedAs($docente);
});

it('CP-RF01-02 rejects an account outside the institutional domain and records the failed attempt', function (string $correo, array $claims) {
    Socialite::fake('google', perfilGoogle($correo, $claims));

    $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHasErrors('google');

    $this->assertGuest();
    $this->get(route('login'))->assertSeeText('Use su correo institucional @ues.edu.sv.');
    $this->assertDatabaseHas('login_history', ['attempted_email' => $correo, 'result' => 'failure', 'failure_reason' => 'domain']);
})->with([
    'gmail' => ['juan.perez@gmail.com', ['hd' => null]],
    'correo institucional sin hd' => ['rr19118@ues.edu.sv', ['hd' => null]],
    'hd de otro dominio' => ['rr19118@ues.edu.sv', ['hd' => 'otra.edu.sv']],
    'correo no verificado' => ['rr19118@ues.edu.sv', ['email_verified' => false]],
]);

it('CP-RF01-03 registers an unknown institutional account without a role and lets it in', function () {
    Socialite::fake('google', perfilGoogle('xx99999@ues.edu.sv', ['id' => 'sub-nuevo', 'given_name' => 'Kevin Israel', 'family_name' => 'Rodríguez Rivera']));

    $this->get(route('auth.google.callback'))->assertRedirect(route('inicio'));

    $usuario = User::query()->where('institutional_email', 'xx99999@ues.edu.sv')->firstOrFail();
    $this->assertAuthenticatedAs($usuario);
    expect($usuario->only(['first_name', 'last_name', 'google_sub', 'is_active']))->toBe(['first_name' => 'Kevin Israel', 'last_name' => 'Rodríguez Rivera', 'google_sub' => 'sub-nuevo', 'is_active' => true])
        ->and($usuario->getRoleNames()->all())->toBe([]);
    $this->assertDatabaseHas('login_history', ['attempted_email' => 'xx99999@ues.edu.sv', 'user_id' => $usuario->id, 'result' => 'success', 'failure_reason' => null]);
    $this->get(route('inicio'))->assertOk()->assertSeeText(['Su cuenta está activa pero aún no tiene un rol asignado', 'Comuníquese con su coordinación o con Soporte técnico.']);
});

it('CP-RF01-03 does not register the same account twice', function () {
    Socialite::fake('google', perfilGoogle('xx99999@ues.edu.sv', ['name' => 'Ana María López', 'given_name' => null]));
    $this->get(route('auth.google.callback'));
    auth()->logout();
    $this->get(route('auth.google.callback'));

    expect(User::query()->where('institutional_email', 'xx99999@ues.edu.sv')->get(['first_name', 'last_name'])->toArray())
        ->toBe([['first_name' => 'Ana', 'last_name' => 'María López']]);
});

it('CP-RF01-03 audits the self-registration as a system write', function () {
    Socialite::fake('google', perfilGoogle('xx99999@ues.edu.sv'));

    $this->get(route('auth.google.callback'));
    DbContext::auditScope(all: true);

    $this->assertDatabaseHas('audit_logs', ['entity_type' => 'users', 'action' => 'insert', 'event_type' => 'user.self_registered']);
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'La auditoría por triggers solo existe en PostgreSQL.');

it('CP-RF01-04 rejects a deactivated account and records the attempt', function () {
    $estudiante = usuarioCon('estudiante');
    DbContext::system(fn () => $estudiante->update(['is_active' => false, 'deactivated_at' => now()]));
    Socialite::fake('google', perfilGoogle($estudiante->institutional_email));

    $this->get(route('auth.google.callback'))->assertRedirect(route('login'));

    $this->assertGuest();
    $this->get(route('login'))->assertSeeText('Su cuenta está desactivada; contacte al administrador.');
    $this->assertDatabaseHas('login_history', ['user_id' => $estudiante->id, 'result' => 'failure', 'failure_reason' => 'inactive']);
    expect($estudiante->fresh()->google_sub)->toBeNull();
});

it('rejects an account whose linked Google sub does not match', function () {
    $docente = usuarioCon('docente');
    DbContext::system(fn () => $docente->update(['google_sub' => 'sub-original']));
    Socialite::fake('google', perfilGoogle($docente->institutional_email, ['id' => 'sub-reciclado']));

    $this->get(route('auth.google.callback'))->assertRedirect(route('login'));

    $this->assertGuest();
    $this->assertDatabaseHas('login_history', ['user_id' => $docente->id, 'result' => 'failure', 'failure_reason' => 'sub_mismatch']);
    expect($docente->fresh()->google_sub)->toBe('sub-original');
});

it('CP-RF01-05 records date, IP, user agent and result of every attempt', function () {
    $this->freezeSecond();
    $docente = usuarioCon('docente');
    $servidor = ['REMOTE_ADDR' => '10.10.5.20', 'HTTP_USER_AGENT' => 'Mozilla/5.0 (Prueba LMS)'];

    Socialite::fake('google', perfilGoogle('juan.perez@gmail.com', ['hd' => null]));
    $this->withServerVariables($servidor)->get(route('auth.google.callback'));
    Socialite::fake('google', perfilGoogle($docente->institutional_email));
    $this->withServerVariables($servidor)->get(route('auth.google.callback'));

    $intentos = DB::table('login_history')->orderBy('id')->get(['attempted_email', 'ip_address', 'user_agent', 'result', 'occurred_at']);
    expect($intentos)->toHaveCount(2)
        ->and($intentos->pluck('result')->all())->toBe(['failure', 'success'])
        ->and($intentos->pluck('ip_address')->unique()->all())->toBe(['10.10.5.20'])
        ->and($intentos->pluck('user_agent')->unique()->all())->toBe(['Mozilla/5.0 (Prueba LMS)'])
        ->and($intentos->every(fn ($intento) => Carbon::parse($intento->occurred_at)->equalTo(now())))->toBeTrue();
});

it('CP-RF01-06 expires the session after 30 minutes of inactivity', function () {
    expect(config('session.lifetime'))->toBe(30)
        ->and(config('session.expire_on_close'))->toBeFalse();
});

it('returns to the login screen when the OAuth state does not match', function () {
    $this->get(route('auth.google.callback', ['code' => 'codigo', 'state' => 'manipulado']))->assertRedirect(route('login'));

    $this->assertGuest();
    $this->get(route('login'))->assertSeeText('La solicitud de inicio de sesión expiró; intente de nuevo.');
    expect(DB::table('login_history')->count())->toBe(0);
});

it('returns to the login screen when the user cancels on Google', function () {
    $this->get(route('auth.google.callback', ['error' => 'access_denied']))->assertRedirect(route('login'));

    $this->get(route('login'))->assertSeeText('Se canceló el inicio de sesión con Google.');
});

it('links to Google with a full page load, not a Livewire navigation', function () {
    $this->get(route('login'))
        ->assertSee('href="'.route('auth.google').'"', false)
        ->assertDontSee('href="'.route('auth.google').'" wire:navigate', false);
});

it('links the Google profile picture and keeps it up to date', function () {
    $docente = usuarioCon('docente');
    $foto = 'https://lh3.googleusercontent.com/a/foto-uno=s96-c';

    Socialite::fake('google', perfilGoogle($docente->institutional_email, ['picture' => $foto]));
    $this->get(route('auth.google.callback'));
    expect($docente->fresh()->avatar_url)->toBe($foto);

    auth()->logout();
    Socialite::fake('google', perfilGoogle($docente->institutional_email, ['picture' => 'https://lh3.googleusercontent.com/a/foto-dos=s96-c']));
    $this->get(route('auth.google.callback'));
    expect($docente->fresh()->avatar_url)->toBe('https://lh3.googleusercontent.com/a/foto-dos=s96-c');
});

it('ignores a profile picture that is not served by Google over https', function (string $foto) {
    $docente = usuarioCon('docente');
    Socialite::fake('google', perfilGoogle($docente->institutional_email, ['picture' => $foto]));

    $this->get(route('auth.google.callback'));

    expect($docente->fresh()->avatar_url)->toBeNull();
})->with([
    'otro host' => ['https://ejemplo.com/foto.jpg'],
    'host que imita a Google' => ['https://googleusercontent.com.ejemplo.com/foto.jpg'],
    'sin https' => ['http://lh3.googleusercontent.com/a/foto'],
]);

it('shows the linked picture without referrer, and the initials when there is none', function () {
    $conFoto = usuarioCon('administrador');
    DbContext::system(fn () => $conFoto->forceFill(['avatar_url' => 'https://lh3.googleusercontent.com/a/foto=s96-c'])->save());
    $sinFoto = usuarioCon('estudiante');

    $this->actingAs($conFoto)->get(route('perfil'))
        ->assertSee('src="https://lh3.googleusercontent.com/a/foto=s96-c" alt="" referrerpolicy="no-referrer"', false);
    $this->actingAs($sinFoto)->get(route('perfil'))
        ->assertDontSee('referrerpolicy', false)
        ->assertSeeText($sinFoto->iniciales());
});
