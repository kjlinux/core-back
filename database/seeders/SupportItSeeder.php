<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SupportItSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $companyId = (string) Str::uuid();
        if (!DB::table('companies')->where('name', 'Support IT Tanga')->exists()) {
            DB::table('companies')->insert([
                'id' => $companyId,
                'name' => 'Support IT Tanga',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $companyId = DB::table('companies')->where('name', 'Support IT Tanga')->value('id');
        }

        $siteId = (string) Str::uuid();
        if (!DB::table('sites')->where('name', 'Locaux Support')->where('company_id', $companyId)->exists()) {
            DB::table('sites')->insert([
                'id' => $siteId,
                'company_id' => $companyId,
                'name' => 'Locaux Support',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (!DB::table('users')->where('email', 'support@tanga.com')->exists()) {
            DB::table('users')->insert([
                'name' => 'Support IT',
                'first_name' => 'Support',
                'last_name' => 'IT',
                'email' => 'support@tanga.com',
                'password' => Hash::make('Support123!'),
                'role' => 'support_it',
                'company_id' => $companyId,
                'is_active' => true,
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
