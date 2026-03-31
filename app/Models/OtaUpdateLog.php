<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtaUpdateLog extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'device_id',
        'device_kind',
        'firmware_version_id',
        'status',
        'triggered_by',
        'started_at',
        'completed_at',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function firmwareVersion(): BelongsTo
    {
        return $this->belongsTo(FirmwareVersion::class, 'firmware_version_id');
    }
}
