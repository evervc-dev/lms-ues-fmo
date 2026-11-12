<?php

namespace App\Modules\Identidad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evento del ciclo de vida de un dispositivo: registered, used o revoked (RF27).
 */
class DeviceEvent extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['device_id', 'event_type', 'occurred_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<StudentDevice, $this>
     */
    public function dispositivo(): BelongsTo
    {
        return $this->belongsTo(StudentDevice::class, 'device_id');
    }
}
