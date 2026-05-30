<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPayment extends Model
{
    use HasUuid;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id',
        'from_plan',
        'to_plan',
        'amount_xof',
        'is_prorata',
        'period_start',
        'period_end',
        'payment_method',
        'payment_status',
        'gateway_token',
        'gateway_response',
        'triggered_by_user_id',
        'triggered_by_superadmin',
    ];

    protected $casts = [
        'is_prorata' => 'boolean',
        'triggered_by_superadmin' => 'boolean',
        'amount_xof' => 'integer',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'gateway_response' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
