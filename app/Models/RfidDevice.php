<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfidDevice extends Model
{
    use HasUuid, BelongsToCompany;

    protected $fillable = [
        'serial_number',
        'name',
        'company_id',
        'site_id',
        'is_online',
        'last_ping_at',
        'mqtt_topic',
    ];

    protected $casts = [
        'is_online' => 'boolean',
        'last_ping_at' => 'datetime',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
