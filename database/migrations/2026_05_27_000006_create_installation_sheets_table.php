<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installation_sheets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->unsignedBigInteger('technician_user_id')->nullable();
            $table->string('client_contact_name')->nullable();
            $table->string('client_contact_role')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('client_email')->nullable();
            $table->string('site_address')->nullable();
            $table->string('solution', 32);
            $table->string('serial_number');
            $table->string('quantity')->nullable();
            $table->string('firmware_version')->nullable();
            $table->string('wifi_ssid')->nullable();
            $table->string('static_ip', 64)->nullable();
            $table->string('remote_access', 64)->nullable();
            $table->json('checklist');
            $table->unsignedTinyInteger('training_rating')->nullable();
            $table->text('observations')->nullable();
            $table->string('client_signature_path')->nullable();
            $table->string('technician_signature_path')->nullable();
            $table->timestamp('installed_at');
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('technician_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['company_id', 'installed_at']);
            $table->index('serial_number');
        });

        // Contrainte CHECK specifique a PostgreSQL ; ignoree sur sqlite (tests).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE installation_sheets ADD CONSTRAINT installation_sheets_solution_check CHECK (solution IN ('presenseRH_rfid','presenseRH_fp','presenseRH_qr','feelback','smartcard','kuilinga'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('installation_sheets');
    }
};
