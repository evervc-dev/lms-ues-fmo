<?php

use App\Modules\Identidad\Services\GoogleCredencialesService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * `php artisan google:verificar` (RF01, ADR-007): conexión con Google y validez del cliente OAuth.
 * Google se simula con Http::fake; ninguna prueba sale a la red.
 */
const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

beforeEach(function () {
    Http::preventStrayRequests();
    config(['services.google' => [
        'client_id' => 'cliente-prueba.apps.googleusercontent.com',
        'client_secret' => 'secreto-prueba',
        'redirect' => 'https://lms.ues.edu.sv/auth/google/callback',
        'allowed_domain' => 'ues.edu.sv',
    ]]);
});

/**
 * @param  array<string, mixed>  $respuestaDelToken
 */
function simularGoogle(array $respuestaDelToken, int $estado = 400): void
{
    Http::fake([
        GoogleCredencialesService::DESCUBRIMIENTO => Http::response(['token_endpoint' => TOKEN_ENDPOINT]),
        TOKEN_ENDPOINT => Http::response($respuestaDelToken, $estado),
    ]);
}

it('accepts valid credentials when Google only rejects the dummy code', function () {
    simularGoogle(['error' => 'invalid_grant', 'error_description' => 'Malformed auth code.']);

    $this->artisan('google:verificar')
        ->expectsOutputToContain('Google aceptó el client_id y el secreto.')
        ->assertSuccessful();

    Http::assertSent(fn ($peticion) => $peticion->url() === TOKEN_ENDPOINT
        && $peticion['client_id'] === 'cliente-prueba.apps.googleusercontent.com'
        && $peticion['client_secret'] === 'secreto-prueba'
        && $peticion['redirect_uri'] === 'https://lms.ues.edu.sv/auth/google/callback');
});

it('fails when Google rejects the client credentials', function () {
    simularGoogle(['error' => 'invalid_client', 'error_description' => 'The OAuth client was not found.'], 401);

    $this->artisan('google:verificar')
        ->expectsOutputToContain('Google rechazó el client_id o el secreto (invalid_client).')
        ->assertFailed();
});

it('fails on an unexpected token endpoint response', function () {
    simularGoogle([], 500);

    $this->artisan('google:verificar')->expectsOutputToContain('Respuesta inesperada de Google: HTTP 500')->assertFailed();
});

it('fails without calling Google when the configuration is incomplete', function (array $configuracion, string $mensaje) {
    config(['services.google' => [...config('services.google'), ...$configuracion]]);

    $this->artisan('google:verificar')->expectsOutputToContain($mensaje)->assertFailed();

    Http::assertNothingSent();
})->with([
    'sin client_id' => [['client_id' => null], 'Vacío.'],
    'client_id con otro formato' => [['client_id' => 'mi-cliente'], 'Debe terminar en .apps.googleusercontent.com.'],
    'sin secreto' => [['client_secret' => ''], 'Vacío.'],
    'redirect a otra ruta' => [['redirect' => 'https://lms.ues.edu.sv/otra'], 'https://lms.ues.edu.sv/otra'],
    'otro dominio' => [['allowed_domain' => 'gmail.com'], 'gmail.com'],
]);

it('fails when Google cannot be reached', function () {
    Http::fake([GoogleCredencialesService::DESCUBRIMIENTO => fn () => throw new ConnectionException('cURL error 6: Could not resolve host')]);

    $this->artisan('google:verificar')->expectsOutputToContain('Sin conexión: cURL error 6')->assertFailed();
});

it('never prints the client secret', function () {
    simularGoogle(['error' => 'invalid_grant']);

    $this->artisan('google:verificar')
        ->doesntExpectOutputToContain('secreto-prueba')
        ->expectsOutputToContain('Definido (no se muestra).')
        ->assertSuccessful();
});

it('reports Google Calendar as disabled without failing', function () {
    simularGoogle(['error' => 'invalid_grant']);

    $this->artisan('google:verificar')->expectsOutputToContain('Desactivado')->assertSuccessful();
});

it('fails when Google Calendar is enabled with a redirect URI that is not the profile callback', function () {
    simularGoogle(['error' => 'invalid_grant']);
    config(['services.google.calendar' => ['enabled' => true, 'redirect' => 'https://lms.ues.edu.sv/auth/google/callback']]);

    expect(collect(app(GoogleCredencialesService::class)->verificar())->firstWhere('paso', 'GOOGLE_CALENDAR_REDIRECT_URI'))->ok->toBeFalse();
    $this->artisan('google:verificar')->assertFailed();
});
