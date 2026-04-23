<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Supprime les enrollments failed avec template_hash vide (occupent
        // une ligne dans l'index sans valeur utile).
        DB::table('fingerprint_enrollments')
            ->where('status', 'failed')
            ->where(function ($q) {
                $q->whereNull('template_hash')->orWhere('template_hash', '');
            })
            ->delete();

        // Suffixer les template_hash vides restants (pending / enrolled) pour
        // eviter une collision sur (device_id, '') au moment de poser l'index.
        $emptyHashRows = DB::table('fingerprint_enrollments')
            ->where(function ($q) {
                $q->whereNull('template_hash')->orWhere('template_hash', '');
            })
            ->get();

        foreach ($emptyHashRows as $row) {
            DB::table('fingerprint_enrollments')
                ->where('id', $row->id)
                ->update(['template_hash' => 'LEGACY_'.substr((string) $row->id, 0, 12)]);
        }

        // Desamorce les doublons residuels : pour chaque (device_id, template_hash)
        // dupique on garde le plus recent et on suffixe les autres pour preserver
        // l'historique sans casser l'index.
        $duplicates = DB::table('fingerprint_enrollments')
            ->selectRaw('device_id, template_hash')
            ->whereNotNull('template_hash')
            ->where('template_hash', '!=', '')
            ->groupBy('device_id', 'template_hash')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            $rows = DB::table('fingerprint_enrollments')
                ->where('device_id', $dup->device_id)
                ->where('template_hash', $dup->template_hash)
                ->orderByDesc('created_at')
                ->get();

            $rows->shift();

            foreach ($rows as $i => $row) {
                DB::table('fingerprint_enrollments')
                    ->where('id', $row->id)
                    ->update([
                        'status' => 'failed',
                        'template_hash' => $row->template_hash.'_DUP'.($i + 1).'_'.substr((string) $row->id, 0, 8),
                    ]);
            }
        }

        Schema::table('fingerprint_enrollments', function (Blueprint $table) {
            $table->unique(['device_id', 'template_hash'], 'fp_enroll_device_tpl_unique');
        });

        // Re-synchronise employees.biometric_enrolled : un employe sans aucun
        // enrollment 'enrolled' ne doit pas apparaitre comme enrole dans l'UI.
        if (Schema::hasTable('employees') && Schema::hasColumn('employees', 'biometric_enrolled')) {
            $stillEnrolledIds = DB::table('fingerprint_enrollments')
                ->where('status', 'enrolled')
                ->pluck('employee_id')
                ->unique()
                ->all();

            DB::table('employees')
                ->where('biometric_enrolled', true)
                ->when(! empty($stillEnrolledIds), fn ($q) => $q->whereNotIn('id', $stillEnrolledIds))
                ->update(['biometric_enrolled' => false]);
        }
    }

    public function down(): void
    {
        Schema::table('fingerprint_enrollments', function (Blueprint $table) {
            $table->dropUnique('fp_enroll_device_tpl_unique');
        });
    }
};
