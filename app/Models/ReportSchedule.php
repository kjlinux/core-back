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
        'filters' => 'array',
        'recipients' => 'array',
        'is_active' => 'boolean',
        'last_sent_at' => 'datetime',
        'next_run_at' => 'datetime',
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
            self::FREQ_DAILY => $base->copy()->addDay()->startOfDay()->addHours(6),
            self::FREQ_WEEKLY => $base->copy()->addWeek()->startOfWeek()->addHours(6),
            self::FREQ_MONTHLY => $base->copy()->addMonthNoOverflow()->startOfMonth()->addHours(6),
            default => $base->copy()->addDay(),
        };
    }

    /**
     * Fenêtre [start_date, end_date] (chaînes Y-m-d) de la période écoulée
     * complète, dérivée de la fréquence. Sert à borner le rapport généré lors
     * de l'envoi planifié (sinon le rapport couvrirait tout l'historique, et
     * le rapport de présence — qui exige start_date/end_date — échouerait).
     *
     * @return array{start_date: string, end_date: string}
     */
    public function reportingWindow(?Carbon $asOf = null): array
    {
        $base = $asOf ?? now();

        return match ($this->frequency) {
            self::FREQ_WEEKLY => [
                'start_date' => $base->copy()->subWeek()->startOfWeek()->toDateString(),
                'end_date' => $base->copy()->subWeek()->endOfWeek()->toDateString(),
            ],
            self::FREQ_MONTHLY => [
                'start_date' => $base->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                'end_date' => $base->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            default => [
                'start_date' => $base->copy()->subDay()->toDateString(),
                'end_date' => $base->copy()->subDay()->toDateString(),
            ],
        };
    }
}
