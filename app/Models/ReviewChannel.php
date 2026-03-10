<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewChannel extends Model
{
    use HasUuid;

    protected $fillable = [
        'review_config_id',
        'name',
    ];

    public function config(): BelongsTo
    {
        return $this->belongsTo(ReviewConfig::class, 'review_config_id');
    }
}
