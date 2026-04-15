<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('technicien_activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(\Illuminate\Support\Facades\DB::raw('gen_random_uuid()'));
            $table->foreignId('technicien_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('action');        // ex: 'create', 'update', 'delete', 'assign', 'sync'
            $table->string('resource_type'); // ex: 'site', 'employee', 'card', 'rfid_device'
            $table->uuid('resource_id')->nullable();
            $table->string('resource_label')->nullable(); // nom lisible de la ressource
            $table->json('metadata')->nullable(); // infos supplementaires
            $table->timestamps();

            $table->index(['technicien_id', 'company_id']);
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technicien_activity_logs');
    }
};
