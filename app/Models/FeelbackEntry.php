<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeelbackEntry extends Model
{
    use HasUuid;

    protected $fillable = [
        'device_id',
        'level',
        'site_id',
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
