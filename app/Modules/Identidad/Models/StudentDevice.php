<?php

namespace App\Modules\Identidad\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Teléfono autorizado para la asistencia con QR (RF27, ADR-045). `credential_id` guarda el SHA-256 del token
 * de dispositivo; el token en claro nunca se guarda.
 */
class StudentDevice extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['student_id', 'credential_id', 'status', 'last_used_at', 'revoked_at', 'revoked_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['last_used_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /**
     * Hash con el que se guarda y se compara un token de dispositivo.
     */
    public static function hashDe(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * @return HasMany<DeviceEvent, $this>
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(DeviceEvent::class, 'device_id');
    }
}
