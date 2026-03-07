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
        $password = Hash::make('Admin123!');

        $users = [
            ['name' => 'Administrateur Tanga Flow', 'first_name' => 'Tanga', 'last_name' => 'Flow', 'email' => 'admin@tanga.com', 'password' => $password, 'role' => 'super_admin', 'company_id' => null, 'is_active' => true, 'email_verified_at' => $now, 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('users')->insert($users);
    }
}
