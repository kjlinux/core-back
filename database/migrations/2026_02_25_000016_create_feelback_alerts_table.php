<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feelback_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('device_id')->constrained('feelback_devices')->cascadeOnDelete();
            $table->foreignUuid('site_id')->constrained('sites')->cascadeOnDelete();
            $table->enum('type', ['threshold_exceeded', 'device_offline', 'low_battery']);
            $table->string('message');
            $table->integer('threshold')->nullable();
            $table->integer('current_value')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index('is_read');
            $table->index('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feelback_alerts');
    }
};
