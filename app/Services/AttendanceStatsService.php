<?php

namespace App\Services;

use App\Enums\ExpectedDaysStrategy;
use App\Models\Employee;
use App\Models\PayrollConfig;
use App\Support\Concerns\CountsApprovedLeaveDays;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Calcule les statistiques de présence d'un rapport de pointage.
 *
 * Service PUR : il ne requête jamais la base — toutes les données (pointages,
 * horaires, jours fériés, congés) lui sont fournies préchargées par l'appelant.
 * Cela évite les N+1 et rend le service testable sans base de données.
 *
 * Le taux de présence se calcule sur les JOURS OUVRÉS ATTENDUS (et non sur le
 * nombre de pointages trouvés) ; les absences en sont dérivées :
 *   absences = jours_ouvrés − jours_présents_plafonnés − congés_approuvés.
 */
class AttendanceStatsService
{
    use CountsApprovedLeaveDays;

    /**
     * Statuts comptés comme « venu » (hors retard, traité à part). `complete`
     * est un statut de segment qui peut transiter ici par sécurité.
     */
    private const PRESENT_STATUSES = ['present', 'left_early', 'complete'];

    public function __construct(
        private readonly ScheduleResolverService $scheduleResolver,
    ) {}

    /**
     * Assemble les lignes et totaux du rapport pour un ensemble d'employés.
     *
     * @param  Collection<int, Employee>  $employees  employés actifs filtrés
     * @param  Collection<string, Collection>  $recordsByEmployee  pointages groupés par employee_id
     * @param  Collection<int, \App\Models\Schedule>  $companySchedules
     * @param  Collection<int, \App\Models\Holiday>  $companyHolidays
     * @param  Collection<string, Collection>  $leavesByEmployee  congés approuvés groupés par employee_id
     * @param  string|null  $typeFilter  daily|monthly (indicatif) | late | absence (filtre les lignes)
     * @return array{totalEmployees:int, totalPresent:int, totalAbsent:int, totalLate:int, totalLeave:int, globalRate:float, rows:array<int, array<string, mixed>>}
     */
    public function buildReport(
        Collection $employees,
        Carbon $start,
        Carbon $end,
        Collection $recordsByEmployee,
        Collection $companySchedules,
        Collection $companyHolidays,
        Collection $leavesByEmployee,
        ExpectedDaysStrategy $strategy,
        ?PayrollConfig $config = null,
        ?string $typeFilter = null,
    ): array {
        $rows = $employees->map(function (Employee $employee) use (
            $start, $end, $recordsByEmployee, $companySchedules, $companyHolidays, $leavesByEmployee, $strategy, $config
        ) {
            $stats = $this->statsForEmployee(
                $employee,
                $start,
                $end,
                $recordsByEmployee->get($employee->id, collect()),
                $companySchedules,
                $companyHolidays,
                $leavesByEmployee->get($employee->id, collect()),
                $strategy,
                $config,
            );

            return [
                'employeeId' => (string) $employee->id,
                'employee' => $employee->first_name.' '.$employee->last_name,
                'department' => $employee->department?->name ?? '-',
                'site' => $employee->site?->name ?? '-',
                'present' => $stats['presentDays'],
                'absent' => $stats['absentDays'],
                'late' => $stats['lateDays'],
                'leave' => $stats['leaveDays'],
                'expected' => $stats['expectedWorkingDays'],
                'overtime' => $stats['overtimeHours'],
                'rate' => $stats['rate'],
                '_attended' => $stats['attendedDays'],
            ];
        })->values();

        if ($typeFilter === 'late') {
            $rows = $rows->filter(fn ($r) => $r['late'] > 0)->values();
        } elseif ($typeFilter === 'absence') {
            $rows = $rows->filter(fn ($r) => $r['absent'] > 0)->values();
        }

        $totalExpected = (int) $rows->sum('expected');
        $totalAttended = (int) $rows->sum('_attended');
        $globalRate = $totalExpected > 0
            ? min(100.0, round(($totalAttended / $totalExpected) * 100, 1))
            : 0.0;

        $outRows = $rows->map(fn ($r) => Arr::except($r, ['_attended']))->all();

        return [
            'totalEmployees' => count($outRows),
            'totalPresent' => (int) $rows->sum('present'),
            'totalAbsent' => (int) $rows->sum('absent'),
            'totalLate' => (int) $rows->sum('late'),
            'totalLeave' => (int) $rows->sum('leave'),
            'globalRate' => $globalRate,
            'rows' => $outRows,
        ];
    }

    /**
     * Statistiques de présence d'un seul employé sur [$start, $end].
     *
     * @param  Collection<int, \App\Models\AttendanceRecord>  $records  pointages de CET employé
     * @param  Collection<int, \App\Models\Schedule>  $companySchedules
     * @param  Collection<int, \App\Models\Holiday>  $companyHolidays
     * @param  Collection<int, \App\Models\AbsenceRequest>  $approvedLeaves  congés de CET employé
     * @return array{expectedWorkingDays:int, presentDays:int, lateDays:int, partialDays:int, leaveDays:int, absentDays:int, attendedDays:int, overtimeHours:float, rate:float}
     */
    public function statsForEmployee(
        Employee $employee,
        Carbon $start,
        Carbon $end,
        Collection $records,
        Collection $companySchedules,
        Collection $companyHolidays,
        Collection $approvedLeaves,
        ExpectedDaysStrategy $strategy,
        ?PayrollConfig $config = null,
    ): array {
        $presentDays = $records->whereIn('status', self::PRESENT_STATUSES)->count();
        $lateDays = $records->where('status', 'late')->count();
        $partialDays = $records->where('status', 'partial')->count();
        $attended = $presentDays + $lateDays + $partialDays;

        $overtimeMinutes = (int) $records->sum('overtime_minutes');
        $overtimeHours = $overtimeMinutes > 0 ? round($overtimeMinutes / 60, 1) : 0.0;

        $leaveDays = $this->countLeaveDaysFromCollection($approvedLeaves, $start, $end);

        $expected = $this->expectedWorkingDays($employee, $start, $end, $companySchedules, $companyHolidays, $strategy, $config);

        // Repli sur la config si l'approche horaire ne donne aucun jour attendu
        // alors qu'aucun horaire n'est configuré, ou que l'employé a tout de même
        // pointé (horaire manquant/mal assigné). Évite d'afficher 100 % d'absence
        // à tort pour une entreprise sans horaires.
        if (
            $strategy === ExpectedDaysStrategy::ScheduleBased
            && $expected === 0
            && ($companySchedules->isEmpty() || $records->isNotEmpty())
        ) {
            $expected = $this->expectedWorkingDaysConfigBased($employee, $start, $end, $config);
        }

        $cappedAttended = min($attended, $expected);
        $absentDays = max(0, $expected - $cappedAttended - $leaveDays);
        $rate = $expected > 0
            ? min(100.0, round(($cappedAttended / $expected) * 100, 1))
            : 0.0;

        return [
            'expectedWorkingDays' => $expected,
            'presentDays' => $presentDays,
            'lateDays' => $lateDays,
            'partialDays' => $partialDays,
            'leaveDays' => $leaveDays,
            'absentDays' => $absentDays,
            'attendedDays' => $cappedAttended,
            'overtimeHours' => $overtimeHours,
            'rate' => $rate,
        ];
    }

    private function expectedWorkingDays(
        Employee $employee,
        Carbon $start,
        Carbon $end,
        Collection $companySchedules,
        Collection $companyHolidays,
        ExpectedDaysStrategy $strategy,
        ?PayrollConfig $config,
    ): int {
        return match ($strategy) {
            ExpectedDaysStrategy::ScheduleBased => $this->expectedWorkingDaysScheduleBased($employee, $start, $end, $companySchedules, $companyHolidays),
            ExpectedDaysStrategy::ConfigBased => $this->expectedWorkingDaysConfigBased($employee, $start, $end, $config),
        };
    }

    /**
     * Jours ouvrés = jours de [$start, $end] tombant sur un jour travaillé de
     * l'horaire de l'employé, hors jours fériés. La résolution d'horaire est
     * mémoïsée par jour ISO (≤ 7 résolutions par employé), sur la collection
     * d'horaires préchargée (aucune requête).
     */
    private function expectedWorkingDaysScheduleBased(
        Employee $employee,
        Carbon $start,
        Carbon $end,
        Collection $companySchedules,
        Collection $companyHolidays,
    ): int {
        $effectiveStart = $this->clampToHire($employee, $start);
        if ($effectiveStart->gt($end)) {
            return 0;
        }

        $holidayKeys = $this->holidayKeySet($companyHolidays);
        $resolvedByWeekday = [];
        $count = 0;

        for ($day = $effectiveStart->copy()->startOfDay(); $day->lte($end); $day->addDay()) {
            if ($this->isHoliday($day, $holidayKeys)) {
                continue;
            }

            $isoDay = $day->isoWeekday();
            if (! array_key_exists($isoDay, $resolvedByWeekday)) {
                $resolvedByWeekday[$isoDay] = $this->scheduleResolver->resolveForEmployee(
                    (string) $employee->company_id,
                    $employee->department_id,
                    $day,
                    $employee->schedule_id,
                    $companySchedules,
                );
            }

            $schedule = $resolvedByWeekday[$isoDay];
            if ($schedule !== null && $this->scheduleResolver->isActiveOnDay($schedule, $isoDay)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Jours ouvrés basés sur working_days_per_month, avec prorata d'embauche
     * (logique alignée sur PayrollService, mais ancrée sur hire_date). Convient
     * surtout aux périodes mensuelles ; sert de repli quand aucun horaire n'existe.
     */
    private function expectedWorkingDaysConfigBased(
        Employee $employee,
        Carbon $start,
        Carbon $end,
        ?PayrollConfig $config,
    ): int {
        $workingDays = $config?->working_days_per_month ?? 26;
        $hire = $this->hireAnchor($employee);

        if ($hire && $hire->gt($end)) {
            return 0;
        }

        if ($hire && $hire->gt($start)) {
            $totalPeriodDays = $start->diffInDays($end) + 1;
            $employeeDaysInPeriod = $hire->diffInDays($end) + 1;

            return (int) round($workingDays * $employeeDaysInPeriod / max(1, $totalPeriodDays));
        }

        return $workingDays;
    }

    private function clampToHire(Employee $employee, Carbon $start): Carbon
    {
        $hire = $this->hireAnchor($employee);

        return ($hire && $hire->gt($start)) ? $hire->copy() : $start->copy();
    }

    private function hireAnchor(Employee $employee): ?Carbon
    {
        $raw = $employee->hire_date ?? $employee->created_at;

        return $raw ? Carbon::parse($raw)->startOfDay() : null;
    }

    /**
     * Construit un index des jours fériés : clés 'Y-m-d' (date fixe) et 'm-d'
     * (récurrent, même jour chaque année).
     *
     * @param  Collection<int, \App\Models\Holiday>  $companyHolidays
     * @return array<string, true>
     */
    private function holidayKeySet(Collection $companyHolidays): array
    {
        $keys = [];

        foreach ($companyHolidays as $holiday) {
            $date = $holiday->date instanceof Carbon ? $holiday->date : Carbon::parse($holiday->date);
            $keys[$date->format('Y-m-d')] = true;
            if (! empty($holiday->is_recurring)) {
                $keys[$date->format('m-d')] = true;
            }
        }

        return $keys;
    }

    /**
     * @param  array<string, true>  $holidayKeys
     */
    private function isHoliday(Carbon $day, array $holidayKeys): bool
    {
        return isset($holidayKeys[$day->format('Y-m-d')])
            || isset($holidayKeys[$day->format('m-d')]);
    }
}
