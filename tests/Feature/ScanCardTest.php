<?php

use App\Models\Company;
use App\Models\RfidDevice;
use App\Models\User;
use App\Services\MqttService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

/*
 * Les migrations applicatives sont specifiques a PostgreSQL et incompatibles avec le
 * sqlite :memory: des tests (RefreshDatabase est desactive dans Pest.php). On batit donc
 * le schema minimal a la main, comme tests/Feature/CompanyUpdateApiTest.php.
 *
 * Objectif : verrouiller le contrat de POST /api/rfid/devices/{id}/scan (bouton "Scanner"
 * de la page d'enregistrement de carte) :
 *  - admin_enterprise / technicien / super_admin peuvent declencher le mode scan ;
 *  - le scope BelongsToCompany empeche de viser le capteur d'une autre entreprise (404) ;
 *  - un manager (hors groupe de roles) est bloque par le middleware (403).
 */
beforeEach(function () {
    Schema::create('users', function (Blueprint $t) {
        $t->id();
        $t->string('name')->nullable();
        $t->string('first_name')->nullable();
        $t->string('last_name')->nullable();
        $t->string('email')->unique();
        $t->string('phone')->nullable();
        $t->string('password');
        $t->string('role')->default('employe');
        $t->uuid('company_id')->nullable();
        $t->uuid('employee_id')->nullable();
        $t->string('avatar')->nullable();
        $t->boolean('is_active')->default(true);
        $t->timestamps();
    });

    Schema::create('companies', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('name')->nullable();
        $t->string('subscription')->default('freemium');
        $t->boolean('is_active')->default(true);
        $t->timestamps();
    });

    Schema::create('rfid_devices', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->string('serial_number')->nullable();
        $t->string('name')->nullable();
        $t->uuid('company_id')->nullable();
        $t->uuid('site_id')->nullable();
        $t->boolean('is_online')->default(false);
        $t->timestamp('last_ping_at')->nullable();
        $t->string('firmware_version')->nullable();
        $t->string('mqtt_topic')->nullable();
        $t->boolean('is_witness')->default(false);
        $t->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('rfid_devices');
    Schema::dropIfExists('companies');
    Schema::dropIfExists('users');
});

// --- Helpers ----------------------------------------------------------------

function scanCompany(string $name = 'ACME'): Company
{
    return Company::create(['name' => $name]);
}

function scanUser(string $role, ?string $companyId, string $email): User
{
    return User::create([
        'name' => 'Test User',
        'email' => $email,
        'password' => 'Secret123',
        'role' => $role,
        'company_id' => $companyId,
        'is_active' => true,
    ]);
}

function scanDevice(string $companyId, string $serial = 'RFID-2026-001'): RfidDevice
{
    return RfidDevice::create([
        'serial_number' => $serial,
        'name' => 'Capteur entree',
        'company_id' => $companyId,
        'mqtt_topic' => "core/rfid/sensor/{$serial}/event",
        'is_online' => true,
    ]);
}

// ---------------------------------------------------------------------------

it('permet a un admin_enterprise de declencher le scan sur son propre capteur', function () {
    $company = scanCompany();
    $device = scanDevice($company->id);

    $mqtt = $this->mock(MqttService::class);
    $mqtt->shouldReceive('getResponseTopic')->once()
        ->with('core/rfid/sensor/RFID-2026-001/event')
        ->andReturn('core/rfid/sensor/RFID-2026-001/response');
    $mqtt->shouldReceive('publish')->once()
        ->with('core/rfid/sensor/RFID-2026-001/response', '0x100030');

    Sanctum::actingAs(scanUser('admin_enterprise', $company->id, 'admin@example.com'));

    $this->postJson("/api/rfid/devices/{$device->id}/scan")
        ->assertOk()
        ->assertJsonPath('data.command', '0x100030');
});

it('refuse a un admin_enterprise de scanner le capteur d_une autre entreprise', function () {
    $companyA = scanCompany('ACME');
    $companyB = scanCompany('Autre');
    $deviceB = scanDevice($companyB->id, 'RFID-2026-002');

    $mqtt = $this->mock(MqttService::class);
    $mqtt->shouldReceive('publish')->never();

    Sanctum::actingAs(scanUser('admin_enterprise', $companyA->id, 'admin@example.com'));

    $this->postJson("/api/rfid/devices/{$deviceB->id}/scan")
        ->assertNotFound();
});

it('interdit a un manager de declencher le scan', function () {
    $company = scanCompany();

    $mqtt = $this->mock(MqttService::class);
    $mqtt->shouldReceive('publish')->never();

    Sanctum::actingAs(scanUser('manager', $company->id, 'manager@example.com'));

    $this->postJson('/api/rfid/devices/some-id/scan')
        ->assertForbidden();
});
