<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Les colonnes created_by_user_id / resolved_by_user_id avaient ete creees en uuid,
     * alors que users.id est un bigint auto-incremente. Toute insertion d'une plainte
     * echouait sous PostgreSQL (« syntaxe en entree invalide pour le type uuid »).
     * La table etant vide, on recree simplement les colonnes avec le bon type.
     */
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropColumn(['created_by_user_id', 'resolved_by_user_id']);
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by_user_id');
            $table->unsignedBigInteger('resolved_by_user_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropColumn(['created_by_user_id', 'resolved_by_user_id']);
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->uuid('created_by_user_id');
            $table->uuid('resolved_by_user_id')->nullable();
        });
    }
};
