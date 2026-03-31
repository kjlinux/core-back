<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_attendance_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUuid('qr_code_id')->constrained('qr_codes')->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('date');
            $table->time('entry_time')->nullable();
            $table->time('exit_time')->nullable();
            $table->enum('status', ['present', 'absent', 'late', 'left_early'])->default('present');
            $table->timestamp('scanned_at')->useCurrent();
            $table->uuid('scanned_by_device_id')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('employee_id');
            $table->index('company_id');
            $table->index('date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_attendance_records');
    }
};
