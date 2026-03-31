<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FirmwareVersion extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'version',
        'device_kind',
        'description',
        'file_path',
        'file_size',
        'is_auto_update',
        'is_published',
        'published_at',
        'uploaded_by',
    ];

    protected $casts = [
        'is_auto_update' => 'boolean',
        'is_published'   => 'boolean',
        'file_size'      => 'integer',
        'published_at'   => 'datetime',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function updateLogs(): HasMany
    {
        return $this->hasMany(OtaUpdateLog::class, 'firmware_version_id');
    }
}
