<?php

namespace App\Modules\Identidad\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solicitud del estudiante para cambiar su teléfono; Soporte la aprueba o la rechaza (RF27).
 */
class DeviceChangeRequest extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['student_id', 'reason', 'justification_path', 'status', 'resolved_by', 'resolved_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
