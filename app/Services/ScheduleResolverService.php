<?php

namespace App\Services;

use App\Models\Schedule;
use Carbon\Carbon;

class ScheduleResolverService
{
    /**
     * Trouve l'horaire applicable pour un employé à un instant donné.
     *
     * Priorité : horaire assigné au département de l'employé, puis horaire global
     * de l'entreprise (assigned_departments vide). work_days est respecté : un
     * horaire dont le jour courant est absent de work_days est ignoré.
     * Quand plusieurs candidats subsistent, on sélectionne celui dont la plage
     * horaire contient l'heure actuelle ; sinon on retourne le premier.
     */
    public function resolveForEmployee(string $companyId, ?string $departmentId, Carbon $at): ?Schedule
    {
        $dayOfWeek = $at->isoWeekday(); // 1=Lun … 7=Dim (ISO 8601)

        // Charger une seule fois tous les horaires de la société puis filtrer en PHP
        // (les entreprises ont rarement plus d'une vingtaine d'horaires).
        $candidates = Schedule::where('company_id', $companyId)
            ->get()
            ->filter(function (Schedule $schedule) use ($departmentId, $dayOfWeek) {
                if (! $this->isActiveOnDay($schedule, $dayOfWeek)) {
                    return false;
                }

                $assigned = $schedule->assigned_departments ?? [];

                // Horaire global (aucun département assigné) → applicable à tous
                if (empty($assigned)) {
                    return true;
                }

                // Horaire de département → ne s'applique que si le département correspond
                return $departmentId !== null && in_array($departmentId, $assigned, true);
            });

        if ($candidates->isEmpty()) {
            return null;
        }

        // Préférer l'horaire spécifique au département sur un horaire global
        if ($departmentId !== null) {
            $deptSpecific = $candidates->filter(
                fn (Schedule $s) => ! empty($s->assigned_departments) && in_array($departmentId, $s->assigned_departments, true)
            );

            if ($deptSpecific->isNotEmpty()) {
                $candidates = $deptSpecific;
            }
        }

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        foreach ($candidates as $schedule) {
            if ($this->isWithinScheduleWindow($schedule, $at)) {
                return $schedule;
            }
        }

        return $candidates->first();
    }

    /**
     * Vérifie si un horaire est actif pour un jour ISO donné (1=Lun … 7=Dim).
     * Si work_days est null ou vide, l'horaire s'applique tous les jours.
     */
    public function isActiveOnDay(Schedule $schedule, int $isoDay): bool
    {
        $workDays = $schedule->work_days;

        if (empty($workDays)) {
            return true;
        }

        return in_array($isoDay, $workDays, true);
    }

    /**
     * Détermine si un instant donné se situe dans la fenêtre d'un horaire.
     * Gère les horaires de nuit (ex: 18:30 → 08:00).
     */
    public function isWithinScheduleWindow(Schedule $schedule, Carbon $at): bool
    {
        $startMinutes = $this->timeToMinutes($schedule->start_time);
        $endMinutes = $this->timeToMinutes($schedule->end_time);
        $nowMinutes = ($at->hour * 60) + $at->minute;

        if ($startMinutes < $endMinutes) {
            // Horaire normal (ex: 08:00 → 17:00)
            return $nowMinutes >= $startMinutes && $nowMinutes <= $endMinutes;
        }

        // Horaire de nuit : la plage chevauche minuit (ex: 18:30 → 08:00)
        return $nowMinutes >= $startMinutes || $nowMinutes <= $endMinutes;
    }

    /**
     * Calcule les minutes de retard pour une entrée.
     * Retourne 0 si l'employé est à l'heure (ou dans la tolérance).
     */
    public function calculateLateMinutes(Schedule $schedule, Carbon $entryTime): int
    {
        $startTime = $this->resolveStartCarbon($schedule, $entryTime);
        $tolerance = $schedule->late_tolerance ?? 0;

        if ($entryTime->gt($startTime->copy()->addMinutes($tolerance))) {
            return (int) $startTime->diffInMinutes($entryTime);
        }

        return 0;
    }

    /**
     * Calcule les minutes de départ anticipé pour une sortie.
     * Retourne 0 si l'employé part après l'heure de fin.
     */
    public function calculateEarlyDepartureMinutes(Schedule $schedule, Carbon $exitTime): int
    {
        $endTime = $this->resolveEndCarbon($schedule, $exitTime);

        if ($exitTime->lt($endTime)) {
            return (int) $exitTime->diffInMinutes($endTime);
        }

        return 0;
    }

    /**
     * Construit le Carbon de l'heure de début, ajusté pour les horaires de nuit.
     */
    private function resolveStartCarbon(Schedule $schedule, Carbon $referenceTime): Carbon
    {
        $startMinutes = $this->timeToMinutes($schedule->start_time);
        $endMinutes = $this->timeToMinutes($schedule->end_time);

        $base = $referenceTime->copy()->setTimeFromTimeString($schedule->start_time)->startOfMinute();

        // Horaire de nuit et on est après minuit (après la fin) → le début était hier
        if ($startMinutes > $endMinutes) {
            $nowMinutes = ($referenceTime->hour * 60) + $referenceTime->minute;
            if ($nowMinutes <= $endMinutes) {
                $base->subDay();
            }
        }

        return $base;
    }

    /**
     * Construit le Carbon de l'heure de fin, ajusté pour les horaires de nuit.
     */
    private function resolveEndCarbon(Schedule $schedule, Carbon $referenceTime): Carbon
    {
        $startMinutes = $this->timeToMinutes($schedule->start_time);
        $endMinutes = $this->timeToMinutes($schedule->end_time);

        $base = $referenceTime->copy()->setTimeFromTimeString($schedule->end_time)->startOfMinute();

        // Horaire de nuit : la fin est le lendemain si on est encore dans la phase "avant minuit"
        if ($startMinutes > $endMinutes) {
            $nowMinutes = ($referenceTime->hour * 60) + $referenceTime->minute;
            if ($nowMinutes >= $startMinutes) {
                $base->addDay();
            }
        }

        return $base;
    }

    private function timeToMinutes(string $time): int
    {
        [$h, $m] = explode(':', $time);

        return ((int) $h * 60) + (int) $m;
    }
}
