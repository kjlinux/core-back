<?php

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Schedule;
use App\Services\AttendanceEvaluationService;
use App\Services\ScheduleResolverService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * Le jeu de migrations applicatif est specifique a PostgreSQL (DROP CONSTRAINT ...),
 * incompatible avec le sqlite :memory: utilise par les tests. On batit donc le schema
 * minimal necessaire a ces deux services, pour tester la resolution d'horaire en isolation.
 */
beforeEach(function () {
    Schema::create('schedules', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('company_id');
        $t->string('name');
        $t->string('type')->default('standard');
        $t->integer('default_late_tolerance')->default(0);
        $t->text('days')->nullable();
        $t->string('start_time')->nullable();
        $t->string('end_time')->nullable();
        $t->string('break_start')->nullable();
        $t->string('break_end')->nullable();
        $t->text('work_days')->nullable();
        $t->integer('late_tolerance')->nullable();
        $t->text('assigned_departments')->nullable();
        $t->timestamps();
    });

    Schema::create('employees', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('company_id')->nullable();
        $t->uuid('department_id')->nullable();
        $t->uuid('schedule_id')->nullable();
        $t->string('first_name')->nullable();
        $t->string('last_name')->nullable();
        $t->string('avatar')->nullable();
        $t->timestamps();
    });

    Schema::create('attendance_records', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('employee_id');
        $t->date('date');
        $t->dateTime('entry_time')->nullable();
        $t->dateTime('exit_time')->nullable();
        $t->string('status')->nullable();
        $t->string('expected_shift')->nullable();
        $t->text('segments')->nullable();
        $t->boolean('is_on_leave')->default(false);
        $t->timestamps();
    });

    Schema::create('absence_requests', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('employee_id');
        $t->string('status')->nullable();
        $t->date('date_start')->nullable();
        $t->date('date_end')->nullable();
        $t->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('absence_requests');
    Schema::dropIfExists('attendance_records');
    Schema::dropIfExists('employees');
    Schema::dropIfExists('schedules');
});

// --- Helpers ----------------------------------------------------------------

function segmentFor(string $kind, string $start, string $end, array $punchTimes): array
{
    return [
        'kind' => $kind,
        'startTime' => $start,
        'endTime' => $end,
        'expectedPunches' => array_map(fn ($time) => ['time' => $time, 'label' => ''], $punchTimes),
        'lateTolerance' => 0,
    ];
}

function weekFor(int $weekday, array $segments): array
{
    $days = [];
    for ($w = 1; $w <= 7; $w++) {
        $days[] = [
            'weekday' => $w,
            'worked' => $w === $weekday,
            'segments' => $w === $weekday ? $segments : [],
        ];
    }

    return $days;
}

function scheduleFor(string $companyId, string $name, array $departments, array $days): Schedule
{
    return Schedule::create([
        'company_id' => $companyId,
        'name' => $name,
        'type' => 'standard',
        'default_late_tolerance' => 0,
        'days' => $days,
        'assigned_departments' => $departments,
    ]);
}

// --- ScheduleResolverService (pointage live) --------------------------------

it('prend l’horaire individuel (schedule_id) en priorité sur celui du département', function () {
    $company = (string) Str::uuid();
    $dept = (string) Str::uuid();
    $at = Carbon::parse('2026-06-01 08:30');
    $weekday = $at->isoWeekday();

    scheduleFor($company, 'Jour Dept', [$dept], weekFor($weekday, [segmentFor('morning', '08:00', '17:00', ['08:00'])]));
    $individual = scheduleFor($company, 'Perso', [], weekFor($weekday, [segmentFor('evening', '13:00', '21:00', ['13:00'])]));

    $resolved = app(ScheduleResolverService::class)
        ->resolveForEmployee($company, $dept, $at, $individual->id);

    // schedule_id l'emporte meme si 08:30 est hors de sa plage 13:00-21:00
    expect($resolved?->id)->toBe($individual->id);
});

it('n’applique jamais globalement un horaire sans département (vide = individuel)', function () {
    $company = (string) Str::uuid();
    $dept = (string) Str::uuid();
    $at = Carbon::parse('2026-06-01 08:30');
    $weekday = $at->isoWeekday();

    // Horaire sans departement, non rattache a l'employe : ne doit PAS servir de fallback
    scheduleFor($company, 'Orphelin', [], weekFor($weekday, [segmentFor('morning', '08:00', '17:00', ['08:00'])]));

    $resolved = app(ScheduleResolverService::class)
        ->resolveForEmployee($company, $dept, $at, null);

    expect($resolved)->toBeNull();
});

it('départage deux horaires d’un même département selon l’heure de pointage', function () {
    $company = (string) Str::uuid();
    $dept = (string) Str::uuid();
    $monday = Carbon::parse('2026-06-01');
    $weekday = $monday->isoWeekday();

    $jour = scheduleFor($company, 'Jour', [$dept], weekFor($weekday, [segmentFor('morning', '08:00', '17:00', ['08:00'])]));
    $nuit = scheduleFor($company, 'Nuit', [$dept], weekFor($weekday, [segmentFor('night', '22:00', '06:00', ['22:00'])]));

    $resolver = app(ScheduleResolverService::class);

    $morning = $resolver->resolveForEmployee($company, $dept, $monday->copy()->setTime(8, 30), null);
    $evening = $resolver->resolveForEmployee($company, $dept, $monday->copy()->setTime(22, 30), null);

    expect($morning?->id)->toBe($jour->id);
    expect($evening?->id)->toBe($nuit->id);
});

// --- AttendanceEvaluationService (rapports) ----------------------------------

function recordFor(string $employeeId, string $date, string $entryTime): AttendanceRecord
{
    return AttendanceRecord::create([
        'employee_id' => $employeeId,
        'date' => $date,
        'entry_time' => "{$date} {$entryTime}:00",
        'status' => 'present',
    ]);
}

it('rattache le bon horaire (Jour vs Nuit) selon l’heure de pointage dans les rapports', function () {
    $company = (string) Str::uuid();
    $dept = (string) Str::uuid();
    $date = '2026-06-01';
    $weekday = Carbon::parse($date)->isoWeekday();

    scheduleFor($company, 'Jour', [$dept], weekFor($weekday, [segmentFor('morning', '08:00', '17:00', ['08:00'])]));
    scheduleFor($company, 'Nuit', [$dept], weekFor($weekday, [segmentFor('night', '22:00', '06:00', ['22:00'])]));

    $employee = Employee::create(['company_id' => $company, 'department_id' => $dept]);

    $service = app(AttendanceEvaluationService::class);

    $night = $service->enrichRecords(collect([recordFor($employee->id, $date, '22:05')]))->first();
    $day = $service->enrichRecords(collect([recordFor($employee->id, $date, '08:05')]))->first();

    expect($night->segments[0]['startTime'])->toBe('22:00');
    expect($day->segments[0]['startTime'])->toBe('08:00');
});

it('utilise l’horaire individuel de l’employé dans les rapports', function () {
    $company = (string) Str::uuid();
    $dept = (string) Str::uuid();
    $date = '2026-06-01';
    $weekday = Carbon::parse($date)->isoWeekday();

    // Horaire du departement (ne doit pas etre choisi)
    scheduleFor($company, 'Jour Dept', [$dept], weekFor($weekday, [segmentFor('morning', '08:00', '17:00', ['08:00'])]));
    // Horaire individuel sans departement
    $individual = scheduleFor($company, 'Perso', [], weekFor($weekday, [segmentFor('morning', '09:00', '12:00', ['09:00'])]));

    $employee = Employee::create([
        'company_id' => $company,
        'department_id' => $dept,
        'schedule_id' => $individual->id,
    ]);

    $enriched = app(AttendanceEvaluationService::class)
        ->enrichRecords(collect([recordFor($employee->id, $date, '09:03')]))
        ->first();

    expect($enriched->segments[0]['startTime'])->toBe('09:00');
});

it('ne rattache aucun horaire si un horaire sans département traîne sans être assigné', function () {
    $company = (string) Str::uuid();
    $dept = (string) Str::uuid();
    $date = '2026-06-01';
    $weekday = Carbon::parse($date)->isoWeekday();

    // Horaire sans departement et non rattache : ne doit pas servir de fallback
    scheduleFor($company, 'Orphelin', [], weekFor($weekday, [segmentFor('morning', '08:00', '17:00', ['08:00'])]));

    $employee = Employee::create(['company_id' => $company, 'department_id' => $dept]);

    $enriched = app(AttendanceEvaluationService::class)
        ->enrichRecords(collect([recordFor($employee->id, $date, '08:05')]))
        ->first();

    expect($enriched->segments)->toBeNull();
    expect($enriched->expected_shift)->toBeNull();
});
