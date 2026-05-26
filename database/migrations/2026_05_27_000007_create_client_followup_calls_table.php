<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_followup_calls', function (Blueprint $table) {
            $table->id();
            $table->uuid('company_id');
            $table->uuid('installation_sheet_id')->nullable();
            $table->string('call_type', 8);
            $table->timestamp('scheduled_at');
            $table->timestamp('called_at')->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('result', 16)->nullable();
            $table->decimal('usage_rate', 5, 2)->nullable();
            $table->unsignedTinyInteger('satisfaction_score')->nullable();
            $table->text('notes')->nullable();
            $table->json('actions')->nullable();
            $table->unsignedBigInteger('assigned_to_user_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('installation_sheet_id')->references('id')->on('installation_sheets')->nullOnDelete();
            $table->foreign('assigned_to_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'scheduled_at']);
            $table->index(['company_id', 'call_type']);
        });

        DB::statement("ALTER TABLE client_followup_calls ADD CONSTRAINT cfc_call_type_check CHECK (call_type IN ('j2','j7','j30'))");
        DB::statement("ALTER TABLE client_followup_calls ADD CONSTRAINT cfc_status_check CHECK (status IN ('pending','done','skipped','escalated'))");
        DB::statement("ALTER TABLE client_followup_calls ADD CONSTRAINT cfc_result_check CHECK (result IS NULL OR result IN ('ok','partial','problem'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('client_followup_calls');
    }
};
