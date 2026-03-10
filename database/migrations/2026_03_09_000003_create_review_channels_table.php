<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_channels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('review_config_id')->constrained('review_configs')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->index('review_config_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_channels');
    }
};
