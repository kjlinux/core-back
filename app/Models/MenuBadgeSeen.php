<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuBadgeSeen extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'badge_key',
        'scope',
        'seen_count',
    ];

    protected function casts(): array
    {
        return [
            'seen_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
