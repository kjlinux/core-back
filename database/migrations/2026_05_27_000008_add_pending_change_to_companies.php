<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Plan vise apres expiration du cycle courant (downgrade differe).
            // null = aucun changement programme.
            $table->string('subscription_pending_change_to', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('subscription_pending_change_to');
        });
    }
};
