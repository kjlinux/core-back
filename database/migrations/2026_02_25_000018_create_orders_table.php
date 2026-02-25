<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('order_number')->unique();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->integer('subtotal');
            $table->integer('delivery_fee')->default(0);
            $table->integer('total');
            $table->string('currency')->default('XOF');
            $table->enum('status', ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'])->default('pending');
            $table->enum('payment_method', ['mobile_money', 'bank_card', 'manual'])->default('mobile_money');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->json('delivery_address');
            $table->string('invoice_url')->nullable();
            $table->string('payment_token')->nullable();
            $table->timestamps();

            $table->index('company_id');
            $table->index('status');
            $table->index('payment_status');
            $table->index('order_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
