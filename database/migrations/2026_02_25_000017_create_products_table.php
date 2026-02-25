<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description');
            $table->enum('category', ['standard_card', 'custom_card', 'enterprise_pack']);
            $table->integer('price');
            $table->string('currency')->default('XOF');
            $table->integer('stock_quantity')->default(0);
            $table->json('images')->nullable();
            $table->boolean('customizable')->default(false);
            $table->integer('min_quantity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('category');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
