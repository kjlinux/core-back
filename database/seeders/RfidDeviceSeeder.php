<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RfidDeviceSeeder extends Seeder
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
            ['id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaa901', 'serial_number' => 'RFID-SN-001', 'name' => 'Lecteur Entree Principale', 'company_id' => $c1, 'site_id' => $s1, 'is_online' => true, 'last_ping_at' => $now->copy()->subMinutes(1), 'mqtt_topic' => 'core/rfid/sensor/RFID-SN-001/event', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaa902', 'serial_number' => 'RFID-SN-002', 'name' => 'Lecteur Sortie Principale', 'company_id' => $c1, 'site_id' => $s1, 'is_online' => true, 'last_ping_at' => $now->copy()->subMinutes(5), 'mqtt_topic' => 'core/rfid/sensor/RFID-SN-002/event', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaa903', 'serial_number' => 'RFID-SN-003', 'name' => 'Lecteur Parking', 'company_id' => $c1, 'site_id' => $s2, 'is_online' => false, 'last_ping_at' => $now->copy()->subDays(2), 'mqtt_topic' => 'core/rfid/sensor/RFID-SN-003/event', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaa904', 'serial_number' => 'RFID-SN-004', 'name' => 'Lecteur Bureau Direction', 'company_id' => $c2, 'site_id' => $s3, 'is_online' => true, 'last_ping_at' => $now->copy()->subMinutes(10), 'mqtt_topic' => 'core/rfid/sensor/RFID-SN-004/event', 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('rfid_devices')->insert($devices);
    }
}
