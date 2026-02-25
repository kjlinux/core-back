<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RfidCardSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $c1 = '11111111-1111-1111-1111-111111111101';
        $c2 = '11111111-1111-1111-1111-111111111102';
        $e1 = '44444444-4444-4444-4444-444444444401';
        $e2 = '44444444-4444-4444-4444-444444444402';
        $e7 = '44444444-4444-4444-4444-444444444407';

        $cards = [
            ['id' => '55555555-5555-5555-5555-555555555501', 'uid' => 'A1B2C3D4', 'employee_id' => $e1, 'company_id' => $c1, 'status' => 'active', 'assigned_at' => $now->copy()->subMonths(6), 'blocked_at' => null, 'block_reason' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '55555555-5555-5555-5555-555555555502', 'uid' => 'E5F6G7H8', 'employee_id' => $e2, 'company_id' => $c1, 'status' => 'active', 'assigned_at' => $now->copy()->subMonths(5), 'blocked_at' => null, 'block_reason' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '55555555-5555-5555-5555-555555555503', 'uid' => 'I9J0K1L2', 'employee_id' => null, 'company_id' => $c2, 'status' => 'blocked', 'assigned_at' => null, 'blocked_at' => $now->copy()->subWeeks(2), 'block_reason' => 'Perdue par employe', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '55555555-5555-5555-5555-555555555504', 'uid' => 'M3N4O5P6', 'employee_id' => null, 'company_id' => $c1, 'status' => 'inactive', 'assigned_at' => null, 'blocked_at' => null, 'block_reason' => null, 'created_at' => $now, 'updated_at' => $now],
            ['id' => '55555555-5555-5555-5555-555555555505', 'uid' => 'Q7R8S9T0', 'employee_id' => $e7, 'company_id' => $c2, 'status' => 'active', 'assigned_at' => $now->copy()->subMonths(3), 'blocked_at' => null, 'block_reason' => null, 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('rfid_cards')->insert($cards);
    }
}
