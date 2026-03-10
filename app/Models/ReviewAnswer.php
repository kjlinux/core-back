<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewAnswer extends Model
{
    use HasUuid;

    protected $fillable = [
        'review_submission_id',
        'review_question_id',
        'stars',
    ];

    protected $casts = [
        'stars' => 'integer',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ReviewSubmission::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ReviewQuestion::class);
    }
}
