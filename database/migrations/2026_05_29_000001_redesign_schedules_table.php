<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->integer('default_late_tolerance')->default(0)->after('type');
            $table->json('days')->nullable()->after('default_late_tolerance');
        });

        // Anciennes colonnes plates : rendues nullables (le nouveau modele utilise days)
        DB::statement('ALTER TABLE schedules ALTER COLUMN start_time DROP NOT NULL');
        DB::statement('ALTER TABLE schedules ALTER COLUMN end_time DROP NOT NULL');
        DB::statement('ALTER TABLE schedules ALTER COLUMN work_days DROP NOT NULL');

        // Elargir le type pour inclure day / night
        DB::statement('ALTER TABLE schedules DROP CONSTRAINT IF EXISTS schedules_type_check');
        DB::statement("ALTER TABLE schedules ADD CONSTRAINT schedules_type_check CHECK (type IN ('standard','custom','day','night'))");
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['default_late_tolerance', 'days']);
        });

        DB::statement('ALTER TABLE schedules DROP CONSTRAINT IF EXISTS schedules_type_check');
        DB::statement("ALTER TABLE schedules ADD CONSTRAINT schedules_type_check CHECK (type IN ('standard','custom'))");
    }
};
