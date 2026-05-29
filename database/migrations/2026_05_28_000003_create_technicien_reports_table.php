<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot signé des rapports de mise en service générés par les techniciens.
 * La signature HMAC-SHA256 permet de vérifier l'intégrité du PDF a posteriori.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technicien_reports', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('company_name'); // figé au moment du rapport
            $table->string('technicien_name');
            $table->unsignedSmallInteger('global_score');
            $table->json('payload'); // sections + issues complets
            $table->char('payload_hash', 64); // SHA-256 hex du payload canonique
            $table->char('signature', 64);    // HMAC-SHA256(payload_hash, APP_KEY)
            $table->timestamp('signed_at');
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technicien_reports');
    }
};
