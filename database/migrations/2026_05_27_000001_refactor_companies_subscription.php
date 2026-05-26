<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE companies DROP CONSTRAINT IF EXISTS companies_subscription_check');
        DB::statement('ALTER TABLE companies ALTER COLUMN subscription DROP DEFAULT');
        DB::statement('ALTER TABLE companies ALTER COLUMN subscription TYPE varchar(32)');
        DB::statement("UPDATE companies SET subscription = CASE WHEN subscription IN ('premium','enterprise') THEN 'premium' ELSE 'freemium' END");
        DB::statement("ALTER TABLE companies ALTER COLUMN subscription SET DEFAULT 'freemium'");
        DB::statement("ALTER TABLE companies ADD CONSTRAINT companies_subscription_check CHECK (subscription IN ('freemium','garantie','premium'))");

        Schema::table('companies', function (Blueprint $table) {
            $table->timestamp('subscription_starts_at')->nullable();
            $table->timestamp('subscription_expires_at')->nullable();
            $table->boolean('subscription_next_period_paid')->default(false);
            $table->timestamp('subscription_next_expires_at')->nullable();
            $table->timestamp('warranty_starts_at')->nullable();
            $table->timestamp('warranty_ends_at')->nullable();
            $table->boolean('is_test')->default(false);

            $table->index('subscription_expires_at');
            $table->index('is_test');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['subscription_expires_at']);
            $table->dropIndex(['is_test']);
            $table->dropColumn([
                'subscription_starts_at',
                'subscription_expires_at',
                'subscription_next_period_paid',
                'subscription_next_expires_at',
                'warranty_starts_at',
                'warranty_ends_at',
                'is_test',
            ]);
        });

        DB::statement('ALTER TABLE companies DROP CONSTRAINT IF EXISTS companies_subscription_check');
        DB::statement('ALTER TABLE companies ALTER COLUMN subscription DROP DEFAULT');
        DB::statement("UPDATE companies SET subscription = CASE WHEN subscription = 'freemium' THEN 'basic' WHEN subscription = 'garantie' THEN 'premium' ELSE subscription END");
        DB::statement("ALTER TABLE companies ALTER COLUMN subscription SET DEFAULT 'basic'");
        DB::statement("ALTER TABLE companies ADD CONSTRAINT companies_subscription_check CHECK (subscription IN ('basic','premium','enterprise'))");
    }
};
