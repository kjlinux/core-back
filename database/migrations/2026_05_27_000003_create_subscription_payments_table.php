<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('from_plan', 32);
            $table->string('to_plan', 32);
            $table->integer('amount_xof');
            $table->boolean('is_prorata')->default(false);
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->string('payment_method', 32)->nullable();
            $table->string('payment_status', 16)->default('pending');
            $table->string('intouch_token')->nullable()->index();
            $table->json('intouch_response')->nullable();
            $table->unsignedBigInteger('triggered_by_user_id')->nullable();
            $table->boolean('triggered_by_superadmin')->default(false);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('triggered_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['company_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
