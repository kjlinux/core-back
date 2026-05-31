<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_configs', function (Blueprint $table) {
            // Jours travaillés par semaine — sert au mode de paie hebdomadaire
            // (brut = taux hebdomadaire × jours_travaillés / working_days_per_week).
            $table->unsignedTinyInteger('working_days_per_week')->default(5)->after('working_days_per_month');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_configs', function (Blueprint $table) {
            $table->dropColumn('working_days_per_week');
        });
    }
};
