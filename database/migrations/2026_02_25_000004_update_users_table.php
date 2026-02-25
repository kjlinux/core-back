<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->after('name')->default('');
            $table->string('last_name')->after('first_name')->default('');
            $table->string('phone')->nullable()->after('email');
            $table->enum('role', ['super_admin', 'admin_enterprise', 'manager'])->default('manager')->after('phone');
            $table->uuid('company_id')->nullable()->after('role');
            $table->string('avatar')->nullable()->after('company_id');
            $table->boolean('is_active')->default(true)->after('avatar');

            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $table->index('role');
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropColumn(['first_name', 'last_name', 'phone', 'role', 'company_id', 'avatar', 'is_active']);
        });
    }
};
