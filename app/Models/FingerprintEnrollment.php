<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FingerprintEnrollment extends Model
{
    use HasUuid;

    protected $fillable = [
        'employee_id',
        'device_id',
        'status',
        'enrolled_at',
        'template_hash',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'device_id');
    }
}
