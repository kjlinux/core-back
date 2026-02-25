<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feelback_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('device_id')->constrained('feelback_devices')->cascadeOnDelete();
            $table->enum('level', ['bon', 'neutre', 'mauvais']);
            $table->foreignUuid('site_id')->constrained('sites')->cascadeOnDelete();
            $table->string('agent_id')->nullable();
            $table->string('agent_name')->nullable();
            $table->timestamps();

            $table->index('level');
            $table->index('site_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feelback_entries');
    }
};
