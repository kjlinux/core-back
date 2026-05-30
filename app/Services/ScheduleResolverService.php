<?php

namespace App\Services;

use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ScheduleResolverService
{
    /**
     * Trouve l'horaire applicable pour un employé à un instant donné.
     *
     * Priorité : horaire individuel de l'employé (schedule_id), puis horaire
     * assigné à son département. Un horaire sans département (assigned_departments
     * vide) est considéré comme individuel : il ne s'applique JAMAIS automatiquement,
     * uniquement via schedule_id. work_days est respecté : un horaire dont le jour
     * courant est absent de work_days est ignoré. Quand plusieurs candidats
     * subsistent, on sélectionne celui dont la plage horaire contient l'heure
     * actuelle ; sinon on retourne le premier.
     *
     * @param  Collection<int, Schedule>|null  $preloadedSchedules  horaires de la société déjà
     *                                                              chargés (évite une requête par appel lors d'une résolution en boucle).
     */
    public function resolveForEmployee(string $companyId, ?string $departmentId, Carbon $at, ?string $scheduleId = null, ?Collection $preloadedSchedules = null): ?Schedule
    {
        $dayOfWeek = $at->isoWeekday(); // 1=Lun … 7=Dim (ISO 8601)

        // Charger une seule fois tous les horaires de la société puis filtrer en PHP
        // (les entreprises ont rarement plus d'une vingtaine d'horaires). Quand l'appelant
        // résout en boucle (ex. jours ouvrés d'une période), il précharge la collection.
        $companySchedules = $preloadedSchedules ?? Schedule::where('company_id', $companyId)->get();

        // Horaire individuel prioritaire : s'il appartient à la société, il
        // l'emporte sur le département, quel que soit work_days (cohérent avec
        // AttendanceEvaluationService et le front resolveEmployeeSchedule).
        if ($scheduleId !== null) {
            $direct = $companySchedules->firstWhere('id', $scheduleId);
            if ($direct !== null) {
                return $direct;
            }
        }

        $candidates = $companySchedules
            ->filter(function (Schedule $schedule) use ($departmentId, $dayOfWeek) {
                if (! $this->isActiveOnDay($schedule, $dayOfWeek)) {
                    return false;
                }

                $assigned = $schedule->assigned_departments ?? [];

                // Horaire sans département → individuel uniquement (via schedule_id, traité
                // plus haut). Jamais appliqué automatiquement, donc écarté du pool par département.
                if (empty($assigned)) {
                    return false;
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
        // Format attendu : HH:MM ou HH:MM:SS (24h). On ignore les secondes si presentes.
        if (! preg_match('/^([0-1]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $time, $m)) {
            throw new \InvalidArgumentException("Format horaire invalide: '{$time}'. Attendu HH:MM.");
        }

        return ((int) $m[1] * 60) + (int) $m[2];
    }
}
