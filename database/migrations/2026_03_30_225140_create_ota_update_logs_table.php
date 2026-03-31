<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ota_update_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('device_id');
            $table->enum('device_kind', ['rfid', 'biometric']);
            $table->foreignUuid('firmware_version_id')->constrained('firmware_versions')->cascadeOnDelete();
            $table->enum('status', ['pending', 'in_progress', 'success', 'failed', 'skipped'])->default('pending');
            $table->enum('triggered_by', ['manual', 'auto'])->default('manual');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('device_id');
            $table->index('device_kind');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ota_update_logs');
    }
};
