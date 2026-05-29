<?php

namespace App\Services;

use App\Models\AbsenceRequest;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Evalue un pointage par rapport a l'horaire attendu :
 * - resout l'horaire de l'employe (scheduleId prioritaire, sinon departement)
 * - rapproche chaque pointage reel des heures cibles avec tolerance
 * - prend en compte les conges approuves (status on_leave, pas de penalite)
 * - distingue les jours non programmes (matin/soir/jour/nuit) des absences
 */
class AttendanceEvaluationService
{
    /**
     * Enrichit une collection de AttendanceRecord avec segments / expectedShift / isOnLeave.
     * Renvoie la meme collection (les modeles sont mutes en place pour la serialisation).
     */
    public function enrichRecords(Collection $records): Collection
    {
        if ($records->isEmpty()) {
            return $records;
        }

        $employeeIds = $records->pluck('employee_id')->unique()->values();
        $employees = Employee::with('schedule')->whereIn('id', $employeeIds)->get()->keyBy('id');
        $scheduleIds = $employees->pluck('schedule_id')->filter()->unique()->values();
        $deptIds = $employees->pluck('department_id')->filter()->unique()->values();

        // Charger tous les horaires utiles : ceux directement affectes + ceux des departements
        $schedules = Schedule::query()
            ->where(function ($q) use ($scheduleIds, $deptIds) {
                $q->whereIn('id', $scheduleIds);
                foreach ($deptIds as $deptId) {
                    $q->orWhereJsonContains('assigned_departments', $deptId);
                }
            })
            ->get();

        $minDate = $records->min('date');
        $maxDate = $records->max('date');
        $approvedLeaves = AbsenceRequest::where('status', 'approved')
            ->whereIn('employee_id', $employeeIds)
            ->where('date_start', '<=', $maxDate)
            ->where('date_end', '>=', $minDate)
            ->get()
            ->groupBy('employee_id');

        foreach ($records as $record) {
            $employee = $employees->get($record->employee_id);
            if (! $employee) {
                continue;
            }
            $schedule = $this->resolveSchedule($employee, $schedules);
            $dateStr = $record->date instanceof Carbon ? $record->date->toDateString() : (string) $record->date;

            $onLeave = $this->isOnLeave($approvedLeaves->get($employee->id), $dateStr);
            $record->is_on_leave = $onLeave;

            if (! $schedule || empty($schedule->days)) {
                $record->expected_shift = null;
                $record->segments = null;
                if ($onLeave) {
                    $record->status = 'on_leave';
                }
                continue;
            }

            $weekday = Carbon::parse($dateStr)->dayOfWeekIso; // 1=Lun..7=Dim
            $scheduleDay = collect($schedule->days)->firstWhere('weekday', $weekday);

            if (! $scheduleDay || empty($scheduleDay['worked']) || empty($scheduleDay['segments'])) {
                $record->expected_shift = null;
                $record->segments = [[
                    'kind' => 'full_day',
                    'startTime' => null,
                    'endTime' => null,
                    'punches' => [],
                    'status' => 'not_scheduled',
                ]];
                if ($onLeave) {
                    $record->status = 'on_leave';
                }
                continue;
            }

            $actualPunches = $this->extractActualPunches($record);
            $segments = [];
            foreach ($scheduleDay['segments'] as $segment) {
                $segments[] = $this->evaluateSegment($segment, $actualPunches, $onLeave, $schedule->default_late_tolerance ?? 0);
            }

            $record->expected_shift = count($segments) === 1 ? ($segments[0]['kind'] ?? null) : 'full_day';
            $record->segments = $segments;

            if ($onLeave) {
                $record->status = 'on_leave';
            } else {
                $record->status = $this->aggregateStatus($segments);
            }
        }

        return $records;
    }

    private function resolveSchedule(Employee $employee, Collection $schedules): ?Schedule
    {
        if ($employee->schedule_id) {
            $direct = $schedules->firstWhere('id', $employee->schedule_id);
            if ($direct) {
                return $direct;
            }
        }
        return $schedules->first(function (Schedule $s) use ($employee) {
            $assigned = $s->assigned_departments ?? [];
            return in_array($employee->department_id, $assigned, true);
        });
    }

    private function isOnLeave(?Collection $employeeLeaves, string $date): bool
    {
        if (! $employeeLeaves) {
            return false;
        }
        foreach ($employeeLeaves as $leave) {
            if ($leave->coversDate($date)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Extrait les heures de pointage reelles a partir de entry_time/exit_time.
     * (Modele simple : 1 entree + 1 sortie. Pour multi-pointages stocker un JSON.)
     */
    private function extractActualPunches(AttendanceRecord $record): array
    {
        $punches = [];
        if ($record->entry_time) {
            $punches[] = Carbon::parse($record->entry_time)->format('H:i');
        }
        if ($record->exit_time) {
            $punches[] = Carbon::parse($record->exit_time)->format('H:i');
        }
        return $punches;
    }

    private function evaluateSegment(array $segment, array $actualPunches, bool $onLeave, int $defaultTolerance): array
    {
        $expected = $segment['expectedPunches'] ?? [];
        $tolerance = $segment['lateTolerance'] ?? $defaultTolerance;

        $punchEvals = [];
        $assigned = array_fill(0, count($actualPunches), false);

        foreach ($expected as $exp) {
            $expTime = $exp['time'] ?? null;
            if (! $expTime) {
                continue;
            }
            $bestIdx = null;
            $bestDiff = PHP_INT_MAX;
            foreach ($actualPunches as $i => $actual) {
                if ($assigned[$i]) continue;
                $diff = $this->minutesDiff($expTime, $actual);
                if (abs($diff) < abs($bestDiff)) {
                    $bestDiff = $diff;
                    $bestIdx = $i;
                }
            }
            if ($bestIdx === null) {
                $punchEvals[] = [
                    'expectedTime' => $expTime,
                    'actualTime' => null,
                    'status' => 'missing',
                    'lateMinutes' => 0,
                ];
                continue;
            }
            $assigned[$bestIdx] = true;
            $actualTime = $actualPunches[$bestIdx];
            $lateMin = max(0, $bestDiff); // bestDiff positif = en retard
            $status = $lateMin <= $tolerance ? 'on_time' : 'late';
            $punchEvals[] = [
                'expectedTime' => $expTime,
                'actualTime' => $actualTime,
                'status' => $status,
                'lateMinutes' => $status === 'late' ? $lateMin : 0,
            ];
        }

        if ($onLeave) {
            $status = 'on_leave';
        } else {
            $status = $this->aggregateSegmentStatus($punchEvals);
        }

        return [
            'kind' => $segment['kind'] ?? 'full_day',
            'startTime' => $segment['startTime'] ?? null,
            'endTime' => $segment['endTime'] ?? null,
            'punches' => $punchEvals,
            'status' => $status,
        ];
    }

    private function aggregateSegmentStatus(array $punches): string
    {
        if (empty($punches)) return 'absent';
        $missing = 0; $late = 0; $onTime = 0;
        foreach ($punches as $p) {
            if ($p['status'] === 'missing') $missing++;
            elseif ($p['status'] === 'late') $late++;
            else $onTime++;
        }
        if ($missing === count($punches)) return 'absent';
        if ($missing > 0) return 'partial';
        if ($late > 0) return 'late';
        return 'complete';
    }

    private function aggregateStatus(array $segments): string
    {
        $active = array_filter($segments, fn($s) => ($s['status'] ?? '') !== 'not_scheduled');
        if (empty($active)) return 'present';
        $statuses = array_column($active, 'status');
        if (in_array('absent', $statuses, true) && count(array_filter($statuses, fn($s) => $s === 'absent')) === count($active)) {
            return 'absent';
        }
        if (in_array('absent', $statuses, true) || in_array('partial', $statuses, true)) {
            return 'partial';
        }
        if (in_array('late', $statuses, true)) return 'late';
        return 'present';
    }

    private function minutesDiff(string $expected, string $actual): int
    {
        [$eh, $em] = array_map('intval', explode(':', $expected));
        [$ah, $am] = array_map('intval', explode(':', $actual));
        return ($ah * 60 + $am) - ($eh * 60 + $em);
    }
}
