<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $c1 = '11111111-1111-1111-1111-111111111101';
        $c2 = '11111111-1111-1111-1111-111111111102';

        $d1 = '33333333-3333-3333-3333-333333333301';
        $d2 = '33333333-3333-3333-3333-333333333302';
        $d3 = '33333333-3333-3333-3333-333333333303';
        $d4 = '33333333-3333-3333-3333-333333333304';
        $d5 = '33333333-3333-3333-3333-333333333305';
        $d6 = '33333333-3333-3333-3333-333333333306';
        $d7 = '33333333-3333-3333-3333-333333333307';

        $schedules = [
            [
                'id' => '66666666-6666-6666-6666-666666666601',
                'company_id' => $c1,
                'name' => 'Horaire Standard',
                'type' => 'standard',
                'start_time' => '08:00',
                'end_time' => '17:00',
                'break_start' => '12:00',
                'break_end' => '13:00',
                'work_days' => json_encode([1, 2, 3, 4, 5]),
                'late_tolerance' => 15,
                'assigned_departments' => json_encode([$d1, $d2, $d3]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => '66666666-6666-6666-6666-666666666602',
                'company_id' => $c1,
                'name' => 'Horaire Matin',
                'type' => 'custom',
                'start_time' => '06:00',
                'end_time' => '14:00',
                'break_start' => '10:00',
                'break_end' => '10:30',
                'work_days' => json_encode([1, 2, 3, 4, 5, 6]),
                'late_tolerance' => 10,
                'assigned_departments' => json_encode([$d4]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => '66666666-6666-6666-6666-666666666603',
                'company_id' => $c2,
                'name' => 'Horaire Soir',
                'type' => 'custom',
                'start_time' => '14:00',
                'end_time' => '22:00',
                'break_start' => null,
                'break_end' => null,
                'work_days' => json_encode([1, 2, 3, 4, 5]),
                'late_tolerance' => 10,
                'assigned_departments' => json_encode([$d5, $d6, $d7]),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('schedules')->insert($schedules);

        $holidays = [
            ['id' => '77777777-7777-7777-7777-777777777701', 'company_id' => $c1, 'name' => 'Fete Nationale', 'date' => '2026-08-05', 'is_recurring' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '77777777-7777-7777-7777-777777777702', 'company_id' => $c1, 'name' => 'Fete du Travail', 'date' => '2026-05-01', 'is_recurring' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '77777777-7777-7777-7777-777777777703', 'company_id' => $c1, 'name' => 'Noel', 'date' => '2026-12-25', 'is_recurring' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '77777777-7777-7777-7777-777777777704', 'company_id' => $c1, 'name' => 'Nouvel An', 'date' => '2026-01-01', 'is_recurring' => true, 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('holidays')->insert($holidays);
    }
}
