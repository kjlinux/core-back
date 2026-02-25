<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiometricDevice extends Model
{
    use HasUuid, BelongsToCompany;

    protected $fillable = [
        'serial_number',
        'company_id',
        'site_id',
        'name',
        'is_online',
        'last_sync_at',
        'firmware_version',
        'enrolled_count',
        'mqtt_topic',
    ];

    protected $casts = [
        'is_online' => 'boolean',
        'last_sync_at' => 'datetime',
        'enrolled_count' => 'integer',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(FingerprintEnrollment::class, 'device_id');
    }
}
