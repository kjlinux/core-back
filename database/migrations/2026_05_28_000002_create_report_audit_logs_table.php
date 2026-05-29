<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'audit des générations de rapports.
 * Trace : qui (user_id) a généré quel rapport, avec quels filtres, depuis quelle IP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('report_type', 80); // ex: 'attendance', 'feelback', 'sales', 'review'
            $table->string('route', 150);
            $table->json('filters')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['company_id', 'report_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_audit_logs');
    }
};
