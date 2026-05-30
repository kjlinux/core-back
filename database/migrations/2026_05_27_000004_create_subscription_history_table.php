<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_history', function (Blueprint $table) {
            $table->id();
            $table->uuid('company_id');
            $table->string('event', 32);
            $table->string('from_plan', 32)->nullable();
            $table->string('to_plan', 32)->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->uuid('payment_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('payment_id')->references('id')->on('subscription_payments')->nullOnDelete();
            $table->index(['company_id', 'created_at']);
        });

        // Contrainte CHECK specifique a PostgreSQL ; ignoree sur sqlite (tests).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE subscription_history ADD CONSTRAINT subscription_history_event_check CHECK (event IN ('subscribed','upgraded','downgraded','renewed','prepaid','rolled_over','expired','admin_changed'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_history');
    }
};
