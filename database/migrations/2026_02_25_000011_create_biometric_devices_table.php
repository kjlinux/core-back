<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('serial_number')->unique();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_online')->default(false);
            $table->timestamp('last_sync_at')->nullable();
            $table->string('firmware_version')->nullable();
            $table->integer('enrolled_count')->default(0);
            $table->string('mqtt_topic')->nullable();
            $table->timestamps();

            $table->index('serial_number');
            $table->index('is_online');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_devices');
    }
};
