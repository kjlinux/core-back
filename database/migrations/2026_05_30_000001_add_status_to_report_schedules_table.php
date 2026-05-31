<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suivi du résultat du dernier envoi planifié : statut + message d'erreur,
 * exposés dans le tableau des planifications pour rendre visibles les échecs
 * silencieux (le job loggue l'erreur mais ne la remontait pas à l'UI).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_schedules', function (Blueprint $table) {
            $table->string('last_status', 10)->nullable()->after('last_sent_at'); // success | failed
            $table->text('last_error')->nullable()->after('last_status');
        });
    }

    public function down(): void
    {
        Schema::table('report_schedules', function (Blueprint $table) {
            $table->dropColumn(['last_status', 'last_error']);
        });
    }
};
