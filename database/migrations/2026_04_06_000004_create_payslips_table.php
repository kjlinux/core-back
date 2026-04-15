<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained('departments')->nullOnDelete();

            // Periode
            $table->string('period', 7);        // YYYY-MM
            $table->date('period_start');
            $table->date('period_end');

            // Remuneration
            $table->string('payment_mode');
            $table->unsignedBigInteger('base_salary');

            // Presence
            $table->unsignedSmallInteger('worked_days')->default(0);
            $table->decimal('worked_hours', 7, 2)->default(0);
            $table->unsignedSmallInteger('absent_days')->default(0);
            $table->unsignedInteger('total_lateness_minutes')->default(0);

            // Heures supplementaires
            $table->decimal('overtime_hours', 7, 2)->default(0);
            $table->unsignedBigInteger('overtime_amount')->default(0);

            // Deductions
            $table->unsignedBigInteger('lateness_deduction')->default(0);
            $table->unsignedBigInteger('absence_deduction')->default(0);

            // Lignes additionnelles (primes, autres deductions) stockees en JSON
            $table->json('lines')->nullable();

            // Totaux
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedBigInteger('net_amount');

            // Statut : draft | validated | paid
            $table->string('status')->default('draft');

            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index('company_id');
            $table->index('employee_id');
            $table->index(['company_id', 'period']);
            $table->unique(['employee_id', 'period']); // une fiche par employe par periode
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
