<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewConfig extends Model
{
    use BelongsToCompany, HasUuid;

    protected $fillable = [
        'company_id',
        'token',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ReviewQuestion::class)->orderBy('order_index');
    }

    public function channels(): HasMany
    {
        return $this->hasMany(ReviewChannel::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ReviewSubmission::class);
    }
}
