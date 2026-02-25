<?php

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfidCard extends Model
{
    use HasFactory, HasUuid, BelongsToCompany;

    protected $fillable = [
        'uid',
        'employee_id',
        'company_id',
        'status',
        'assigned_at',
        'blocked_at',
        'block_reason',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'blocked_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(CardHistory::class, 'card_id');
    }
}
