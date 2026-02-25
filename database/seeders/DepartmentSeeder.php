<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $departments = [
            ['id' => '33333333-3333-3333-3333-333333333301', 'name' => 'Direction Generale', 'site_id' => '22222222-2222-2222-2222-222222222201', 'company_id' => '11111111-1111-1111-1111-111111111101', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '33333333-3333-3333-3333-333333333302', 'name' => 'Ressources Humaines', 'site_id' => '22222222-2222-2222-2222-222222222201', 'company_id' => '11111111-1111-1111-1111-111111111101', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '33333333-3333-3333-3333-333333333303', 'name' => 'Direction Technique', 'site_id' => '22222222-2222-2222-2222-222222222201', 'company_id' => '11111111-1111-1111-1111-111111111101', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '33333333-3333-3333-3333-333333333304', 'name' => 'Service Commercial', 'site_id' => '22222222-2222-2222-2222-222222222202', 'company_id' => '11111111-1111-1111-1111-111111111101', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '33333333-3333-3333-3333-333333333305', 'name' => 'Direction Generale', 'site_id' => '22222222-2222-2222-2222-222222222203', 'company_id' => '11111111-1111-1111-1111-111111111102', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '33333333-3333-3333-3333-333333333306', 'name' => 'Service Clientele', 'site_id' => '22222222-2222-2222-2222-222222222203', 'company_id' => '11111111-1111-1111-1111-111111111102', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '33333333-3333-3333-3333-333333333307', 'name' => 'Operations', 'site_id' => '22222222-2222-2222-2222-222222222204', 'company_id' => '11111111-1111-1111-1111-111111111102', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '33333333-3333-3333-3333-333333333308', 'name' => 'Direction', 'site_id' => '22222222-2222-2222-2222-222222222205', 'company_id' => '11111111-1111-1111-1111-111111111103', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '33333333-3333-3333-3333-333333333309', 'name' => 'Exploitation', 'site_id' => '22222222-2222-2222-2222-222222222206', 'company_id' => '11111111-1111-1111-1111-111111111104', 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('departments')->insert($departments);
    }
}
