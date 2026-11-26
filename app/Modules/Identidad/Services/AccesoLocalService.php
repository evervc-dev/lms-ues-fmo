<?php

namespace App\Modules\Identidad\Services;

use App\Models\User;
use App\Support\DbContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Ingreso con contraseña de la cuenta superadministradora (ADR-082), por una ruta aparte de `/login`. Solo entra una
 * cuenta con el rol `superadmin`: el resto sigue entrando con Google (ADR-007). Cada intento queda en `login_history`
 * y hay límite de intentos por correo e IP.
 */
class AccesoLocalService
{
    public const ROL = 'superadmin';

    private const INTENTOS = 5;

    private const BLOQUEO_SEGUNDOS = 300;

    /**
     * @throws ValidationException con la clave `correo` y el mensaje para el formulario.
     */
    public function entrar(string $correo, string $clave): User
    {
        $correo = mb_strtolower(trim($correo));
        $llave = 'acceso-local:'.$correo.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($llave, self::INTENTOS)) {
            $this->registrar($correo, null, 'failure', 'throttled');

            throw ValidationException::withMessages(['correo' => __('Demasiados intentos. Intente de nuevo en :m minutos.', ['m' => (int) ceil(RateLimiter::availableIn($llave) / 60)])]);
        }

        $usuario = User::query()->whereRaw('lower(institutional_email) = ?', [$correo])->first();
        $valido = $usuario !== null && $usuario->hasRole(self::ROL) && $usuario->password !== null && Hash::check($clave, $usuario->password);
        if (! $valido) {
            RateLimiter::hit($llave, self::BLOQUEO_SEGUNDOS);
            $this->registrar($correo, $usuario?->id, 'failure', 'bad_credentials');

            throw ValidationException::withMessages(['correo' => __('El correo o la contraseña no son correctos.')]);
        }
        if (! $usuario->is_active) {
            $this->registrar($correo, $usuario->id, 'failure', 'inactive');

            throw ValidationException::withMessages(['correo' => __('Su cuenta está desactivada; contacte al administrador.')]);
        }

        RateLimiter::clear($llave);
        // Como en GoogleAuthService: primero se guarda con el usuario como actor y luego se inicia la sesión.
        Auth::setUser($usuario);
        DbContext::run(fn () => $usuario->forceFill(['last_login_at' => now()])->save(), 'login');
        Auth::login($usuario);
        session()->regenerate();
        $this->registrar($correo, $usuario->id, 'success');

        return $usuario;
    }

    /**
     * Crea o actualiza la cuenta superadministradora con el correo y la contraseña de config/identidad.php. Es
     * idempotente: vuelve a fijar la contraseña, la activa y le deja solo el rol `superadmin` (rol exclusivo).
     *
     * Es la cuenta de emergencia (`is_emergency`): la base solo deja tener contraseña a esa cuenta
     * (CHECK users_password_only_emergency) y solo admite una (users_single_emergency_unique). Sin la marca,
     * PostgreSQL rechazaba la fila; en SQLite no hay CHECK y no se notaba.
     *
     * @throws \RuntimeException si falta la contraseña o el rol (corra las migraciones).
     */
    public function crearCuentaSuperadmin(): User
    {
        $correo = mb_strtolower(trim((string) config('identidad.superadmin.email')));
        $clave = (string) config('identidad.superadmin.password');
        if ($clave === '') {
            throw new \RuntimeException(__('Defina SUPERADMIN_PASSWORD en el entorno antes de crear la cuenta.'));
        }
        if (! Role::query()->where('name', self::ROL)->where('guard_name', 'web')->exists()) {
            throw new \RuntimeException(__('El rol superadmin no existe; corra las migraciones.'));
        }

        $otra = User::query()->where('is_emergency', true)->whereRaw('lower(institutional_email) <> ?', [$correo])->value('institutional_email');
        if ($otra !== null) {
            throw new \RuntimeException(__('Ya existe otra cuenta de emergencia (:correo); solo puede haber una. Use ese correo en SUPERADMIN_EMAIL.', ['correo' => $otra]));
        }

        return DbContext::system(function () use ($correo, $clave) {
            $usuario = User::query()->whereRaw('lower(institutional_email) = ?', [$correo])->first() ?? new User(['institutional_email' => $correo]);
            $usuario->forceFill([
                'is_emergency' => true,
                'first_name' => $usuario->first_name ?: 'Super',
                'last_name' => $usuario->last_name ?: 'Administrador',
                'is_active' => true,
                'deactivated_at' => null,
                'deactivated_by' => null,
                'password' => Hash::make($clave),
            ])->save();
            $usuario->syncRoles([self::ROL]);

            return $usuario;
        }, 'user.superadmin_provisioned');
    }

    /**
     * `login_history` es de solo inserción y no lleva auditoría: no necesita DbContext.
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
