<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $e1 = '44444444-4444-4444-4444-444444444401';
        $e2 = '44444444-4444-4444-4444-444444444402';
        $e3 = '44444444-4444-4444-4444-444444444403';
        $e4 = '44444444-4444-4444-4444-444444444404';
        $e5 = '44444444-4444-4444-4444-444444444405';
        $e7 = '44444444-4444-4444-4444-444444444407';
        $e8 = '44444444-4444-4444-4444-444444444408';

        $employees = [$e1, $e2, $e3, $e4, $e5, $e7, $e8];
        $biometricEmployees = [$e1, $e2, $e4, $e7];
        $records = [];

        for ($dayOffset = 4; $dayOffset >= 0; $dayOffset--) {
            $date = Carbon::now()->subDays($dayOffset);
            if ($date->isWeekend()) {
                continue;
            }

            $dateStr = $date->toDateString();

            foreach ($employees as $empId) {
                $rand = rand(1, 100);

                if ($rand <= 10) {
                    $records[] = [
                        'id' => Str::uuid()->toString(),
                        'employee_id' => $empId,
                        'date' => $dateStr,
                        'entry_time' => null,
                        'exit_time' => null,
                        'status' => 'absent',
                        'late_minutes' => 0,
                        'early_departure_minutes' => 0,
                        'source' => $rand % 2 === 0 ? 'rfid' : 'biometric',
                        'is_double_badge' => false,
                        'created_at' => $date,
                        'updated_at' => $date,
                    ];
                    continue;
                }

                $isLate = $rand <= 30;
                $lateMinutes = $isLate ? rand(2, 45) : 0;
                $entryHour = $isLate ? 8 : 7;
                $entryMinute = $isLate ? rand(16, 59) : rand(30, 59);

                $leftEarly = $rand > 90;
                $earlyMinutes = $leftEarly ? rand(30, 120) : 0;
                $exitHour = $leftEarly ? rand(14, 16) : 17;
                $exitMinute = $leftEarly ? rand(0, 30) : rand(0, 30);

                $entryTime = $date->copy()->setTime($entryHour, $entryMinute);
                $exitTime = $date->copy()->setTime($exitHour, $exitMinute);

                $status = 'present';
                if ($isLate) $status = 'late';
                if ($leftEarly) $status = 'left_early';

                $isDoubleBadge = $empId === $e3 && $dayOffset === 2;
                $source = in_array($empId, $biometricEmployees) ? 'biometric' : 'rfid';

                $records[] = [
                    'id' => Str::uuid()->toString(),
                    'employee_id' => $empId,
                    'date' => $dateStr,
                    'entry_time' => $entryTime,
                    'exit_time' => $exitTime,
                    'status' => $status,
                    'late_minutes' => $lateMinutes,
                    'early_departure_minutes' => $earlyMinutes,
                    'source' => $source,
                    'is_double_badge' => $isDoubleBadge,
                    'created_at' => $entryTime,
                    'updated_at' => $exitTime,
                ];
            }
        }

        DB::table('attendance_records')->insert($records);
    }
}
