<?php

namespace App\Modules\Identidad\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Verifica la configuración y las credenciales del cliente OAuth de Google (RF01, ADR-007) sin un usuario:
 * consulta el documento de descubrimiento de Google y canjea un código inválido en el token endpoint.
 * Google valida el cliente antes que el código, así que `invalid_grant` confirma que el client_id y el secreto
 * son correctos, e `invalid_client` que no lo son. La URI de redirección solo se valida en un ingreso real.
 * También revisa la configuración de Google Calendar (RF21), que usa el mismo cliente.
 */
class GoogleCredencialesService
{
    public const DESCUBRIMIENTO = 'https://accounts.google.com/.well-known/openid-configuration';

    /**
     * @return array<int, array{paso: string, ok: bool, detalle: string}>
     */
    public function verificar(): array
    {
        $configuracion = $this->revisarConfiguracion();
        if (in_array(false, array_column($configuracion, 'ok'), true)) {
            return $configuracion;
        }

        try {
            $descubrimiento = Http::timeout(10)->acceptJson()->get(self::DESCUBRIMIENTO);
        } catch (ConnectionException $excepcion) {
            return [...$configuracion, $this->paso('Conexión con Google', false, __('Sin conexión: :error', ['error' => $excepcion->getMessage()]))];
        }

        $tokenEndpoint = $descubrimiento->json('token_endpoint');
        if (! $descubrimiento->successful() || ! is_string($tokenEndpoint)) {
            return [...$configuracion, $this->paso('Conexión con Google', false, __('Google respondió HTTP :estado sin token_endpoint.', ['estado' => $descubrimiento->status()]))];
        }
        $conexion = $this->paso('Conexión con Google', true, $tokenEndpoint);

        try {
            $canje = Http::timeout(10)->asForm()->acceptJson()->post($tokenEndpoint, [
                'grant_type' => 'authorization_code',
                'code' => 'verificacion-lms-ues',
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => $this->redirect(),
            ]);
        } catch (ConnectionException $excepcion) {
            return [...$configuracion, $conexion, $this->paso('Credenciales del cliente', false, __('Sin conexión: :error', ['error' => $excepcion->getMessage()]))];
        }

        $error = (string) $canje->json('error');
        $credenciales = match ($error) {
            'invalid_grant' => $this->paso('Credenciales del cliente', true, __('Google aceptó el client_id y el secreto.')),
            'invalid_client', 'unauthorized_client' => $this->paso('Credenciales del cliente', false, __('Google rechazó el client_id o el secreto (:error).', ['error' => $error])),
            default => $this->paso('Credenciales del cliente', false, __('Respuesta inesperada de Google: HTTP :estado :error.', ['estado' => $canje->status(), 'error' => $error])),
        };

        return [...$configuracion, $conexion, $credenciales];
    }

    /**
     * @return array<int, array{paso: string, ok: bool, detalle: string}>
     */
    private function revisarConfiguracion(): array
    {
        $clientId = (string) config('services.google.client_id');
        $idValido = str_ends_with($clientId, '.apps.googleusercontent.com');
        $secretoDefinido = filled(config('services.google.client_secret'));
        $redirect = $this->redirect();

        return [
            $this->paso('GOOGLE_CLIENT_ID', $idValido, match (true) {
                $clientId === '' => __('Vacío.'),
                $idValido => $clientId,
                default => __('Debe terminar en .apps.googleusercontent.com.'),
            }),
            $this->paso('GOOGLE_CLIENT_SECRET', $secretoDefinido, $secretoDefinido ? __('Definido (no se muestra).') : __('Vacío.')),
            $this->paso('GOOGLE_REDIRECT_URI', str_ends_with($redirect, '/auth/google/callback'), $redirect),
            $this->paso('GOOGLE_ALLOWED_DOMAIN', config('services.google.allowed_domain') === 'ues.edu.sv', (string) config('services.google.allowed_domain')),
            $this->calendar(),
        ];
    }

    /**
     * Google Calendar (RF21, ADR-018) usa el mismo cliente: apagado no es un error. Encendido, la URI de redirección
     * debe ser la del callback de Mi perfil; la Calendar API solo se prueba con un docente conectado
     * (`php artisan google:calendar-prueba <correo>`).
     *
     * @return array{paso: string, ok: bool, detalle: string}
     */
    private function calendar(): array
    {
        if (! config('services.google.calendar.enabled')) {
            return $this->paso('GOOGLE_CALENDAR_ENABLED', true, __('Desactivado: las clases virtuales no crean eventos.'));
        }
        $redirect = url((string) config('services.google.calendar.redirect'));

        return $this->paso('GOOGLE_CALENDAR_REDIRECT_URI', str_ends_with($redirect, '/perfil/google-calendar/callback'), $redirect);
    }

    private function redirect(): string
    {
        $redirect = (string) config('services.google.redirect');

        return str_starts_with($redirect, '/') ? url($redirect) : $redirect;
    }

    /**
     * @return array{paso: string, ok: bool, detalle: string}
     */
    private function paso(string $paso, bool $ok, string $detalle): array
    {
        return ['paso' => $paso, 'ok' => $ok, 'detalle' => $detalle];
    }
}
