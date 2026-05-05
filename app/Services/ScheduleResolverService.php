<?php

namespace App\Services;

use App\Models\Schedule;
use Carbon\Carbon;

class ScheduleResolverService
{
    /**
     * Trouve l'horaire applicable pour un employé à un instant donné.
     *
     * Quand plusieurs horaires sont assignés au même département, on sélectionne
     * celui dont la plage horaire correspond à l'heure actuelle. Si aucun ne
     * correspond à l'heure exacte, on retourne le premier trouvé comme fallback.
     */
    public function resolveForEmployee(string $companyId, string $departmentId, Carbon $at): ?Schedule
    {
        $schedules = Schedule::where('company_id', $companyId)
            ->whereJsonContains('assigned_departments', $departmentId)
            ->get();

        if ($schedules->isEmpty()) {
            return null;
        }

        if ($schedules->count() === 1) {
            return $schedules->first();
        }

        foreach ($schedules as $schedule) {
            if ($this->isWithinScheduleWindow($schedule, $at)) {
                return $schedule;
            }
        }

        // Fallback : retourner le premier horaire
        return $schedules->first();
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
            return (int) $entryTime->diffInMinutes($startTime);
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
