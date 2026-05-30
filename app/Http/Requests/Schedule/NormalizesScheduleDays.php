<?php

namespace App\Http\Requests\Schedule;

trait NormalizesScheduleDays
{
    /**
     * Le blob JSON `days` utilise des cles imbriquees en camelCase
     * (startTime, endTime, expectedPunches, lateTolerance). C'est le contrat
     * partage avec le frontend (types TS), les services backend
     * (AttendanceEvaluationService, ScheduleResolverService) et les tests.
     *
     * Or l'intercepteur axios du frontend snake_case recursivement TOUTES les
     * cles du corps de requete, y compris celles imbriquees dans `days`. On les
     * renormalise donc ici en camelCase avant validation/persistance, afin que
     * la valeur stockee reste canonique et lisible par le reste du backend.
     *
     * Idempotent : une charge deja en camelCase passe sans modification.
     */
    protected function normalizeScheduleDays(): void
    {
        $days = $this->input('days');
        if (! is_array($days)) {
            return;
        }

        $map = [
            'start_time' => 'startTime',
            'end_time' => 'endTime',
            'expected_punches' => 'expectedPunches',
            'late_tolerance' => 'lateTolerance',
        ];

        foreach ($days as $dayIndex => $day) {
            if (! is_array($day) || ! isset($day['segments']) || ! is_array($day['segments'])) {
                continue;
            }

            foreach ($day['segments'] as $segIndex => $segment) {
                if (! is_array($segment)) {
                    continue;
                }

                foreach ($map as $snake => $camel) {
                    if (! array_key_exists($snake, $segment)) {
                        continue;
                    }
                    // Ne pas ecraser une cle camelCase deja presente.
                    if (! array_key_exists($camel, $segment)) {
                        $segment[$camel] = $segment[$snake];
                    }
                    unset($segment[$snake]);
                }

                $days[$dayIndex]['segments'][$segIndex] = $segment;
            }
        }

        $this->merge(['days' => $days]);
    }
}
