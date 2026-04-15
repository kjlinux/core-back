<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LatenessRule extends Model
{
    use HasUuid;

    protected $fillable = [
        'company_id',
        'tolerance_minutes',
        'minutes_threshold',
        'penalty_value',
        'penalty_type',
        'apply_per',
    ];

    protected $casts = [
        'tolerance_minutes' => 'integer',
        'minutes_threshold' => 'integer',
        'penalty_value'     => 'float',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
