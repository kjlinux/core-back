<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds company_id directly to feelback_alerts to avoid the slow whereHas('site') subquery
 * that is currently used for company scoping. All sibling tables (feelback_devices,
 * feelback_entries) carry company_id directly; this brings alerts in line.
 *
 * The column is nullable so existing rows are not invalidated; a backfill via
 * the site relationship is possible but left to a seeder/data-migration if needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feelback_alerts', function (Blueprint $table) {
            $table->foreignUuid('company_id')
                ->nullable()
                ->after('id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('feelback_alerts', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropIndex(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
