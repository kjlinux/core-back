<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fiche de maintenance : remplie par le technicien lors d'une intervention de
 * maintenance chez un client. Calquee sur la fiche d'installation (memes
 * coordonnees client, equipements en JSON, check-list, signatures), mais avec
 * des champs propres a la maintenance (type d'intervention, probleme signale,
 * etat des equipements, prochaine maintenance recommandee).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_sheets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->unsignedBigInteger('technician_user_id')->nullable();
            $table->string('client_contact_name')->nullable();
            $table->string('client_contact_role')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('client_email')->nullable();
            $table->string('site_address')->nullable();
            $table->string('maintenance_type', 32);
            $table->text('reported_issue')->nullable();
            $table->json('equipments')->nullable();
            $table->json('checklist');
            $table->boolean('resolved')->default(true);
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->unsignedTinyInteger('satisfaction_rating')->nullable();
            $table->date('next_maintenance_at')->nullable();
            $table->text('observations')->nullable();
            $table->string('client_signature_path')->nullable();
            $table->string('technician_signature_path')->nullable();
            $table->timestamp('maintained_at');
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('technician_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['company_id', 'maintained_at']);
        });

        // Contrainte CHECK specifique a PostgreSQL ; ignoree sur sqlite (tests).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE maintenance_sheets ADD CONSTRAINT maintenance_sheets_type_check CHECK (maintenance_type IN ('preventive','corrective','emergency'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_sheets');
    }
};
