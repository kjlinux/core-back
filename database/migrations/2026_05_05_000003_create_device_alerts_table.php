<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->nullable()->index();
            $table->uuid('site_id')->nullable()->index();
            $table->uuid('device_id')->nullable();
            $table->string('device_kind', 32);
            $table->string('type', 64);
            $table->string('severity', 16)->default('medium');
            $table->string('title');
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->string('status', 16)->default('open');
            $table->uuid('acknowledged_by')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'severity']);
            $table->index(['device_kind', 'device_id']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_alerts');
    }
};
