<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CompanySeeder::class,
            SiteSeeder::class,
            DepartmentSeeder::class,
            UserSeeder::class,
            EmployeeSeeder::class,
            RfidCardSeeder::class,
            RfidDeviceSeeder::class,
            ScheduleSeeder::class,
            BiometricDeviceSeeder::class,
            FeelbackDeviceSeeder::class,
            ProductSeeder::class,
            OrderSeeder::class,
            AttendanceSeeder::class,
            FeelbackEntrySeeder::class,
        ]);
    }
}
