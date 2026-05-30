<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->string('expected_shift')->nullable()->after('status');
            $table->json('segments')->nullable()->after('expected_shift');
            $table->boolean('is_on_leave')->default(false)->after('segments');
        });

        // Contraintes CHECK specifiques a PostgreSQL ; ignorees sur sqlite (tests).
        if (DB::getDriverName() === 'pgsql') {
            // Statuts etendus : partial, on_leave
            DB::statement('ALTER TABLE attendance_records DROP CONSTRAINT IF EXISTS attendance_records_status_check');
            DB::statement("ALTER TABLE attendance_records ADD CONSTRAINT attendance_records_status_check CHECK (status IN ('present','absent','late','left_early','partial','on_leave'))");

            // Source etendue : qrcode
            DB::statement('ALTER TABLE attendance_records DROP CONSTRAINT IF EXISTS attendance_records_source_check');
            DB::statement("ALTER TABLE attendance_records ADD CONSTRAINT attendance_records_source_check CHECK (source IN ('rfid','biometric','qrcode'))");
        }
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['expected_shift', 'segments', 'is_on_leave']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE attendance_records DROP CONSTRAINT IF EXISTS attendance_records_status_check');
            DB::statement("ALTER TABLE attendance_records ADD CONSTRAINT attendance_records_status_check CHECK (status IN ('present','absent','late','left_early'))");

            DB::statement('ALTER TABLE attendance_records DROP CONSTRAINT IF EXISTS attendance_records_source_check');
            DB::statement("ALTER TABLE attendance_records ADD CONSTRAINT attendance_records_source_check CHECK (source IN ('rfid','biometric'))");
        }
    }
};
