<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FeelbackDeviceSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $c1 = '11111111-1111-1111-1111-111111111101';
        $c2 = '11111111-1111-1111-1111-111111111102';
        $s1 = '22222222-2222-2222-2222-222222222201';
        $s2 = '22222222-2222-2222-2222-222222222202';
        $s3 = '22222222-2222-2222-2222-222222222203';

        $devices = [
            ['id' => '99999999-9999-9999-9999-999999999901', 'serial_number' => 'FB-SN-001', 'company_id' => $c1, 'site_id' => $s1, 'is_online' => true, 'battery_level' => 85, 'last_ping_at' => $now->copy()->subMinutes(2), 'assigned_agent' => 'Jean Compaore', 'mqtt_topic' => 'core/feelback/sensor/FB-SN-001/event', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '99999999-9999-9999-9999-999999999902', 'serial_number' => 'FB-SN-002', 'company_id' => $c1, 'site_id' => $s2, 'is_online' => true, 'battery_level' => 42, 'last_ping_at' => $now->copy()->subMinutes(15), 'assigned_agent' => 'Marie Zongo', 'mqtt_topic' => 'core/feelback/sensor/FB-SN-002/event', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '99999999-9999-9999-9999-999999999903', 'serial_number' => 'FB-SN-003', 'company_id' => $c2, 'site_id' => $s3, 'is_online' => false, 'battery_level' => 12, 'last_ping_at' => $now->copy()->subDays(1), 'assigned_agent' => null, 'mqtt_topic' => 'core/feelback/sensor/FB-SN-003/event', 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('feelback_devices')->insert($devices);
    }
}
