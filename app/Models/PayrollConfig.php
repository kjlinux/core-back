<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollConfig extends Model
{
    use HasUuid;

    protected $fillable = [
        'company_id',
        'default_payment_mode',
        'standard_daily_hours',
        'working_days_per_month',
        'payment_day',
        'lateness_deduction_enabled',
        'overtime_enabled',
        'overtime_rate',
    ];

    protected $casts = [
        'standard_daily_hours'      => 'integer',
        'working_days_per_month'    => 'integer',
        'payment_day'               => 'integer',
        'lateness_deduction_enabled'=> 'boolean',
        'overtime_enabled'          => 'boolean',
        'overtime_rate'             => 'float',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function latenessRules(): HasMany
    {
        return $this->hasMany(LatenessRule::class, 'company_id', 'company_id')
            ->orderBy('minutes_threshold');
    }
}
