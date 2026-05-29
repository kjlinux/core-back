<?php

namespace App\Models;

use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSchedule extends Model
{
    use HasUuid;

    public const FREQ_DAILY = 'daily';
    public const FREQ_WEEKLY = 'weekly';
    public const FREQ_MONTHLY = 'monthly';

    protected $fillable = [
        'user_id',
        'company_id',
        'report_type',
        'format',
        'frequency',
        'filters',
        'recipients',
        'is_active',
        'last_sent_at',
        'next_run_at',
    ];

    protected $casts = [
        'filters'      => 'array',
        'recipients'   => 'array',
        'is_active'    => 'boolean',
        'last_sent_at' => 'datetime',
        'next_run_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Calcule la prochaine échéance à partir d'une base donnée.
     */
    public function computeNextRun(?Carbon $from = null): Carbon
    {
        $base = $from ?? now();

        return match ($this->frequency) {
            self::FREQ_DAILY   => $base->copy()->addDay()->startOfDay()->addHours(6),
            self::FREQ_WEEKLY  => $base->copy()->addWeek()->startOfWeek()->addHours(6),
            self::FREQ_MONTHLY => $base->copy()->addMonthNoOverflow()->startOfMonth()->addHours(6),
            default            => $base->copy()->addDay(),
        };
    }
}
