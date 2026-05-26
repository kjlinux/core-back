<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionHistory extends Model
{
    public const EVENT_SUBSCRIBED = 'subscribed';
    public const EVENT_UPGRADED = 'upgraded';
    public const EVENT_DOWNGRADED = 'downgraded';
    public const EVENT_RENEWED = 'renewed';
    public const EVENT_PREPAID = 'prepaid';
    public const EVENT_ROLLED_OVER = 'rolled_over';
    public const EVENT_EXPIRED = 'expired';
    public const EVENT_ADMIN_CHANGED = 'admin_changed';

    public $timestamps = false;

    protected $table = 'subscription_history';

    protected $fillable = [
        'company_id',
        'event',
        'from_plan',
        'to_plan',
        'actor_user_id',
        'payment_id',
        'notes',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPayment::class, 'payment_id');
    }
}
