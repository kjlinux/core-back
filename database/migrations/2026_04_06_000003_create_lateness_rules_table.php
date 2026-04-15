<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lateness_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            // minutes de tolerance avant application de la penalite
            $table->unsignedSmallInteger('tolerance_minutes')->default(5);
            // nombre de minutes de retard cumule declenchant la penalite
            $table->unsignedSmallInteger('minutes_threshold');
            // valeur de la penalite (montant FCFA ou pourcentage)
            $table->decimal('penalty_value', 10, 2);
            // fixed = montant FCFA fixe, percentage = % du salaire journalier/horaire
            $table->string('penalty_type')->default('fixed');
            // occurrence = par retard constate, tranche = par tranche de minutes_threshold
            $table->string('apply_per')->default('occurrence');
            $table->timestamps();

            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lateness_rules');
    }
};
