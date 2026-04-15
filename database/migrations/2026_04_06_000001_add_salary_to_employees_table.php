<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('payment_mode')->nullable()->after('hire_date'); // monthly|hourly|daily|weekly|forfait
            $table->unsignedBigInteger('base_salary')->nullable()->after('payment_mode'); // en FCFA
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['payment_mode', 'base_salary']);
        });
    }
};
