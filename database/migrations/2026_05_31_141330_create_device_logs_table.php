<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('device_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->nullable()->index();
            $table->uuid('site_id')->nullable();
            $table->uuid('device_id')->nullable()->index();
            $table->enum('device_kind', ['rfid', 'biometric'])->default('rfid');
            $table->string('serial_number')->nullable()->index();
            $table->enum('level', ['debug', 'info', 'warning', 'error', 'critical'])->default('info');
            $table->text('message');
            $table->string('firmware_version')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index('device_kind');
            $table->index('level');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_logs');
    }
};
