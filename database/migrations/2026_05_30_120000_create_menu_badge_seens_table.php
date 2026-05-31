<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_badge_seens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('badge_key');
            $table->string('scope')->default('all');
            $table->unsignedInteger('seen_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'badge_key', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_badge_seens');
    }
};
