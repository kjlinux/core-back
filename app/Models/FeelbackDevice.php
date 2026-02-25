<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeelbackDevice extends Model
{
    use HasUuid, BelongsToCompany;

    protected $fillable = [
        'serial_number',
        'company_id',
        'site_id',
        'is_online',
        'battery_level',
        'last_ping_at',
        'assigned_agent',
        'mqtt_topic',
    ];

    protected $casts = [
        'is_online' => 'boolean',
        'battery_level' => 'integer',
        'last_ping_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(FeelbackEntry::class, 'device_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(FeelbackAlert::class, 'device_id');
    }
}
