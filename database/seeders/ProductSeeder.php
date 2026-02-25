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
                'id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaa001',
                'name' => 'Carte RFID Standard',
                'description' => 'Carte RFID blanche standard compatible avec tous nos lecteurs. Frequence 13.56 MHz, norme ISO 14443A.',
                'category' => 'standard_card',
                'price' => 5000,
                'currency' => 'XOF',
                'stock_quantity' => 500,
                'images' => json_encode(['/images/products/standard-card.jpg']),
                'customizable' => false,
                'min_quantity' => 10,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaa002',
                'name' => 'Carte RFID Personnalisee',
                'description' => 'Carte RFID personnalisable avec logo, nom et couleurs de votre entreprise. Impression recto-verso haute qualite.',
                'category' => 'custom_card',
                'price' => 8000,
                'currency' => 'XOF',
                'stock_quantity' => 200,
                'images' => json_encode(['/images/products/custom-card.jpg']),
                'customizable' => true,
                'min_quantity' => 25,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaa003',
                'name' => 'Pack Entreprise (100 cartes + lecteur)',
                'description' => 'Pack complet comprenant 100 cartes RFID personnalisees et un lecteur USB professionnel. Ideal pour demarrer.',
                'category' => 'enterprise_pack',
                'price' => 500000,
                'currency' => 'XOF',
                'stock_quantity' => 10,
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
