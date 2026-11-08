<?php

namespace App\Modules\Identidad\Services;

use App\Models\User;
use App\Support\DbContext;
use App\Support\DbError;
use App\Support\Nombre;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as PerfilGoogle;

/**
 * Inicio de sesión con Google @ues.edu.sv (RF01, ADR-007, docs/08 Flujos/Flujo Inicio de sesión con Google.md).
 *
 * El parámetro `hd` del redirect solo filtra el selector de Google: aquí se valida en el servidor el claim `hd`,
 * `email_verified` y el sufijo del correo. Una cuenta institucional que no existe se crea sin rol (ADR-038); el `sub`
 * se vincula en el primer ingreso y desde entonces identifica al usuario. Cada intento queda en `login_history` (RNF02).
 */
class GoogleAuthService
{
    public function configurado(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function dominio(): string
    {
        return (string) config('services.google.allowed_domain', 'ues.edu.sv');
    }

    /**
     * @throws ValidationException con la clave `google` y el mensaje para la pantalla de inicio de sesión.
     */
    public function autenticar(PerfilGoogle $perfil): User
    {
        $raw = $perfil->getRaw();
        $correo = mb_strtolower(trim((string) $perfil->getEmail()));
        $sub = (string) $perfil->getId();

        $dominioValido = ($raw['hd'] ?? null) === $this->dominio()
            && ($raw['email_verified'] ?? false) === true
            && str_ends_with($correo, '@'.$this->dominio());
        if (! $dominioValido || $sub === '') {
            $this->rechazar($correo, null, 'domain', __('Use su correo institucional @:dominio.', ['dominio' => $this->dominio()]));
        }

        $usuario = User::query()->where('google_sub', $sub)->first()
            ?? User::query()->where('institutional_email', $correo)->first();

        $usuario ??= $this->registrarSinRol($correo, $raw, (string) $perfil->getName());
        if ($usuario->google_sub !== null && $usuario->google_sub !== $sub) {
            $this->rechazar($correo, $usuario->id, 'sub_mismatch', __('Su cuenta no está habilitada; contacte al administrador.'));
        }
        if (! $usuario->is_active) {
            $this->rechazar($correo, $usuario->id, 'inactive', __('Su cuenta está desactivada; contacte al administrador.'));
        }

        // Primero se guarda (el usuario es el actor de la auditoría, sin sesión todavía) y solo entonces se inicia la sesión.
        Auth::setUser($usuario);
        try {
            DbContext::run(fn () => $usuario->forceFill(['google_sub' => $sub, 'last_login_at' => now(), 'avatar_url' => $this->foto($raw)])->save(), 'login');
        } catch (QueryException $excepcion) {
            throw DbError::toDomain($excepcion);
        }
        Auth::login($usuario);
        session()->regenerate();
        $this->registrar($correo, $usuario->id, 'success');

        return $usuario;
    }

    /**
     * Autorregistro sin rol (ADR-038): una cuenta @ues.edu.sv válida pertenece a la universidad. Entra sin módulos
     * hasta que alguien le asigne un rol. Aún no hay actor, así que el alta va como escritura del sistema y queda
     * en la auditoría como `user.self_registered`. Si dos primeros ingresos llegan a la vez, el UNIQUE del correo
     * decide y el segundo usa la cuenta creada.
     *
     * @param  array<string, mixed>  $raw
     */
    private function registrarSinRol(string $correo, array $raw, string $nombreCompleto): User
    {
        [$nombres, $apellidos] = $this->nombres($raw, $nombreCompleto, $correo);

        try {
            return DbContext::system(fn () => User::query()->create([
                'institutional_email' => $correo, 'first_name' => Nombre::normalizar($nombres), 'last_name' => Nombre::normalizar($apellidos), 'is_active' => true,
            ]), 'user.self_registered');
        } catch (UniqueConstraintViolationException) {
            return User::query()->where('institutional_email', $correo)->firstOrFail();
        }
    }

    /**
     * Nombres y apellidos del perfil de Google; si faltan, se parte el nombre completo o se usa el correo.
     *
     * @param  array<string, mixed>  $raw
     * @return array{0: string, 1: string}
     */
    private function nombres(array $raw, string $nombreCompleto, string $correo): array
    {
        $nombres = trim((string) ($raw['given_name'] ?? ''));
        $apellidos = trim((string) ($raw['family_name'] ?? ''));
        if ($nombres === '') {
            $partes = preg_split('/\s+/', trim($nombreCompleto), 2) ?: [];
            $nombres = ($partes[0] ?? '') !== '' ? $partes[0] : strstr($correo, '@', true);
            $apellidos = $apellidos !== '' ? $apellidos : ($partes[1] ?? '');
        }

        return [mb_substr($nombres, 0, 100), mb_substr($apellidos, 0, 100)];
    }

    /**
     * Foto de perfil (ADR-039): solo se enlaza, nunca se descarga, y solo si viene de googleusercontent.com por https.
     *
     * @param  array<string, mixed>  $raw
     */
    private function foto(array $raw): ?string
    {
        $url = $raw['picture'] ?? null;
        if (! is_string($url) || mb_strlen($url) > 500 || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return null;
        }
        $host = (string) parse_url($url, PHP_URL_HOST);

        return $host === 'googleusercontent.com' || str_ends_with($host, '.googleusercontent.com') ? $url : null;
    }

    /**
     * @return never
     */
    private function rechazar(string $correo, ?int $usuarioId, string $motivo, string $mensaje): void
    {
        $this->registrar($correo, $usuarioId, 'failure', $motivo);

        throw ValidationException::withMessages(['google' => $mensaje])->redirectTo(route('login'));
    }

    /**
     * `login_history` es de solo inserción (inmutable en PostgreSQL) y no lleva auditoría: no necesita DbContext.
     */
    private function registrar(string $correo, ?int $usuarioId, string $resultado, ?string $motivo = null): void
    {
        DB::table('login_history')->insert([
            'user_id' => $usuarioId,
            'attempted_email' => $correo === '' ? '(sin correo)' : $correo,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'result' => $resultado,
            'failure_reason' => $motivo,
            'occurred_at' => now(),
        ]);
    }
}
