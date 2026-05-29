<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Planification d'envoi automatique de rapports par email.
 * Le scheduler Laravel évalue les schedules dûs et dispatch un job par schedule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('report_type', 40);          // attendance | feelback | sales
            $table->string('format', 10)->default('pdf'); // pdf | csv
            $table->string('frequency', 10);             // daily | weekly | monthly
            $table->json('filters')->nullable();         // site_id, type, etc.
            $table->json('recipients');                  // liste d'emails
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'next_run_at']);
            $table->index(['company_id', 'report_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
