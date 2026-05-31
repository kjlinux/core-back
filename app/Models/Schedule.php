<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use BelongsToCompany, HasFactory, HasUuid;

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'default_late_tolerance',
        'days',
        'start_time',
        'end_time',
        'break_start',
        'break_end',
        'work_days',
        'late_tolerance',
        'assigned_departments',
    ];

    protected $casts = [
        'work_days' => 'array',
        'days' => 'array',
        'assigned_departments' => 'array',
        'late_tolerance' => 'integer',
        'default_late_tolerance' => 'integer',
    ];

    /**
     * Backfill des champs plats legacy (start_time/end_time/work_days/late_tolerance)
     * a partir de la nouvelle structure `days`. Permet aux composants existants
     * (ScheduleResolverService, MQTT listeners, QrAttendance) de continuer a
     * fonctionner sans modification.
     */
    protected static function booted(): void
    {
        static::saving(function (Schedule $schedule) {
            $days = $schedule->days;
            if (! is_array($days) || empty($days)) {
                return;
            }

            $workedDays = array_values(array_filter($days, fn ($d) => ! empty($d['worked']) && ! empty($d['segments'])));
            if (empty($workedDays)) {
                return;
            }

            // work_days legacy : liste des weekday (ISO 1..7) reellement travailles
            if (empty($schedule->work_days)) {
                $schedule->work_days = array_values(array_map(fn ($d) => (int) $d['weekday'], $workedDays));
            }

            // start_time / end_time legacy : premier et dernier segment du premier jour travaille
            if (empty($schedule->start_time) || empty($schedule->end_time)) {
                $segments = $workedDays[0]['segments'] ?? [];
                if (! empty($segments)) {
                    $firstSegment = $segments[0];
                    $lastSegment = $segments[count($segments) - 1];
                    if (empty($schedule->start_time) && ! empty($firstSegment['startTime'])) {
                        $schedule->start_time = $firstSegment['startTime'];
                    }
                    if (empty($schedule->end_time) && ! empty($lastSegment['endTime'])) {
                        $schedule->end_time = $lastSegment['endTime'];
                    }
                }
            }

            // late_tolerance legacy : la tolerance par defaut sert de valeur globale
            if ($schedule->late_tolerance === null && $schedule->default_late_tolerance !== null) {
                $schedule->late_tolerance = $schedule->default_late_tolerance;
            }
        });
    }
}
