<?php

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/*
 * Les migrations applicatives sont specifiques a PostgreSQL (DROP CONSTRAINT ...),
 * incompatibles avec le sqlite :memory: des tests. On batit donc le schema minimal
 * (companies + schedules) a la main, comme tests/Feature/ScheduleResolutionTest.php.
 *
 * Objectif : verrouiller le contrat de casse du blob `days`. Le frontend envoie les
 * cles imbriquees en snake_case (l'intercepteur axios snake_case tout le corps de
 * requete) ; l'API doit les renormaliser en camelCase avant persistance, sinon les
 * services (AttendanceEvaluationService/ScheduleResolverService) et le pre-remplissage
 * de la page d'edition ne peuvent plus lire les segments.
 */
beforeEach(function () {
    Schema::create('companies', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('name');
        $t->timestamps();
    });

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
});

afterEach(function () {
    Schema::dropIfExists('schedules');
    Schema::dropIfExists('companies');
});

// --- Helpers ----------------------------------------------------------------

function schedApiActAsSuperAdmin(): User
{
    $user = new User(['role' => 'super_admin']);
    $user->id = (string) Str::uuid();
    Sanctum::actingAs($user);

    return $user;
}

function schedApiSeedCompany(): string
{
    $id = (string) Str::uuid();
    DB::table('companies')->insert([
        'id' => $id,
        'name' => 'ACME',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

// Charge `days` telle que recue apres l'intercepteur axios : cles imbriquees snake_case.
function schedApiSnakeDays(): array
{
    $segment = [
        'kind' => 'morning',
        'start_time' => '08:00',
        'end_time' => '12:00',
        'expected_punches' => [
            ['time' => '08:00', 'label' => 'Arrivee'],
            ['time' => '12:00', 'label' => 'Depart'],
        ],
        'late_tolerance' => 5,
    ];

    $days = [];
    for ($w = 1; $w <= 7; $w++) {
        $days[] = [
            'weekday' => $w,
            'worked' => $w === 1,
            'segments' => $w === 1 ? [$segment] : [],
        ];
    }

    return $days;
}

// --- Tests ------------------------------------------------------------------

it('renormalise les cles snake_case de `days` en camelCase a la creation', function () {
    schedApiActAsSuperAdmin();
    $companyId = schedApiSeedCompany();

    $response = $this->postJson('/api/schedules', [
        'company_id' => $companyId,
        'name' => 'Horaire Jour',
        'type' => 'day',
        'default_late_tolerance' => 0,
        'days' => schedApiSnakeDays(),
        'assigned_departments' => [],
    ]);

    $response->assertStatus(201);

    // La reponse expose les cles imbriquees en camelCase.
    $response->assertJsonPath('data.days.0.segments.0.startTime', '08:00');
    $response->assertJsonPath('data.days.0.segments.0.endTime', '12:00');
    $response->assertJsonPath('data.days.0.segments.0.lateTolerance', 5);
    $response->assertJsonPath('data.days.0.segments.0.expectedPunches.0.time', '08:00');

    // Et la persistance est canonique (camelCase), lisible par les services.
    $schedule = Schedule::firstWhere('name', 'Horaire Jour');
    $segment = $schedule->days[0]['segments'][0];

    expect($segment)->toHaveKeys(['startTime', 'endTime', 'expectedPunches', 'lateTolerance']);
    expect($segment)->not->toHaveKey('start_time');
    expect($segment)->not->toHaveKey('end_time');
    expect($segment)->not->toHaveKey('expected_punches');
    expect($segment)->not->toHaveKey('late_tolerance');
    expect($segment['startTime'])->toBe('08:00');
    expect($segment['expectedPunches'][0]['label'])->toBe('Arrivee');
});

it('renormalise les cles snake_case de `days` en camelCase a la mise a jour', function () {
    schedApiActAsSuperAdmin();
    $companyId = schedApiSeedCompany();

    $schedule = Schedule::create([
        'company_id' => $companyId,
        'name' => 'Initial',
        'type' => 'day',
        'default_late_tolerance' => 0,
        'days' => [],
        'assigned_departments' => [],
    ]);

    $response = $this->putJson("/api/schedules/{$schedule->id}", [
        'days' => schedApiSnakeDays(),
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('data.days.0.segments.0.startTime', '08:00');

    $schedule->refresh();
    $segment = $schedule->days[0]['segments'][0];

    expect($segment)->toHaveKey('startTime');
    expect($segment)->not->toHaveKey('start_time');
    expect($segment['endTime'])->toBe('12:00');
    expect($segment['lateTolerance'])->toBe(5);
});

it('accepte une charge `days` deja en camelCase sans la modifier (idempotence)', function () {
    schedApiActAsSuperAdmin();
    $companyId = schedApiSeedCompany();

    $camelSegment = [
        'kind' => 'morning',
        'startTime' => '09:00',
        'endTime' => '13:00',
        'expectedPunches' => [['time' => '09:00', 'label' => 'Arrivee']],
        'lateTolerance' => 10,
    ];
    $days = [['weekday' => 1, 'worked' => true, 'segments' => [$camelSegment]]];
    for ($w = 2; $w <= 7; $w++) {
        $days[] = ['weekday' => $w, 'worked' => false, 'segments' => []];
    }

    $response = $this->postJson('/api/schedules', [
        'company_id' => $companyId,
        'name' => 'Deja CamelCase',
        'type' => 'day',
        'default_late_tolerance' => 0,
        'days' => $days,
        'assigned_departments' => [],
    ]);

    $response->assertStatus(201);

    $schedule = Schedule::firstWhere('name', 'Deja CamelCase');
    $segment = $schedule->days[0]['segments'][0];

    expect($segment['startTime'])->toBe('09:00');
    expect($segment['endTime'])->toBe('13:00');
    expect($segment['lateTolerance'])->toBe(10);
});
