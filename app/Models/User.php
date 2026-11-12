<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

/**
 * Usuario institucional (docs/07 Datos/Modelo entidad-relación.md · Identidad y acceso).
 * Entra solo con Google @ues.edu.sv (ADR-007); `password` existe solo para la cuenta de emergencia.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'institutional_email',
        'google_sub',
        'first_name',
        'last_name',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'google_calendar_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_emergency' => 'boolean',
            'deactivated_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'google_calendar_token' => 'encrypted',
        ];
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Los correos de las notificaciones (RF23) van al correo institucional: la columna `email` no existe.
     */
    public function routeNotificationForMail(): string
    {
        return $this->institutional_email;
    }

    /**
     * Iniciales para el avatar cuando no hay foto (o la de Google no carga).
     */
    public function iniciales(): string
    {
        return Str::upper(Str::substr((string) $this->first_name, 0, 1).Str::substr((string) $this->last_name, 0, 1));
    }
}
