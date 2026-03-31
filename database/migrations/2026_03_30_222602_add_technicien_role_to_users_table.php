<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'admin_enterprise', 'manager', 'technicien') NOT NULL DEFAULT 'manager'");
    }

    public function down(): void
    {
        // Supprimer les utilisateurs technicien avant de réduire l'enum
        DB::statement("UPDATE users SET role = 'manager' WHERE role = 'technicien'");
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'admin_enterprise', 'manager') NOT NULL DEFAULT 'manager'");
    }
};
