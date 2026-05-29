<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index composites pour accélérer les agrégations des rapports / dashboards.
 * Voir audit des rapports — § PR Perf.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            // Dashboard quotidien : WHERE date = ? AND status IN (...)
            $table->index(['date', 'status'], 'idx_attendance_date_status');
            // Rapports mensuels par employé : WHERE employee_id = ? AND date BETWEEN ... AND status = ?
            $table->index(['employee_id', 'date', 'status'], 'idx_attendance_emp_date_status');
        });

        Schema::table('feelback_entries', function (Blueprint $table) {
            // Rapports Feelback : WHERE site_id IN (...) AND created_at BETWEEN ... GROUP BY level
            $table->index(['site_id', 'created_at', 'level'], 'idx_feelback_site_date_level');
        });

        Schema::table('orders', function (Blueprint $table) {
            // Rapports ventes : WHERE company_id = ? AND payment_status = 'paid' AND created_at >= ?
            $table->index(['company_id', 'payment_status', 'created_at'], 'idx_orders_company_pay_created');
        });

        Schema::table('technicien_activity_logs', function (Blueprint $table) {
            // Synthèse par technicien : WHERE technicien_id IN (...) AND company_id = ? GROUP BY resource_type, action
            $table->index(
                ['company_id', 'technicien_id', 'resource_type', 'action'],
                'idx_tal_company_tech_resource_action'
            );
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropIndex('idx_attendance_date_status');
            $table->dropIndex('idx_attendance_emp_date_status');
        });

        Schema::table('feelback_entries', function (Blueprint $table) {
            $table->dropIndex('idx_feelback_site_date_level');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_company_pay_created');
        });

        Schema::table('technicien_activity_logs', function (Blueprint $table) {
            $table->dropIndex('idx_tal_company_tech_resource_action');
        });
    }
};
