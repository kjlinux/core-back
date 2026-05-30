<?php

use App\Enums\ExpectedDaysStrategy;
use App\Models\AbsenceRequest;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollConfig;
use App\Models\Schedule;
use App\Services\AttendanceStatsService;
use App\Services\ScheduleResolverService;
use Illuminate\Support\Carbon;

/*
 * Tests purs (sans base de données) : AttendanceStatsService ne requête jamais,
 * on lui passe des modèles en mémoire. Les migrations applicatives étant
 * spécifiques à PostgreSQL, on évite ainsi tout besoin de schéma sqlite.
 */

uses(Tests\TestCase::class);

const COMPANY = 'company-1';
const DEPT = 'dept-1';

function service(): AttendanceStatsService
{
    return new AttendanceStatsService(new ScheduleResolverService);
}

/** Horaire Lun→Ven assigné au département. */
function monFriSchedule(): Schedule
{
    return new Schedule([
        'company_id' => COMPANY,
        'name' => 'Standard',
        'type' => 'standard',
        'assigned_departments' => [DEPT],
        'work_days' => [1, 2, 3, 4, 5],
        'start_time' => '08:00',
        'end_time' => '17:00',
    ]);
}

function employee(array $extra = []): Employee
{
    $e = new Employee(array_merge([
        'company_id' => COMPANY,
        'department_id' => DEPT,
        'first_name' => 'Test',
        'last_name' => 'User',
        'is_active' => true,
    ], $extra));
    $e->id = $extra['id'] ?? 'emp-1';

    // Relations préchargées (comme le contrôleur via with()) → évite tout
    // lazy-load vers une base inexistante pendant buildReport().
    $e->setRelation('department', null);
    $e->setRelation('site', null);

    return $e;
}

function rec(string $status, int $overtime = 0): AttendanceRecord
{
    return new AttendanceRecord([
        'status' => $status,
        'date' => '2026-06-01',
        'overtime_minutes' => $overtime,
    ]);
}

function approvedLeave(string $start, string $end, string $status = 'approved'): AbsenceRequest
{
    return new AbsenceRequest([
        'date_start' => $start,
        'date_end' => $end,
        'status' => $status,
    ]);
}

/** Semaine Lun 2026-06-01 → Ven 2026-06-05 = 5 jours ouvrés. */
function weekStart(): Carbon
{
    return Carbon::parse('2026-06-01')->startOfDay();
}

function weekEnd(): Carbon
{
    return Carbon::parse('2026-06-05')->endOfDay();
}

function statsFor(Employee $emp, $records, $schedules, $holidays, $leaves, ?PayrollConfig $config = null, ExpectedDaysStrategy $strategy = ExpectedDaysStrategy::ScheduleBased): array
{
    return service()->statsForEmployee(
        $emp,
        weekStart(),
        weekEnd(),
        collect($records),
        collect($schedules),
        collect($holidays),
        collect($leaves),
        $strategy,
        $config,
    );
}

it('calcule un taux < 100 % quand des jours ouvrés sont manqués', function () {
    $stats = statsFor(employee(), [rec('present'), rec('present'), rec('present')], [monFriSchedule()], [], []);

    expect($stats['expectedWorkingDays'])->toBe(5);
    expect($stats['presentDays'])->toBe(3);
    expect($stats['rate'])->toBe(60.0);
    expect($stats['absentDays'])->toBe(2);
});

it('dérive les absences même sans aucun record « absent »', function () {
    $stats = statsFor(employee(), [rec('present')], [monFriSchedule()], [], []);

    expect($stats['absentDays'])->toBe(4);
    expect($stats['rate'])->toBe(20.0);
});

it('compte un employé sans aucun pointage comme 100 % absent', function () {
    $stats = statsFor(employee(), [], [monFriSchedule()], [], []);

    expect($stats['expectedWorkingDays'])->toBe(5);
    expect($stats['presentDays'])->toBe(0);
    expect($stats['absentDays'])->toBe(5);
    expect($stats['rate'])->toBe(0.0);
});

it('exclut les congés approuvés des absences', function () {
    $stats = statsFor(employee(), [rec('present')], [monFriSchedule()], [], [approvedLeave('2026-06-04', '2026-06-05')]);

    expect($stats['leaveDays'])->toBe(2);
    expect($stats['absentDays'])->toBe(2); // 5 - 1 présent - 2 congés
});

it('ne déduit pas les congés en attente ou rejetés', function () {
    $stats = statsFor(employee(), [rec('present')], [monFriSchedule()], [], [approvedLeave('2026-06-04', '2026-06-05', 'pending')]);

    expect($stats['leaveDays'])->toBe(0);
    expect($stats['absentDays'])->toBe(4);
});

it('exclut les jours fériés du dénominateur', function () {
    // 2026-06-03 (mercredi) férié → 4 jours ouvrés attendus
    $stats = statsFor(employee(), [rec('present'), rec('present')], [monFriSchedule()], [new Holiday(['date' => '2026-06-03'])], []);

    expect($stats['expectedWorkingDays'])->toBe(4);
    expect($stats['absentDays'])->toBe(2);
    expect($stats['rate'])->toBe(50.0);
});

it('reconnaît un jour férié récurrent d’une autre année', function () {
    // Férié récurrent défini en 2020, le 03/06 (mercredi en 2026)
    $stats = statsFor(employee(), [], [monFriSchedule()], [new Holiday(['date' => '2020-06-03', 'is_recurring' => true])], []);

    expect($stats['expectedWorkingDays'])->toBe(4);
});

it('proratise sur la date d’embauche (hire_date)', function () {
    // Embauché le mercredi 2026-06-03 → seuls 03,04,05 sont attendus
    $emp = employee(['hire_date' => '2026-06-03']);
    $stats = statsFor($emp, [rec('present'), rec('present'), rec('present')], [monFriSchedule()], [], []);

    expect($stats['expectedWorkingDays'])->toBe(3);
    expect($stats['absentDays'])->toBe(0);
    expect($stats['rate'])->toBe(100.0);
});

it('compte les jours ouvrés à cheval sur deux mois', function () {
    // Lun 2026-05-25 → Ven 2026-06-05 : 5 + 5 jours ouvrés (week-end 30-31/05 exclu)
    $stats = service()->statsForEmployee(
        employee(),
        Carbon::parse('2026-05-25')->startOfDay(),
        Carbon::parse('2026-06-05')->endOfDay(),
        collect([]),
        collect([monFriSchedule()]),
        collect([]),
        collect([]),
        ExpectedDaysStrategy::ScheduleBased,
    );

    expect($stats['expectedWorkingDays'])->toBe(10);
});

it('compte retards et journées partielles comme présence', function () {
    $stats = statsFor(employee(), [rec('present'), rec('late'), rec('partial')], [monFriSchedule()], [], []);

    expect($stats['lateDays'])->toBe(1);
    expect($stats['partialDays'])->toBe(1);
    expect($stats['attendedDays'])->toBe(3);
    expect($stats['rate'])->toBe(60.0);
    expect($stats['absentDays'])->toBe(2);
});

it('plafonne le taux à 100 % et n’autorise pas d’absences négatives', function () {
    // 6 pointages présents pour 5 jours ouvrés (ex. ping week-end)
    $records = collect(array_fill(0, 6, null))->map(fn () => rec('present'))->all();
    $stats = statsFor(employee(), $records, [monFriSchedule()], [], []);

    expect($stats['rate'])->toBe(100.0);
    expect($stats['absentDays'])->toBe(0);
});

it('additionne les heures supplémentaires en heures', function () {
    $stats = statsFor(employee(), [rec('present', 90), rec('present', 30)], [monFriSchedule()], [], []);

    expect($stats['overtimeHours'])->toBe(2.0);
});

it('bascule sur la config quand aucun horaire n’existe', function () {
    $config = new PayrollConfig(['working_days_per_month' => 26]);
    $stats = statsFor(employee(), [rec('present'), rec('present')], [], [], [], $config);

    expect($stats['expectedWorkingDays'])->toBe(26);
    expect($stats['absentDays'])->toBe(24);
});

it('utilise la stratégie config (working_days_per_month) quand demandée', function () {
    $config = new PayrollConfig(['working_days_per_month' => 22]);
    $stats = statsFor(employee(), [rec('present')], [monFriSchedule()], [], [], $config, ExpectedDaysStrategy::ConfigBased);

    expect($stats['expectedWorkingDays'])->toBe(22);
    expect($stats['absentDays'])->toBe(21);
});

it('assemble le rapport et inclut les employés sans pointage', function () {
    $present = employee(['id' => 'emp-present']);
    $absent = employee(['id' => 'emp-absent']);

    $recordsByEmployee = collect([
        'emp-present' => collect([rec('present'), rec('present'), rec('present'), rec('present'), rec('present')]),
    ]);

    $report = service()->buildReport(
        collect([$present, $absent]),
        weekStart(),
        weekEnd(),
        $recordsByEmployee,
        collect([monFriSchedule()]),
        collect([]),
        collect([]),
        ExpectedDaysStrategy::ScheduleBased,
    );

    expect($report['totalEmployees'])->toBe(2);
    expect($report['rows'])->toHaveCount(2);
    expect($report['totalPresent'])->toBe(5);
    expect($report['totalAbsent'])->toBe(5); // l'employé sans pointage = 5 absences
    expect($report['globalRate'])->toBe(50.0); // 5 présents / 10 attendus
});

it('filtre les lignes sur type=absence', function () {
    $present = employee(['id' => 'emp-present']);
    $absent = employee(['id' => 'emp-absent']);

    $recordsByEmployee = collect([
        'emp-present' => collect([rec('present'), rec('present'), rec('present'), rec('present'), rec('present')]),
    ]);

    $report = service()->buildReport(
        collect([$present, $absent]),
        weekStart(),
        weekEnd(),
        $recordsByEmployee,
        collect([monFriSchedule()]),
        collect([]),
        collect([]),
        ExpectedDaysStrategy::ScheduleBased,
        null,
        'absence',
    );

    expect($report['rows'])->toHaveCount(1);
    expect($report['rows'][0]['employeeId'])->toBe('emp-absent');
});

it('expose les clés du contrat JSON attendues par le front', function () {
    $report = service()->buildReport(
        collect([employee()]),
        weekStart(),
        weekEnd(),
        collect([]),
        collect([monFriSchedule()]),
        collect([]),
        collect([]),
        ExpectedDaysStrategy::ScheduleBased,
    );

    expect($report)->toHaveKeys(['totalEmployees', 'totalPresent', 'totalAbsent', 'totalLate', 'totalLeave', 'globalRate', 'rows']);
    expect($report['rows'][0])->toHaveKeys(['employeeId', 'employee', 'department', 'site', 'present', 'absent', 'late', 'leave', 'expected', 'overtime', 'rate']);
    expect($report['rows'][0])->not->toHaveKey('_attended');
});
