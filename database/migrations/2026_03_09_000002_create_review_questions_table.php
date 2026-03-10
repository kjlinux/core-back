<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('review_config_id')->constrained('review_configs')->cascadeOnDelete();
            $table->string('text');
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->timestamps();

            $table->index('review_config_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_questions');
    }
};
