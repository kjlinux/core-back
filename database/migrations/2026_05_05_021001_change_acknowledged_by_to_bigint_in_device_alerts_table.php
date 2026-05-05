<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_alerts', function (Blueprint $table) {
            $table->dropColumn('acknowledged_by');
        });

        Schema::table('device_alerts', function (Blueprint $table) {
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete()->after('acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::table('device_alerts', function (Blueprint $table) {
            $table->dropColumn('acknowledged_by');
        });

        Schema::table('device_alerts', function (Blueprint $table) {
            $table->uuid('acknowledged_by')->nullable()->after('acknowledged_at');
        });
    }
};
