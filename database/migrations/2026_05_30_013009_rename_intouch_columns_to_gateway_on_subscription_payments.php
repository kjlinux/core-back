<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->renameColumn('intouch_token', 'gateway_token');
            $table->renameColumn('intouch_response', 'gateway_response');
        });

        // renameColumn ne renomme pas l'index associe : on l'aligne sur la colonne.
        // ALTER INDEX ... RENAME est specifique a PostgreSQL.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER INDEX IF EXISTS subscription_payments_intouch_token_index RENAME TO subscription_payments_gateway_token_index');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER INDEX IF EXISTS subscription_payments_gateway_token_index RENAME TO subscription_payments_intouch_token_index');
        }

        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->renameColumn('gateway_token', 'intouch_token');
            $table->renameColumn('gateway_response', 'intouch_response');
        });
    }
};
