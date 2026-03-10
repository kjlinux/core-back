<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewSubmission extends Model
{
    use HasUuid;

    protected $fillable = [
        'review_config_id',
        'recommendations',
        'channel',
    ];

    public function config(): BelongsTo
    {
        return $this->belongsTo(ReviewConfig::class, 'review_config_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ReviewAnswer::class);
    }
}
