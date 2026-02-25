<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feelback_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('serial_number')->unique();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('site_id')->constrained('sites')->cascadeOnDelete();
            $table->boolean('is_online')->default(false);
            $table->integer('battery_level')->default(100);
            $table->timestamp('last_ping_at')->nullable();
            $table->string('assigned_agent')->nullable();
            $table->string('mqtt_topic')->nullable();
            $table->timestamps();

            $table->index('serial_number');
            $table->index('is_online');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feelback_devices');
    }
};
