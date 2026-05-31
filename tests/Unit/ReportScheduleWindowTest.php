<?php

use App\Models\ReportSchedule;
use Illuminate\Support\Carbon;

/*
 * Test pur (sans base de données) de la fenêtre de période dérivée de la
 * fréquence. C'est ce qui borne le rapport envoyé par le job planifié : sans
 * dates, le rapport de présence (start_date/end_date requis) échouait, et les
 * autres couvraient tout l'historique. Migrations spécifiques PostgreSQL → on
 * instancie le modèle en mémoire, sans schéma sqlite.
 */

uses(Tests\TestCase::class);

function schedule(string $frequency): ReportSchedule
{
    return new ReportSchedule(['frequency' => $frequency]);
}

test('daily window targets the previous day', function () {
    $window = schedule(ReportSchedule::FREQ_DAILY)
        ->reportingWindow(Carbon::parse('2026-05-15 06:00'));

    expect($window)->toBe([
        'start_date' => '2026-05-14',
        'end_date' => '2026-05-14',
    ]);
});

test('weekly window targets the previous monday-to-sunday week', function () {
    // asOf = lundi 2026-05-11 → semaine précédente = lundi 04 au dimanche 10.
    $window = schedule(ReportSchedule::FREQ_WEEKLY)
        ->reportingWindow(Carbon::parse('2026-05-11 06:00'));

    expect($window)->toBe([
        'start_date' => '2026-05-04',
        'end_date' => '2026-05-10',
    ]);
});

test('monthly window targets the previous calendar month', function () {
    // asOf = 1er mai → mois précédent = 1er au 30 avril.
    $window = schedule(ReportSchedule::FREQ_MONTHLY)
        ->reportingWindow(Carbon::parse('2026-05-01 06:00'));

    expect($window)->toBe([
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-30',
    ]);
});
