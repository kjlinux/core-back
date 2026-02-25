<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfid_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('uid')->unique();
            $table->foreignUuid('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->enum('status', ['active', 'inactive', 'blocked', 'lost'])->default('inactive');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->string('block_reason')->nullable();
            $table->timestamps();

            $table->index('uid');
            $table->index('status');
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfid_cards');
    }
};
