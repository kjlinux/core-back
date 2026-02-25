<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BiometricDeviceSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $c1 = '11111111-1111-1111-1111-111111111101';
        $c2 = '11111111-1111-1111-1111-111111111102';
        $s1 = '22222222-2222-2222-2222-222222222201';
        $s3 = '22222222-2222-2222-2222-222222222203';

        $devices = [
            ['id' => '88888888-8888-8888-8888-888888888801', 'serial_number' => 'BIO-SN-001', 'company_id' => $c1, 'site_id' => $s1, 'name' => 'Entree Principale', 'is_online' => true, 'last_sync_at' => $now->copy()->subMinutes(5), 'firmware_version' => '2.1.0', 'enrolled_count' => 45, 'mqtt_topic' => 'core/biometric/sensor/BIO-SN-001/event', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '88888888-8888-8888-8888-888888888802', 'serial_number' => 'BIO-SN-002', 'company_id' => $c1, 'site_id' => $s1, 'name' => 'Parking', 'is_online' => true, 'last_sync_at' => $now->copy()->subMinutes(10), 'firmware_version' => '2.0.5', 'enrolled_count' => 30, 'mqtt_topic' => 'core/biometric/sensor/BIO-SN-002/event', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '88888888-8888-8888-8888-888888888803', 'serial_number' => 'BIO-SN-003', 'company_id' => $c2, 'site_id' => $s3, 'name' => 'Salle de reunion', 'is_online' => false, 'last_sync_at' => $now->copy()->subDays(2), 'firmware_version' => '1.9.2', 'enrolled_count' => 15, 'mqtt_topic' => 'core/biometric/sensor/BIO-SN-003/event', 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('biometric_devices')->insert($devices);
    }
}
