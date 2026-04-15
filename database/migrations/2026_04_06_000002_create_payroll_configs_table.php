<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->unique()->constrained('companies')->cascadeOnDelete();
            $table->string('default_payment_mode')->default('monthly'); // monthly|hourly|daily|weekly|forfait
            $table->unsignedTinyInteger('standard_daily_hours')->default(8);
            $table->unsignedTinyInteger('working_days_per_month')->default(26);
            $table->unsignedTinyInteger('payment_day')->default(28); // jour du mois
            $table->boolean('lateness_deduction_enabled')->default(true);
            $table->boolean('overtime_enabled')->default(false);
            $table->decimal('overtime_rate', 4, 2)->default(1.25); // taux multiplicateur heures sup
            $table->timestamps();

            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_configs');
    }
};
