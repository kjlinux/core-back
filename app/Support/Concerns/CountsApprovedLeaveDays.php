<?php

namespace App\Support\Concerns;

use App\Models\AbsenceRequest;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Comptage des jours de congé approuvés couverts par une période.
 *
 * Source de vérité unique partagée par PayrollService (paie) et
 * AttendanceStatsService (rapports de présence) : un congé n'est jamais
 * compté comme une absence pénalisante.
 */
trait CountsApprovedLeaveDays
{
    /**
     * Compte le nombre de jours distincts couverts par des demandes de congé
     * approuvées pour cet employé sur la période [$start, $end].
     *
     * Variante requêtante (charge les AbsenceRequest depuis la base). Utilisée
     * quand les congés ne sont pas déjà préchargés.
     */
    protected function countApprovedLeaveDays(string $employeeId, Carbon $start, Carbon $end): int
    {
        $leaves = AbsenceRequest::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where('date_start', '<=', $end->toDateString())
            ->where('date_end', '>=', $start->toDateString())
            ->get();

        return $this->countLeaveDaysFromCollection($leaves, $start, $end);
    }

    /**
     * Variante pure (sans requête) : compte les jours distincts couverts par
     * une collection de congés déjà chargés. Seuls les congés `approved` sont
     * pris en compte ; chaque jour n'est compté qu'une fois même si plusieurs
     * demandes se chevauchent, et le décompte est borné à [$start, $end].
     *
     * @param  Collection<int, AbsenceRequest>  $approvedLeaves
     */
    protected function countLeaveDaysFromCollection(Collection $approvedLeaves, Carbon $start, Carbon $end): int
    {
        $days = [];

        foreach ($approvedLeaves as $leave) {
            if (($leave->status ?? null) !== 'approved') {
                continue;
            }

            $leaveStart = Carbon::parse($leave->date_start);
            $leaveEnd = Carbon::parse($leave->date_end);

            $from = $leaveStart->greaterThan($start) ? $leaveStart : $start->copy();
            $to = $leaveEnd->lessThan($end) ? $leaveEnd : $end->copy();

            $cursor = $from->copy();
            while ($cursor->lte($to)) {
                $days[$cursor->toDateString()] = true;
                $cursor->addDay();
            }
        }

        return count($days);
    }
}
