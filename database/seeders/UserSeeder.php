<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $password = Hash::make('admin123');

        $c1 = '11111111-1111-1111-1111-111111111101';
        $c2 = '11111111-1111-1111-1111-111111111102';

        $users = [
            ['name' => 'Amadou Diallo', 'first_name' => 'Amadou', 'last_name' => 'Diallo', 'email' => 'admin@tanga.com', 'password' => $password, 'role' => 'super_admin', 'company_id' => null, 'is_active' => true, 'email_verified_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Fatou Ouedraogo', 'first_name' => 'Fatou', 'last_name' => 'Ouedraogo', 'email' => 'admin@orange-bf.com', 'password' => $password, 'role' => 'admin_enterprise', 'company_id' => $c1, 'is_active' => true, 'email_verified_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Aissata Traore', 'first_name' => 'Aissata', 'last_name' => 'Traore', 'email' => 'admin@coris.com', 'password' => $password, 'role' => 'admin_enterprise', 'company_id' => $c2, 'is_active' => true, 'email_verified_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Ibrahim Sawadogo', 'first_name' => 'Ibrahim', 'last_name' => 'Sawadogo', 'email' => 'manager@orange-bf.com', 'password' => $password, 'role' => 'manager', 'company_id' => $c1, 'is_active' => true, 'email_verified_at' => $now, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Moussa Kabore', 'first_name' => 'Moussa', 'last_name' => 'Kabore', 'email' => 'manager@coris.com', 'password' => $password, 'role' => 'manager', 'company_id' => $c2, 'is_active' => true, 'email_verified_at' => $now, 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('users')->insert($users);
    }
}
