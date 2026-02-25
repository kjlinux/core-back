<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SiteSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $sites = [
            ['id' => '22222222-2222-2222-2222-222222222201', 'name' => 'Siege Principal', 'company_id' => '11111111-1111-1111-1111-111111111101', 'address' => 'Ouagadougou Centre', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '22222222-2222-2222-2222-222222222202', 'name' => 'Agence Commerciale Ouaga 2000', 'company_id' => '11111111-1111-1111-1111-111111111101', 'address' => 'Ouaga 2000', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '22222222-2222-2222-2222-222222222203', 'name' => 'Siege Social', 'company_id' => '11111111-1111-1111-1111-111111111102', 'address' => 'Ouagadougou', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '22222222-2222-2222-2222-222222222204', 'name' => 'Agence Bobo', 'company_id' => '11111111-1111-1111-1111-111111111102', 'address' => 'Bobo-Dioulasso', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '22222222-2222-2222-2222-222222222205', 'name' => 'Direction Generale', 'company_id' => '11111111-1111-1111-1111-111111111103', 'address' => 'Ouagadougou', 'created_at' => $now, 'updated_at' => $now],
            ['id' => '22222222-2222-2222-2222-222222222206', 'name' => 'Centre Technique', 'company_id' => '11111111-1111-1111-1111-111111111104', 'address' => 'Ouagadougou', 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('sites')->insert($sites);
    }
}
