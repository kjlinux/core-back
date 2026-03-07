<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $products = [
            [
                'id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaa003',
                'name' => 'Pack Entreprise',
                'description' => 'Pack Entreprise RFID',
                'category' => 'enterprise_pack',
                'price' => 1000,
                'currency' => 'XOF',
                'stock_quantity' => 100,
                'images' => json_encode(['/images/products/enterprise-pack.jpg']),
                'customizable' => true,
                'min_quantity' => 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('products')->insert($products);
    }
}
