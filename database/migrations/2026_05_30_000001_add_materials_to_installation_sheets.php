<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une fiche d'installation peut désormais comporter plusieurs matériels.
 * Les champs jusqu'ici uniques (solution, n° série, réseau...) sont déplacés
 * dans une colonne JSON `materials` (tableau d'objets), à l'image de `checklist`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE installation_sheets DROP CONSTRAINT IF EXISTS installation_sheets_solution_check');
        }

        Schema::table('installation_sheets', function (Blueprint $table) {
            $table->json('materials')->nullable()->after('site_address');
            $table->dropIndex(['serial_number']);
            $table->dropColumn([
                'solution', 'serial_number', 'quantity', 'firmware_version',
                'wifi_ssid', 'static_ip', 'remote_access',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('installation_sheets', function (Blueprint $table) {
            $table->string('solution', 32)->nullable();
            $table->string('serial_number')->nullable();
            $table->string('quantity')->nullable();
            $table->string('firmware_version')->nullable();
            $table->string('wifi_ssid')->nullable();
            $table->string('static_ip', 64)->nullable();
            $table->string('remote_access', 64)->nullable();
            $table->index('serial_number');
            $table->dropColumn('materials');
        });
    }
};
