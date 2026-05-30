<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stratégie de calcul des jours ouvrés attendus
    |--------------------------------------------------------------------------
    |
    | Dénominateur du taux de présence et base des absences dans les rapports.
    |
    |   'schedule' : basé sur les horaires de l'employé + jours fériés (précis).
    |   'config'   : basé sur working_days_per_month au prorata (comme la paie).
    |
    */

    'expected_days_strategy' => env('ATTENDANCE_EXPECTED_DAYS_STRATEGY', 'schedule'),

];
