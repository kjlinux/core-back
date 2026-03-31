<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Redesign du système de pointage QR :
 * - Un QR Code par site (affiché physiquement à l'entrée)
 * - Identification de l'employé par device_fingerprint (UUID persistant sur son téléphone)
 * - Vérification GPS : l'employé doit être dans le rayon du site
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Ajouter coordonnées GPS + rayon aux sites
        Schema::table('sites', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedInteger('geofence_radius')->default(100)->after('longitude'); // mètres
        });

        // 2. Ajouter device_fingerprint + device_info aux employés
        Schema::table('employees', function (Blueprint $table) {
            $table->string('device_fingerprint', 64)->nullable()->unique()->after('biometric_enrolled');
            $table->string('device_info')->nullable()->after('device_fingerprint'); // ex: "iPhone 14 / Safari"
            $table->timestamp('device_enrolled_at')->nullable()->after('device_info');
        });

        // 3. Refactoriser qr_codes : un QR par site (plus par employé)
        // On vide d'abord les enregistrements dépendants (cascade)
        Schema::table('qr_attendance_records', function (Blueprint $table) {
            $table->dropForeign(['qr_code_id']);
        });
        Schema::table('qr_codes', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropColumn('employee_id');
            $table->foreignUuid('site_id')->nullable()->after('company_id')->constrained('sites')->nullOnDelete();
            $table->string('label')->nullable()->after('site_id'); // ex: "Entrée principale"
        });
        Schema::table('qr_attendance_records', function (Blueprint $table) {
            $table->foreign('qr_code_id')->references('id')->on('qr_codes')->cascadeOnDelete();
        });

        // 4. Enrichir qr_attendance_records : GPS + device
        Schema::table('qr_attendance_records', function (Blueprint $table) {
            $table->string('device_fingerprint', 64)->nullable()->after('notes');
            $table->decimal('scan_latitude', 10, 7)->nullable()->after('device_fingerprint');
            $table->decimal('scan_longitude', 10, 7)->nullable()->after('scan_latitude');
            $table->boolean('gps_verified')->default(false)->after('scan_longitude');
            $table->unsignedInteger('distance_meters')->nullable()->after('gps_verified'); // distance calculée au site
        });
    }

    public function down(): void
    {
        Schema::table('qr_attendance_records', function (Blueprint $table) {
            $table->dropColumn(['device_fingerprint', 'scan_latitude', 'scan_longitude', 'gps_verified', 'distance_meters']);
        });

        Schema::table('qr_codes', function (Blueprint $table) {
            $table->dropForeign(['site_id']);
            $table->dropColumn(['site_id', 'label']);
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['device_fingerprint', 'device_info', 'device_enrolled_at']);
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'geofence_radius']);
        });
    }
};
