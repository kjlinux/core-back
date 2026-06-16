<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une fiche de maintenance peut etre rattachee a la fiche d'installation
 * d'origine, afin de pre-remplir les equipements deja poses chez le client.
 * Lien optionnel : une maintenance peut aussi etre saisie sans installation
 * de reference (materiel pose avant la mise en place du module).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_sheets', function (Blueprint $table) {
            $table->uuid('installation_sheet_id')->nullable()->after('company_id');
            $table->foreign('installation_sheet_id')->references('id')->on('installation_sheets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_sheets', function (Blueprint $table) {
            $table->dropForeign(['installation_sheet_id']);
            $table->dropColumn('installation_sheet_id');
        });
    }
};
