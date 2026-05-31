<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\PayrollConfig;
use App\Models\Payslip;
use App\Support\Concerns\CountsApprovedLeaveDays;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PayrollService
{
    use CountsApprovedLeaveDays;

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
        $start = Carbon::parse($periodStart);
        $end = Carbon::parse($periodEnd);
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
        // Les déductions d'absence/retard ne s'appliquent qu'aux modes à salaire fixe.
        // Pour les modes horaire/journalier/hebdomadaire, le brut reflète déjà uniquement
        // le temps réellement travaillé — re-déduire les absences serait une double pénalité,
        // et base_salary y représente un taux (par heure/jour) et non un montant mensuel.
        $isFixedSalaryMode = in_array($paymentMode, ['monthly', 'forfait'], true);
        $baseSalary = $employee->base_salary ?? 0;
        $workingDays = $config?->working_days_per_month ?? 26;
        $workingDaysPerWeek = $config?->working_days_per_week ?? 5;
        $dailyHours = $config?->standard_daily_hours ?? 8;

        // Charger les presences de la periode
        $records = AttendanceRecord::where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();

        // Jours travaillés : on ne compte QUE les pointages de présence réelle.
        // Les enregistrements 'absent' / 'on_leave' ne sont PAS du temps travaillé
        // et ne doivent pas gonfler workedDays (sinon les absences sont sous-estimées).
        // Une journée partielle (un seul pointage) compte pour une demi-journée.
        $presenceStatuses = ['present', 'late', 'left_early', 'complete'];
        $workedDays = $records->whereIn('status', $presenceStatuses)->count()
            + $records->where('status', 'partial')->count() * 0.5;
        $workedHours = $records->sum(function ($r) {
            if (! $r->entry_time || ! $r->exit_time) {
                return 0;
            }

            // max(0, …) : garde-fou si entry_time > exit_time (saisie ou horodatage
            // inversé) pour ne jamais soustraire des heures au temps travaillé.
            return max(0, Carbon::parse($r->entry_time)->diffInMinutes(Carbon::parse($r->exit_time)) / 60);
        });

        // Jours de conge approuves dans la periode : ne doivent pas etre comptes
        // comme absences ni generer de penalite retard
        $leaveDays = $this->countApprovedLeaveDays($employee->id, $start, $end);

        // Jours ouvrables effectivement attendus sur la période. Base COMMUNE au
        // calcul des absences, au taux journalier des déductions et au seuil
        // d'heures supplémentaires — pour éviter toute incohérence entre eux.
        // Pro-rata si l'employé a été embauché en cours de période. On se base sur la
        // date d'embauche reelle (hire_date), pas sur created_at (date de creation de la
        // fiche en base, souvent posterieure a l'embauche : imports, reprise de donnees).
        // Meme ancrage que AttendanceStatsService::hireAnchor().
        $hireRaw = $employee->hire_date ?? $employee->created_at;
        $hiredAt = $hireRaw ? Carbon::parse($hireRaw)->startOfDay() : null;
        $effectiveWorkingDays = $workingDays;
        if ($hiredAt && $hiredAt->gt($end)) {
            // Embauché après la fin de période — aucun jour ouvrable attendu
            $effectiveWorkingDays = 0;
        } elseif ($hiredAt && $hiredAt->gt($start)) {
            // Proportionnel : jours_employé_dans_période / jours_totaux_période × workingDays
            $totalPeriodDays = $start->diffInDays($end) + 1;
            $employeeDaysInPeriod = $hiredAt->diffInDays($end) + 1;
            $effectiveWorkingDays = (int) round($workingDays * $employeeDaysInPeriod / $totalPeriodDays);
        }

        $absentDays = max(0, $effectiveWorkingDays - $workedDays);

        // Soustraire les jours en conge approuve : non penalises comme absences
        $absentDays = max(0, $absentDays - $leaveDays);

        // Calcul des retards en minutes (exclut les pointages couverts par un conge)
        $totalLatenessMinutes = $records->sum(function ($r) {
            if (($r->status ?? null) === 'on_leave' || ($r->is_on_leave ?? false)) {
                return 0;
            }

            return max(0, (int) ($r->late_minutes ?? 0));
        });

        // Calcul du salaire brut selon le mode
        $grossAmount = match ($paymentMode) {
            'monthly' => $baseSalary,
            'daily' => (int) round($baseSalary * $workedDays),
            'hourly' => (int) round($baseSalary * $workedHours),
            // Hebdomadaire : base_salary = taux HEBDOMADAIRE ; nombre de semaines
            // travaillées = jours travaillés / jours travaillés par semaine (config).
            'weekly' => (int) round($baseSalary * ($workedDays / max(1, $workingDaysPerWeek))),
            'forfait' => $baseSalary,
            default => $baseSalary,
        };

        // Heures supplementaires (uniquement pour mode horaire, overtimeRate doit être > 1.0)
        $overtimeHours = 0.0;
        $overtimeAmount = 0;
        $overtimeRate = $config?->overtime_rate ?? 1.0;
        if ($config?->overtime_enabled && $paymentMode === 'hourly' && $overtimeRate > 1.0) {
            // Seuil pro-raté sur la période réelle (cohérent avec les absences),
            // sinon une période partielle comparée à 26 jours fausse les heures sup.
            $standardHours = $effectiveWorkingDays * $dailyHours;
            $overtimeHours = max(0, $workedHours - $standardHours);
            $hourlyRate = $baseSalary;
            $overtimeAmount = (int) round($overtimeHours * $hourlyRate * ($overtimeRate - 1));
            $grossAmount += $overtimeAmount;
        }

        // Deduction absences (prorata jours ouvrables) — uniquement en mode salaire fixe
        $absenceDeduction = 0;
        if ($isFixedSalaryMode && $baseSalary > 0 && $effectiveWorkingDays > 0) {
            // Taux journalier basé sur les jours attendus de la période (mêmes que
            // pour le décompte des absences) afin que déduction et absences soient
            // calculées sur la même base.
            $dailySalary = $baseSalary / $effectiveWorkingDays;
            $absenceDeduction = (int) round($dailySalary * $absentDays);
        }

        // Deduction retards — uniquement en mode salaire fixe (sinon déjà reflété dans le brut)
        $latenessDeduction = 0;
        if ($isFixedSalaryMode && $config?->lateness_deduction_enabled && $totalLatenessMinutes > 0 && $config->latenessRules->isNotEmpty()) {
            $latenessDeduction = $this->calculateLatenessDeduction(
                $totalLatenessMinutes,
                $baseSalary,
                $effectiveWorkingDays,
                $dailyHours,
                $config->latenessRules,
            );
        }

        // Garde-fou : les déductions ne peuvent jamais dépasser le brut.
        // On plafonne d'abord les absences, puis les retards sur le reliquat,
        // pour que la colonne « Déductions » et le net restent cohérents (net >= 0).
        $absenceDeduction = min($absenceDeduction, $grossAmount);
        $latenessDeduction = min($latenessDeduction, max(0, $grossAmount - $absenceDeduction));

        $netAmount = max(0, $grossAmount - $absenceDeduction - $latenessDeduction);

        // Upsert (une fiche par employe par periode)
        $payslip = Payslip::updateOrCreate(
            ['employee_id' => $employee->id, 'period' => $period],
            [
                'company_id' => $employee->company_id,
                'site_id' => $employee->site_id,
                'department_id' => $employee->department_id,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'payment_mode' => $paymentMode,
                'base_salary' => $baseSalary,
                'worked_days' => (int) round($workedDays),
                'worked_hours' => round($workedHours, 2),
                'absent_days' => (int) round($absentDays),
                'total_lateness_minutes' => $totalLatenessMinutes,
                'overtime_hours' => round($overtimeHours, 2),
                'overtime_amount' => $overtimeAmount,
                'lateness_deduction' => $latenessDeduction,
                'absence_deduction' => $absenceDeduction,
                'lines' => $this->buildPayslipLines(
                    $grossAmount, $paymentMode, $overtimeAmount, $absenceDeduction, $latenessDeduction
                ),
                'gross_amount' => $grossAmount,
                'net_amount' => $netAmount,
                'status' => 'draft',
                'generated_at' => now(),
            ]
        );

        // Charger les relations pour la resource
        $payslip->load(['employee', 'company', 'site', 'department']);

        return $payslip;
    }

    private function buildPayslipLines(
        int $grossAmount,
        string $paymentMode,
        int $overtimeAmount,
        int $absenceDeduction,
        int $latenessDeduction,
    ): array {
        // Pour les modes horaires/journaliers, le brut de base est différent du taux unitaire
        $baseLabel = match ($paymentMode) {
            'hourly' => 'Salaire (taux horaire × heures)',
            'daily' => 'Salaire (taux journalier × jours)',
            'weekly' => 'Salaire (taux hebdomadaire × semaines)',
            default => 'Salaire de base',
        };
        // Le brut avant heures sup est : grossAmount - overtimeAmount
        $baseEarning = $grossAmount - $overtimeAmount;

        $lines = [
            ['label' => $baseLabel, 'type' => 'earning', 'amount' => $baseEarning],
        ];

        if ($overtimeAmount > 0) {
            $lines[] = ['label' => 'Heures supplémentaires', 'type' => 'earning', 'amount' => $overtimeAmount];
        }
        if ($absenceDeduction > 0) {
            $lines[] = ['label' => 'Déduction absences', 'type' => 'deduction', 'amount' => $absenceDeduction];
        }
        if ($latenessDeduction > 0) {
            $lines[] = ['label' => 'Déduction retards', 'type' => 'deduction', 'amount' => $latenessDeduction];
        }

        return $lines;
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
        $deduction = 0;

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
                $deduction += (int) round($dailySalary * ($rule->penalty_value / 100) * $multiplier);
            }
        }

        return min($deduction, $baseSalary); // cap: jamais plus que le salaire
    }
}
