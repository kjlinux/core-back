<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeelbackAlert extends Model
{
    use HasUuid;

    protected $fillable = [
        'device_id',
        'site_id',
        'type',
        'message',
        'threshold',
        'current_value',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'threshold' => 'integer',
        'current_value' => 'integer',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(FeelbackDevice::class, 'device_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
