<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fingerprint_enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUuid('device_id')->constrained('biometric_devices')->cascadeOnDelete();
            $table->enum('status', ['pending', 'enrolled', 'failed'])->default('pending');
            $table->timestamp('enrolled_at')->nullable();
            $table->string('template_hash');
            $table->timestamps();

            $table->index('employee_id');
            $table->index('template_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fingerprint_enrollments');
    }
};
