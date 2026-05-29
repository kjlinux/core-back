<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceAlert extends Model
{
    use HasUuid;

    public const STATUS_OPEN = 'open';
    public const STATUS_ACKNOWLEDGED = 'acknowledged';
    public const STATUS_RESOLVED = 'resolved';

    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    public const TYPE_OFFLINE_THRESHOLD = 'offline_threshold';
    public const TYPE_PROLONGED_OFFLINE = 'prolonged_offline';
    public const TYPE_OTA_FAILED = 'ota_failed';
    public const TYPE_BROKER_DOWN = 'broker_down';
    public const TYPE_REVERB_DOWN = 'reverb_down';
    public const TYPE_API_ERROR = 'api_error';
    public const TYPE_ENROLLMENT_FAILED = 'enrollment_failed';
    public const TYPE_LISTENER_DOWN = 'listener_down';

    protected $fillable = [
        'company_id',
        'site_id',
        'device_id',
        'device_kind',
        'type',
        'severity',
        'title',
        'message',
        'context',
        'status',
        'acknowledged_by',
        'acknowledged_at',
        'resolved_at',
        'notified_at',
    ];

    protected $casts = [
        'context' => 'array',
        'acknowledged_at' => 'datetime',
        'resolved_at' => 'datetime',
        'notified_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
