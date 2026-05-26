<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds missing company_id / site_id indexes on device tables and
 * device_id index on fingerprint_enrollments.
 * Foreign keys without a matching index cause full-table scans on lookups/joins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->index('company_id');
            $table->index('site_id');
        });

        Schema::table('feelback_devices', function (Blueprint $table) {
            $table->index('company_id');
            $table->index('site_id');
        });

        Schema::table('rfid_devices', function (Blueprint $table) {
            $table->index('company_id');
            $table->index('site_id');
        });

        Schema::table('fingerprint_enrollments', function (Blueprint $table) {
            $table->index('device_id');
        });
    }

    public function down(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->dropIndex(['company_id']);
            $table->dropIndex(['site_id']);
        });

        Schema::table('feelback_devices', function (Blueprint $table) {
            $table->dropIndex(['company_id']);
            $table->dropIndex(['site_id']);
        });

        Schema::table('rfid_devices', function (Blueprint $table) {
            $table->dropIndex(['company_id']);
            $table->dropIndex(['site_id']);
        });

        Schema::table('fingerprint_enrollments', function (Blueprint $table) {
            $table->dropIndex(['device_id']);
        });
    }
};
