<?php

namespace App\Modules\Identidad\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * «Entrar como…» (docs/12 Desarrollo/Setup del entorno.md): elegir un usuario sembrado sin Google.
 * Solo con LMS_DEV_LOGIN=true y nunca en producción.
 */
class AccesoDesarrolloService
{
    public function habilitado(): bool
    {
        return config('lms.dev_login') === true && ! app()->isProduction();
    }

    /**
     * Las cuentas *.dev@ues.edu.sv de los seeders, una por rol.
     *
     * @return Collection<int, User>
     */
    public function cuentasDeDesarrollo(): Collection
    {
        return User::query()->with('roles')->where('institutional_email', 'like', '%.dev@ues.edu.sv')->where('is_active', true)->orderBy('id')->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function buscar(string $texto): Collection
    {
        if (mb_strlen(trim($texto)) < 2) {
            return collect();
        }

        $patron = '%'.mb_strtolower(trim($texto)).'%';

        return User::query()->with('roles')->where('is_active', true)
            ->where(fn ($query) => $query->whereRaw('lower(institutional_email) like ?', [$patron])
                ->orWhereRaw("lower(first_name || ' ' || last_name) like ?", [$patron]))
            ->orderBy('institutional_email')->limit(10)->get();
    }

    public function entrarComo(int $usuarioId): User
    {
        abort_unless($this->habilitado(), 404);

        $usuario = User::query()->where('is_active', true)->findOrFail($usuarioId);
        Auth::login($usuario);
        session()->regenerate();

        return $usuario;
    }
}
