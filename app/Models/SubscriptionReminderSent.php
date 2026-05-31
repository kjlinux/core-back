<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionReminderSent extends Model
{
    protected $table = 'subscription_reminders_sent';

    protected $fillable = [
        'company_id',
        'days_left',
        'sent_on',
    ];

    protected function casts(): array
    {
        return [
            'days_left' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
