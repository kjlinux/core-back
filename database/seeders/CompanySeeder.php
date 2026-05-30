<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $companies = [
            [
                'id' => '11111111-1111-1111-1111-111111111101',
                'name' => 'Orange Burkina Faso',
                'email' => 'contact@orange.bf',
                'phone' => '+226 25 30 60 00',
                'address' => 'Avenue de la Nation, Ouagadougou',
                'matricule_prefix' => 'ORG',
                'subscription' => 'premium',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => '11111111-1111-1111-1111-111111111102',
                'name' => 'Coris Bank International',
                'email' => 'info@corisbank.bf',
                'phone' => '+226 25 30 40 00',
                'address' => 'Avenue Kwame Nkrumah, Ouagadougou',
                'matricule_prefix' => 'CBI',
                'subscription' => 'premium',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => '11111111-1111-1111-1111-111111111103',
                'name' => 'ONATEL',
                'email' => 'contact@onatel.bf',
                'phone' => '+226 25 49 01 01',
                'address' => 'Avenue de la Nation, Ouagadougou',
                'matricule_prefix' => 'ONA',
                'subscription' => 'premium',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => '11111111-1111-1111-1111-111111111104',
                'name' => 'SONABEL',
                'email' => 'info@sonabel.bf',
                'phone' => '+226 25 30 61 00',
                'address' => '55 Avenue de la Nation, Ouagadougou',
                'matricule_prefix' => 'SNB',
                'subscription' => 'freemium',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('companies')->insert($companies);
    }
}
