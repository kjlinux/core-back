<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Garantie materielle a renouvellement automatique (12 mois) tant qu'un
            // technicien ou super_admin ne l'arrete pas. Conditionne l'acces aux plans garantie/premium.
            $table->boolean('warranty_auto_renew')->default(false)->after('warranty_ends_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('warranty_auto_renew');
        });
    }
};
