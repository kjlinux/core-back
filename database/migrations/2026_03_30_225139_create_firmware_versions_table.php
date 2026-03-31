<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firmware_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('version', 20);
            $table->enum('device_kind', ['rfid', 'biometric']);
            $table->text('description')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->boolean('is_auto_update')->default(false);
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['version', 'device_kind']);
            $table->index('device_kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firmware_versions');
    }
};
