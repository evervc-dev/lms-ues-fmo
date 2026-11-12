<?php

namespace App\Modules\Asistencia\Services;

use App\Models\User;
use App\Support\DbContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Conexión del docente con Google Calendar (RF21, ADR-018): autorización incremental con `calendar.events` y
 * `access_type=offline`, con el mismo cliente OAuth del inicio de sesión. El refresh token se guarda cifrado en
 * `users.google_calendar_token` (cast `encrypted`).
 *
 * Solo cuentas @ues.edu.sv: el `hd` del redirect solo filtra el selector de Google; aquí se validan en el servidor el
 * `hd`, `email_verified`, la audiencia y que el correo autorizado sea el institucional del docente que inició el flujo.
 */
class CalendarConexionService
{
    public const SCOPE = 'https://www.googleapis.com/auth/calendar.events';

    /** Conectan su calendario el docente (clases virtuales, RF21) y el Administrador (calendario de evaluaciones, RF06). */
    public const PERMISOS = 'videoconferencias.gestionar|periodos.gestionar|periodos.calendario.gestionar';

    private const AUTORIZACION = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN = 'https://oauth2.googleapis.com/token';

    private const REVOCAR = 'https://oauth2.googleapis.com/revoke';

    public function habilitada(): bool
    {
        return (bool) config('services.google.calendar.enabled')
            && filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function dominio(): string
    {
        return (string) config('services.google.allowed_domain', 'ues.edu.sv');
    }

    public function conectado(User $docente): bool
    {
        return filled($docente->google_calendar_token);
    }

    public function delDominio(string $correo): bool
    {
        return str_ends_with(mb_strtolower(trim($correo)), '@'.$this->dominio());
    }

    /**
     * URL de consentimiento de Google. `prompt=consent` asegura que Google entregue el refresh token aunque el docente
     * ya haya autorizado antes.
     *
     * @throws AuthorizationException si el docente no tiene el permiso o su correo no es del dominio.
     */
    public function urlAutorizacion(User $docente, string $state): string
    {
        $this->exigir($docente);

        return self::AUTORIZACION.'?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->redirect(),
            'response_type' => 'code',
            'scope' => 'openid email '.self::SCOPE,
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent',
            'hd' => $this->dominio(),
            'login_hint' => $docente->institutional_email,
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Cambia el código de autorización por el refresh token y lo guarda.
     *
     * @throws ValidationException con la clave `google` si Google rechaza el código o la cuenta no es válida.
     */
    public function conectar(User $docente, string $codigo): void
    {
        $this->exigir($docente);

        $respuesta = Http::asForm()->timeout(15)->post(self::TOKEN, [
            'code' => $codigo,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => $this->redirect(),
            'grant_type' => 'authorization_code',
        ]);
        if ($respuesta->failed()) {
            $this->rechazar(__('Google no aceptó la autorización; intente de nuevo.'));
        }

        $claims = $this->claims((string) $respuesta->json('id_token'));
        $correo = mb_strtolower((string) ($claims['email'] ?? ''));
        $cuentaValida = ($claims['aud'] ?? null) === config('services.google.client_id')
            && in_array($claims['iss'] ?? null, ['https://accounts.google.com', 'accounts.google.com'], true)
            && ($claims['hd'] ?? null) === $this->dominio()
            && ($claims['email_verified'] ?? false) === true
            && $this->delDominio($correo);
        if (! $cuentaValida) {
            $this->rechazar(__('Use su correo institucional @:dominio.', ['dominio' => $this->dominio()]));
        }
        if ($correo !== mb_strtolower($docente->institutional_email)) {
            $this->rechazar(__('Autorice con la misma cuenta con la que entró al LMS (:correo).', ['correo' => $docente->institutional_email]));
        }

        $alcances = explode(' ', (string) $respuesta->json('scope'));
        $refresh = (string) $respuesta->json('refresh_token');
        if (! in_array(self::SCOPE, $alcances, true) || $refresh === '') {
            $this->rechazar(__('Para crear los eventos, marque el permiso de Google Calendar al autorizar.'));
        }

        DbContext::run(fn () => $docente->forceFill(['google_calendar_token' => $refresh])->save(), 'user.calendar_connected');
    }

    /**
     * Quita la conexión: revoca el token en Google (si falla, igual se borra aquí) y lo elimina.
     */
    public function desconectar(User $docente): void
    {
        $token = $docente->google_calendar_token;
        if (blank($token)) {
            return;
        }

        try {
            Http::asForm()->timeout(10)->post(self::REVOCAR, ['token' => $token]);
        } catch (Throwable $excepcion) {
            report($excepcion);
        }

        DbContext::run(fn () => $docente->forceFill(['google_calendar_token' => null])->save(), 'user.calendar_disconnected');
    }

    /**
     * Token de acceso de corta duración a partir del refresh token guardado. Si Google lo revocó (`invalid_grant`),
     * se borra para que el docente vuelva a autorizar y se devuelve null; otros errores se lanzan para que el job
     * reintente.
     */
    public function tokenDeAcceso(User $docente): ?string
    {
        if (! $this->conectado($docente)) {
            return null;
        }

        $respuesta = Http::asForm()->timeout(15)->post(self::TOKEN, [
            'refresh_token' => $docente->google_calendar_token,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'grant_type' => 'refresh_token',
        ]);
        if ($respuesta->status() === 400 && $respuesta->json('error') === 'invalid_grant') {
            DbContext::system(fn () => $docente->forceFill(['google_calendar_token' => null])->save(), 'user.calendar_revoked');

            return null;
        }

        return (string) $respuesta->throw()->json('access_token');
    }

    private function redirect(): string
    {
        return url((string) config('services.google.calendar.redirect'));
    }

    /**
     * @throws AuthorizationException
     */
    private function exigir(User $docente): void
    {
        $permitido = collect(explode('|', self::PERMISOS))->contains(fn (string $permiso) => $docente->checkPermissionTo($permiso));
        if (! $permitido || ! $this->delDominio($docente->institutional_email)) {
            throw new AuthorizationException(__('No tiene permiso para conectar Google Calendar.'));
        }
    }

    /**
     * Claims del `id_token`. Llega directo del endpoint de tokens de Google por TLS, así que basta leer la carga
     * (OpenID Connect Core §3.1.3.7) y validar audiencia, emisor y dominio.
     *
     * @return array<string, mixed>
     */
    private function claims(string $idToken): array
    {
        $partes = explode('.', $idToken);
        if (count($partes) !== 3) {
            return [];
        }
        $carga = json_decode((string) base64_decode(strtr($partes[1], '-_', '+/'), true), true);

        return is_array($carga) ? $carga : [];
    }

    /**
     * @return never
     */
    private function rechazar(string $mensaje): void
    {
        throw ValidationException::withMessages(['google' => $mensaje]);
    }
}
