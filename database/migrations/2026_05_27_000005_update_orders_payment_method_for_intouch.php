<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_payment_method_check');
        DB::statement('ALTER TABLE orders ALTER COLUMN payment_method TYPE varchar(32)');
        DB::statement("UPDATE orders SET payment_method = 'intouch_mobile_money' WHERE payment_method = 'mobile_money'");
        DB::statement("UPDATE orders SET payment_method = 'intouch_card' WHERE payment_method = 'bank_card'");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_method_check CHECK (payment_method IN ('intouch_mobile_money','intouch_card','manual'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_payment_method_check');
        DB::statement("UPDATE orders SET payment_method = 'mobile_money' WHERE payment_method = 'intouch_mobile_money'");
        DB::statement("UPDATE orders SET payment_method = 'bank_card' WHERE payment_method = 'intouch_card'");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_method_check CHECK (payment_method IN ('mobile_money','bank_card','manual'))");
    }
};
