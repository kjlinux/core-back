<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LatenessRule;
use App\Models\Payslip;
use App\Models\PayrollConfig;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PayrollService
{
    /**
     * Genere (ou regenere) les fiches de paie pour tous les employes
     * correspondant aux filtres, sur la periode donnee.
     *
     * @return Collection<Payslip>
     */
    public function generatePayslips(
        string $companyId,
        string $periodStart,
        string $periodEnd,
        ?string $siteId = null,
        ?string $departmentId = null,
    ): Collection {
        $start  = Carbon::parse($periodStart);
        $end    = Carbon::parse($periodEnd);
        $period = $start->format('Y-m');

        // Charger la config de paie de l'entreprise
        $config = PayrollConfig::with('latenessRules')
            ->where('company_id', $companyId)
            ->first();

        // Charger les employes concernes
        $query = Employee::where('company_id', $companyId)
            ->where('is_active', true);

        if ($siteId) {
            $query->where('site_id', $siteId);
        }
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $employees = $query->with(['site', 'department', 'rfidCard'])->get();

        $payslips = collect();

        foreach ($employees as $employee) {
            $payslip = $this->generateForEmployee($employee, $config, $start, $end, $period);
            $payslips->push($payslip);
        }

        return $payslips;
    }

    private function generateForEmployee(
        Employee $employee,
        ?PayrollConfig $config,
        Carbon $start,
        Carbon $end,
        string $period,
    ): Payslip {
        $paymentMode = $employee->payment_mode ?? $config?->default_payment_mode ?? 'monthly';
        $baseSalary  = $employee->base_salary ?? 0;
        $workingDays = $config?->working_days_per_month ?? 26;
        $dailyHours  = $config?->standard_daily_hours ?? 8;

        // Charger les presences de la periode
        $records = AttendanceRecord::where('employee_id', $employee->id)
            ->whereBetween('check_in', [$start->startOfDay(), $end->endOfDay()])
            ->get();

        // Calcul des jours et heures travailles
        $workedDays   = $records->count();
        $workedHours  = $records->sum(function ($r) {
            if (!$r->check_in || !$r->check_out) {
                return 0;
            }
            return Carbon::parse($r->check_in)->diffInMinutes(Carbon::parse($r->check_out)) / 60;
        });

        // Calcul des absences (jours ouvrables - jours pointes)
        $absentDays = max(0, $workingDays - $workedDays);

        // Calcul des retards en minutes
        $totalLatenessMinutes = $records->sum(function ($r) {
            return max(0, $r->lateness_minutes ?? 0);
        });

        // Calcul du salaire brut selon le mode
        $grossAmount = match ($paymentMode) {
            'monthly'   => $baseSalary,
            'daily'     => $baseSalary * $workedDays,
            'hourly'    => (int) round($baseSalary * $workedHours),
            'weekly'    => (int) round($baseSalary * ($workedDays / 5)),
            'forfait'   => $baseSalary,
            default     => $baseSalary,
        };

        // Heures supplementaires
        $overtimeHours  = 0.0;
        $overtimeAmount = 0;
        if ($config?->overtime_enabled && $paymentMode === 'hourly') {
            $standardHours   = $workingDays * $dailyHours;
            $overtimeHours   = max(0, $workedHours - $standardHours);
            $hourlyRate      = $baseSalary;
            $overtimeAmount  = (int) round($overtimeHours * $hourlyRate * ($config->overtime_rate - 1));
            $grossAmount    += $overtimeAmount;
        }

        // Deduction absences (prorata jours ouvrables)
        $absenceDeduction = 0;
        if ($baseSalary > 0 && $workingDays > 0) {
            $dailySalary      = $baseSalary / $workingDays;
            $absenceDeduction = (int) round($dailySalary * $absentDays);
        }

        // Deduction retards
        $latenessDeduction = 0;
        if ($config?->lateness_deduction_enabled && $totalLatenessMinutes > 0 && $config->latenessRules->isNotEmpty()) {
            $latenessDeduction = $this->calculateLatenessDeduction(
                $totalLatenessMinutes,
                $baseSalary,
                $workingDays,
                $dailyHours,
                $config->latenessRules,
            );
        }

        $netAmount = max(0, $grossAmount - $absenceDeduction - $latenessDeduction);

        // Upsert (une fiche par employe par periode)
        $payslip = Payslip::updateOrCreate(
            ['employee_id' => $employee->id, 'period' => $period],
            [
                'company_id'             => $employee->company_id,
                'site_id'                => $employee->site_id,
                'department_id'          => $employee->department_id,
                'period_start'           => $start->toDateString(),
                'period_end'             => $end->toDateString(),
                'payment_mode'           => $paymentMode,
                'base_salary'            => $baseSalary,
                'worked_days'            => $workedDays,
                'worked_hours'           => round($workedHours, 2),
                'absent_days'            => $absentDays,
                'total_lateness_minutes' => $totalLatenessMinutes,
                'overtime_hours'         => round($overtimeHours, 2),
                'overtime_amount'        => $overtimeAmount,
                'lateness_deduction'     => $latenessDeduction,
                'absence_deduction'      => $absenceDeduction,
                'lines'                  => [],
                'gross_amount'           => $grossAmount,
                'net_amount'             => $netAmount,
                'status'                 => 'draft',
                'generated_at'           => now(),
            ]
        );

        // Charger les relations pour la resource
        $payslip->load(['employee', 'company', 'site', 'department']);

        return $payslip;
    }

    /**
     * Calcule la deduction totale pour retards selon les regles configurees.
     */
    private function calculateLatenessDeduction(
        int $totalMinutes,
        int $baseSalary,
        int $workingDays,
        int $dailyHours,
        Collection $rules,
    ): int {
        $deduction    = 0;
        $hourlyRate   = $workingDays > 0 && $dailyHours > 0
            ? $baseSalary / ($workingDays * $dailyHours)
            : 0;

        foreach ($rules as $rule) {
            // Ignorer si en-dessous du seuil de tolerance
            if ($totalMinutes <= $rule->tolerance_minutes) {
                continue;
            }

            $effectiveMinutes = $totalMinutes - $rule->tolerance_minutes;

            if ($effectiveMinutes < $rule->minutes_threshold) {
                continue;
            }

            $multiplier = $rule->apply_per === 'tranche'
                ? floor($effectiveMinutes / $rule->minutes_threshold)
                : 1;

            if ($rule->penalty_type === 'fixed') {
                $deduction += (int) round($rule->penalty_value * $multiplier);
            } else {
                // percentage du salaire journalier
                $dailySalary = $workingDays > 0 ? $baseSalary / $workingDays : 0;
                $deduction  += (int) round($dailySalary * ($rule->penalty_value / 100) * $multiplier);
            }
        }

        return min($deduction, $baseSalary); // cap: jamais plus que le salaire
    }
}
