<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class FeelbackEntrySeeder extends Seeder
{
    public function run(): void
    {
        $fb1 = '99999999-9999-9999-9999-999999999901';
        $fb2 = '99999999-9999-9999-9999-999999999902';
        $fb3 = '99999999-9999-9999-9999-999999999903';

        $s1 = '22222222-2222-2222-2222-222222222201';
        $s2 = '22222222-2222-2222-2222-222222222202';
        $s3 = '22222222-2222-2222-2222-222222222203';

        $devices = [
            ['id' => $fb1, 'site_id' => $s1],
            ['id' => $fb2, 'site_id' => $s2],
            ['id' => $fb3, 'site_id' => $s3],
        ];

        $levels = ['bon', 'bon', 'bon', 'bon', 'bon', 'bon', 'bon', 'neutre', 'neutre', 'mauvais'];
        $entries = [];

        for ($i = 0; $i < 30; $i++) {
            $device = $devices[array_rand($devices)];
            $level = $levels[array_rand($levels)];
            $timestamp = Carbon::now()->subHours(rand(1, 168));

            $entries[] = [
                'id' => Str::uuid()->toString(),
                'device_id' => $device['id'],
                'level' => $level,
                'site_id' => $device['site_id'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        DB::table('feelback_entries')->insert($entries);
    }
}
