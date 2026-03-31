<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('firmware_versions', function (Blueprint $table) {
            $table->boolean('is_published')->default(false)->after('is_auto_update');
            $table->timestamp('published_at')->nullable()->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('firmware_versions', function (Blueprint $table) {
            $table->dropColumn(['is_published', 'published_at']);
        });
    }
};
