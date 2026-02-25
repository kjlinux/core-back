<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $c1 = '11111111-1111-1111-1111-111111111101';
        $c2 = '11111111-1111-1111-1111-111111111102';
        $p1 = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaa001';
        $p2 = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaa002';
        $p3 = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaa003';

        $ord1 = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbb001';
        $ord2 = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbb002';
        $ord3 = 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbb003';

        $orders = [
            [
                'id' => $ord1,
                'order_number' => 'ORD-ABC12345',
                'company_id' => $c1,
                'subtotal' => 50000,
                'delivery_fee' => 2000,
                'total' => 52000,
                'currency' => 'XOF',
                'status' => 'delivered',
                'payment_method' => 'mobile_money',
                'payment_status' => 'paid',
                'delivery_address' => json_encode([
                    'fullName' => 'Amadou Ouedraogo',
                    'phone' => '+226 70 00 00 01',
                    'street' => 'Avenue de la Nation',
                    'city' => 'Ouagadougou',
                    'country' => 'Burkina Faso',
                ]),
                'created_at' => $now->copy()->subWeeks(3),
                'updated_at' => $now->copy()->subWeeks(1),
            ],
            [
                'id' => $ord2,
                'order_number' => 'ORD-DEF67890',
                'company_id' => $c2,
                'subtotal' => 200000,
                'delivery_fee' => 2000,
                'total' => 202000,
                'currency' => 'XOF',
                'status' => 'processing',
                'payment_method' => 'bank_card',
                'payment_status' => 'paid',
                'delivery_address' => json_encode([
                    'fullName' => 'Fatima Sawadogo',
                    'phone' => '+226 70 00 00 02',
                    'street' => 'Avenue Kwame Nkrumah',
                    'city' => 'Ouagadougou',
                    'country' => 'Burkina Faso',
                ]),
                'created_at' => $now->copy()->subWeeks(1),
                'updated_at' => $now->copy()->subDays(3),
            ],
            [
                'id' => $ord3,
                'order_number' => 'ORD-GHI13579',
                'company_id' => $c1,
                'subtotal' => 500000,
                'delivery_fee' => 2000,
                'total' => 502000,
                'currency' => 'XOF',
                'status' => 'pending',
                'payment_method' => 'mobile_money',
                'payment_status' => 'pending',
                'delivery_address' => json_encode([
                    'fullName' => 'Ibrahim Kabore',
                    'phone' => '+226 70 00 00 03',
                    'street' => 'Rue de la Chance',
                    'city' => 'Ouagadougou',
                    'country' => 'Burkina Faso',
                ]),
                'created_at' => $now->copy()->subDays(2),
                'updated_at' => $now->copy()->subDays(2),
            ],
        ];

        DB::table('orders')->insert($orders);

        $orderItems = [
            ['id' => 'cccccccc-cccc-cccc-cccc-ccccccccc001', 'order_id' => $ord1, 'product_id' => $p1, 'product_name' => 'Carte RFID Standard', 'quantity' => 10, 'unit_price' => 5000, 'total_price' => 50000, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 'cccccccc-cccc-cccc-cccc-ccccccccc002', 'order_id' => $ord2, 'product_id' => $p2, 'product_name' => 'Carte RFID Personnalisee', 'quantity' => 25, 'unit_price' => 8000, 'total_price' => 200000, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 'cccccccc-cccc-cccc-cccc-ccccccccc003', 'order_id' => $ord3, 'product_id' => $p3, 'product_name' => 'Pack Entreprise (100 cartes + lecteur)', 'quantity' => 1, 'unit_price' => 500000, 'total_price' => 500000, 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('order_items')->insert($orderItems);
    }
}
