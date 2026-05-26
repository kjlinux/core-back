<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an optional name column to feelback_devices.
 * BiometricDevice and RfidDevice both have a name; FeelbackDevice was inconsistently missing one.
 * FeelbackReportController already references $device->name (falls back to serial_number when null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feelback_devices', function (Blueprint $table) {
            $table->string('name')->nullable()->after('serial_number');
        });
    }

    public function down(): void
    {
        Schema::table('feelback_devices', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }
};
