<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['rfid_devices', 'biometric_devices', 'feelback_devices', 'qr_codes'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'is_witness')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->boolean('is_witness')->default(false)->index();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['rfid_devices', 'biometric_devices', 'feelback_devices', 'qr_codes'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'is_witness')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('is_witness');
                });
            }
        }
    }
};
