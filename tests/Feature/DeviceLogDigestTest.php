<?php

use App\Console\Commands\MqttListenRfidCommand;
use App\Mail\DeviceLogsDigestMail;
use App\Models\Company;
use App\Models\DeviceLog;
use App\Models\RfidDevice;
use App\Models\User;
use Illuminate\Console\OutputStyle;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/*
 * Les migrations applicatives sont specifiques a PostgreSQL et incompatibles avec le
 * sqlite :memory: des tests (RefreshDatabase desactive). On batit le schema minimal a la
 * main, comme tests/Feature/ScanCardTest.php.
 *
 * Couvre :
 *  - device-logs:send-digest : destinataires (support_it + adresse fixe), fenetre 24h,
 *    et mail "tout va bien" meme sans incident ;
 *  - MqttListenRfidCommand::processDeviceLog : persistance d'un log remonte par MQTT.
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
        $t->timestamps();
    });

    Schema::create('device_logs', function (Blueprint $t) {
        $t->uuid('id')->primary();
        $t->uuid('company_id')->nullable();
        $t->uuid('site_id')->nullable();
        $t->uuid('device_id')->nullable();
        $t->string('device_kind')->default('rfid');
        $t->string('serial_number')->nullable();
        $t->string('level')->default('info');
        $t->text('message');
        $t->string('firmware_version')->nullable();
        $t->json('context')->nullable();
        $t->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('device_logs');
    Schema::dropIfExists('rfid_devices');
    Schema::dropIfExists('companies');
    Schema::dropIfExists('users');
});

it('envoie le digest aux support_it et a l_adresse fixe avec les logs des 24h', function () {
    Mail::fake();

    User::create([
        'name' => 'Support', 'email' => 'support@tangaflow.com', 'password' => 'Secret123',
        'role' => 'support_it', 'is_active' => true,
    ]);
    User::create([
        'name' => 'Support inactif', 'email' => 'inactif@tangaflow.com', 'password' => 'Secret123',
        'role' => 'support_it', 'is_active' => false,
    ]);
    User::create([
        'name' => 'Manager', 'email' => 'manager@tangaflow.com', 'password' => 'Secret123',
        'role' => 'manager', 'is_active' => true,
    ]);

    DeviceLog::create([
        'device_kind' => 'rfid', 'serial_number' => 'RFID-2026-001',
        'level' => 'error', 'message' => 'Wi-Fi perdu',
    ]);

    $old = DeviceLog::create([
        'device_kind' => 'rfid', 'serial_number' => 'RFID-2026-001',
        'level' => 'warning', 'message' => 'Vieux log',
    ]);
    $old->forceFill(['created_at' => now()->subDays(2)])->save();

    $this->artisan('device-logs:send-digest')->assertExitCode(0);

    Mail::assertQueued(DeviceLogsDigestMail::class, function (DeviceLogsDigestMail $mail) {
        return $mail->hasTo('support@tangaflow.com')
            && $mail->hasTo('koffijude33@gmail.com')
            && ! $mail->hasTo('inactif@tangaflow.com')
            && ! $mail->hasTo('manager@tangaflow.com')
            && $mail->logs->count() === 1;
    });
});

it('envoie un mail "tout va bien" meme sans aucun incident', function () {
    Mail::fake();

    User::create([
        'name' => 'Support', 'email' => 'support@tangaflow.com', 'password' => 'Secret123',
        'role' => 'support_it', 'is_active' => true,
    ]);

    $this->artisan('device-logs:send-digest')->assertExitCode(0);

    Mail::assertQueued(DeviceLogsDigestMail::class, function (DeviceLogsDigestMail $mail) {
        return $mail->hasTo('koffijude33@gmail.com') && $mail->logs->count() === 0;
    });

    $mail = new DeviceLogsDigestMail(collect(), now()->subDay(), now());
    $mail->assertSeeInHtml('Aucun incident');
});

it('rend le digest en groupant les messages par terminal', function () {
    $logs = collect([
        new DeviceLog(['serial_number' => 'RFID-2026-001', 'level' => 'error', 'message' => 'Wi-Fi perdu', 'created_at' => now()]),
        new DeviceLog(['serial_number' => 'RFID-2026-002', 'level' => 'critical', 'message' => 'MQTT echec', 'created_at' => now()]),
    ]);

    $mail = new DeviceLogsDigestMail($logs, now()->subDay(), now());

    $mail->assertSeeInHtml('RFID-2026-001');
    $mail->assertSeeInHtml('Wi-Fi perdu');
    $mail->assertSeeInHtml('RFID-2026-002');
    $mail->assertSeeInHtml('MQTT echec');
});

it('persiste un log MQTT en normalisant le niveau et en rattachant le terminal', function () {
    $company = Company::create(['name' => 'ACME']);
    $device = RfidDevice::create([
        'serial_number' => 'RFID-2026-001', 'name' => 'Entree', 'company_id' => $company->id,
    ]);

    $command = app(MqttListenRfidCommand::class);
    $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));

    $method = (new ReflectionMethod($command, 'processDeviceLog'));
    $method->invoke($command, [
        'event' => 'log', 'level' => 'ERROR', 'message' => 'Echec capteur RFID',
        'uptime' => 120, 'version' => 'V2.0.3',
    ], 'RFID-2026-001');

    $log = DeviceLog::first();

    expect($log)->not->toBeNull()
        ->and($log->level)->toBe('error')
        ->and($log->company_id)->toBe($company->id)
        ->and($log->device_id)->toBe($device->id)
        ->and($log->firmware_version)->toBe('V2.0.3')
        ->and($log->context['uptime'])->toBe(120);
});
