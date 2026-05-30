<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Contrainte CHECK specifique a PostgreSQL ; ignoree sur sqlite (tests).
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_payment_method_check');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_method_check CHECK (payment_method IN ('ligdicash','intouch_mobile_money','intouch_card','manual'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_payment_method_check');
        // Remapper les lignes 'ligdicash' avant de retablir la contrainte etroite :
        // Postgres valide ADD CONSTRAINT contre les lignes existantes et le rollback
        // echouerait sinon (cf. pattern de 2026_05_27_000005).
        DB::statement("UPDATE orders SET payment_method = 'manual' WHERE payment_method = 'ligdicash'");
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_payment_method_check CHECK (payment_method IN ('intouch_mobile_money','intouch_card','manual'))");
    }
};
