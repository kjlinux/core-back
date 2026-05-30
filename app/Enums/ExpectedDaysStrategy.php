<?php

namespace App\Enums;

/**
 * Stratégie de calcul des « jours ouvrés attendus » servant de dénominateur
 * au taux de présence et de base au calcul des absences.
 */
enum ExpectedDaysStrategy: string
{
    /**
     * Basé sur les horaires : compte, jour par jour, ceux qui tombent sur un
     * jour travaillé de l'horaire de l'employé, hors jours fériés. Précis par
     * employé. Stratégie par défaut.
     */
    case ScheduleBased = 'schedule';

    /**
     * Basé sur la configuration de paie (working_days_per_month, prorata
     * embauche). Plus simple, utilisé en repli quand aucun horaire n'existe.
     */
    case ConfigBased = 'config';

    public static function fromConfig(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::ScheduleBased;
    }
}
