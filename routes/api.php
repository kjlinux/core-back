<?php

use App\Http\Controllers\Api\AdminOrderController;
use App\Http\Controllers\Api\AdminSalesReportController;
use App\Http\Controllers\Api\AdvancedAnalyticsController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceReportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BiometricAuditController;
use App\Http\Controllers\Api\BiometricDeviceController;
use App\Http\Controllers\Api\BiometricInconsistencyController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmployeeDeviceController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\EnrollSessionController;
use App\Http\Controllers\Api\FeelbackAlertController;
use App\Http\Controllers\Api\FeelbackDeviceController;
use App\Http\Controllers\Api\FeelbackEntryController;
use App\Http\Controllers\Api\FeelbackReportController;
use App\Http\Controllers\Api\FeelbackStatsController;
use App\Http\Controllers\Api\FirmwareController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Controllers\Api\MenuBadgeController;
use App\Http\Controllers\Api\MqttController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentCallbackController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PublicReviewController;
use App\Http\Controllers\Api\QrAttendanceController;
use App\Http\Controllers\Api\QrCodeController;
use App\Http\Controllers\Api\ResetPasswordController;
use App\Http\Controllers\Api\ReviewConfigController;
use App\Http\Controllers\Api\ReviewStatsController;
use App\Http\Controllers\Api\RfidDeviceController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\Support\HealthController as SupportHealthController;
use App\Http\Controllers\Api\Support\SupportController;
use App\Http\Controllers\Api\SupportTicketController;
use App\Http\Controllers\Api\TechnicienActivityController;
use App\Http\Controllers\Api\TechnicienReportController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Firmware version check — appelee par les capteurs ESP32 (sans auth)
Route::get('/firmware/version.json', [FirmwareController::class, 'latestVersion']);

// Verification publique d'un rapport technicien signe — accessible sans auth (QR code dans le PDF).
Route::get('/technicien-reports/{id}/verify', [TechnicienReportController::class, 'verify']);

// Public routes (sans auth)
Route::prefix('public')->group(function () {
    Route::get('/review/{token}', [PublicReviewController::class, 'show']);
    Route::post('/review/{token}/submit', [PublicReviewController::class, 'submit'])
        ->middleware('throttle:10,1');
});

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/auth/logout', [AuthController::class, 'logout']);
Route::post('/auth/forgot-password', ForgotPasswordController::class)->middleware('throttle:5,1');
Route::post('/auth/reset-password', ResetPasswordController::class)->middleware('throttle:5,1');
Route::post('/payment/callback', [PaymentCallbackController::class, 'handle']);
Route::post('/payment/intouch/callback', [PaymentCallbackController::class, 'intouch']);
Route::get('/subscriptions/plans', [\App\Http\Controllers\Api\SubscriptionController::class, 'plans']);

// Routes publiques QR — utilisées par les employés sans compte (téléphone mobile)
Route::post('/qr-attendance/scan', [QrAttendanceController::class, 'scan']);
Route::post('/employees/device/identify', [EmployeeDeviceController::class, 'identify']);
Route::post('/enroll-session/{token}/submit', [EnrollSessionController::class, 'submit']);

// Broadcasting auth (Reverb/Pusher private channels via Bearer token)
Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
    return \Illuminate\Support\Facades\Broadcast::auth($request);
})->middleware('auth:sanctum');

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {

    // =============================================
    // Auth (tous les roles)
    // =============================================
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [ProfileController::class, 'update']);
    Route::put('/auth/password', [ProfileController::class, 'changePassword']);
    Route::post('/auth/select-company', [AuthController::class, 'selectCompany']);

    // =============================================
    // Subscriptions (toute compagnie authentifiee)
    // =============================================
    Route::prefix('subscriptions')->group(function () {
        Route::get('/me', [\App\Http\Controllers\Api\SubscriptionController::class, 'me']);
        Route::get('/history', [\App\Http\Controllers\Api\SubscriptionController::class, 'history']);
        Route::get('/events', [\App\Http\Controllers\Api\SubscriptionController::class, 'events']);
        Route::get('/quote', [\App\Http\Controllers\Api\SubscriptionController::class, 'quote']);
        Route::post('/subscribe', [\App\Http\Controllers\Api\SubscriptionController::class, 'subscribe']);
        Route::post('/upgrade', [\App\Http\Controllers\Api\SubscriptionController::class, 'upgrade']);
        Route::post('/pay-next-period', [\App\Http\Controllers\Api\SubscriptionController::class, 'payNextPeriod']);
    });

    // Admin: gestion des abonnements de toutes les compagnies
    Route::middleware('role:super_admin')->prefix('admin/subscriptions')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\AdminSubscriptionController::class, 'index']);
        Route::get('/analytics', [\App\Http\Controllers\Api\AdminSubscriptionController::class, 'analytics']);
    });
    Route::middleware('role:super_admin')->patch('/admin/companies/{id}/subscription', [\App\Http\Controllers\Api\AdminSubscriptionController::class, 'update']);

    // =============================================
    // Fiches d'installation (technicien + super_admin)
    // =============================================
    Route::middleware('role:super_admin,technicien')->group(function () {
        Route::get('/installation-sheets', [\App\Http\Controllers\Api\InstallationSheetController::class, 'index']);
        Route::get('/installation-sheets/{id}/pdf', [\App\Http\Controllers\Api\InstallationSheetController::class, 'pdf']);
        Route::get('/installation-sheets/{id}', [\App\Http\Controllers\Api\InstallationSheetController::class, 'show']);
        Route::post('/installation-sheets', [\App\Http\Controllers\Api\InstallationSheetController::class, 'store']);
    });

    // =============================================
    // Fiches de maintenance (technicien + super_admin)
    // =============================================
    Route::middleware('role:super_admin,technicien')->group(function () {
        Route::get('/maintenance-sheets', [\App\Http\Controllers\Api\MaintenanceSheetController::class, 'index']);
        Route::get('/maintenance-sheets/{id}/pdf', [\App\Http\Controllers\Api\MaintenanceSheetController::class, 'pdf']);
        Route::get('/maintenance-sheets/{id}', [\App\Http\Controllers\Api\MaintenanceSheetController::class, 'show']);
        Route::post('/maintenance-sheets', [\App\Http\Controllers\Api\MaintenanceSheetController::class, 'store']);
    });

    // =============================================
    // CRM Followups J+2/J+7/J+30 — usage interne TANGA GROUP
    // (chargés de compte / commerciaux : super_admin + technicien)
    // Le client (admin_enterprise) ne doit PAS voir le suivi commercial le concernant.
    // =============================================
    Route::middleware('role:super_admin,technicien')->prefix('followups')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ClientFollowupController::class, 'index']);
        Route::get('/dashboard', [\App\Http\Controllers\Api\ClientFollowupController::class, 'dashboard']);
        Route::get('/{id}', [\App\Http\Controllers\Api\ClientFollowupController::class, 'show']);
        Route::patch('/{id}', [\App\Http\Controllers\Api\ClientFollowupController::class, 'update']);
        Route::post('/{id}/escalate', [\App\Http\Controllers\Api\ClientFollowupController::class, 'escalate']);
    });

    // =============================================
    // Super Admin + Technicien (setup/onboarding)
    // =============================================
    Route::middleware('role:super_admin,technicien')->group(function () {
        // Companies CUD
        Route::post('/companies', [CompanyController::class, 'store']);
        Route::patch('/companies/{id}/toggle-active', [CompanyController::class, 'toggleActive']);

        // Garantie materielle (deverrouille les plans garantie/premium pour l'entreprise)
        Route::post('/companies/{id}/warranty', [CompanyController::class, 'activateWarranty']);
        Route::delete('/companies/{id}/warranty', [CompanyController::class, 'stopWarranty']);

    });

    // Mise a jour entreprise : l'admin_enterprise peut modifier sa propre entreprise.
    // Le scope (entreprise propre) et le verrouillage du champ subscription sont geres
    // dans CompanyController::update().
    Route::middleware('role:super_admin,admin_enterprise,technicien')
        ->put('/companies/{id}', [CompanyController::class, 'update']);

    // =============================================
    // Super Admin uniquement
    // =============================================
    Route::middleware('role:super_admin')->group(function () {
        // Marketplace products CUD
        Route::post('/marketplace/products', [ProductController::class, 'store']);
        Route::put('/marketplace/products/{id}', [ProductController::class, 'update']);
        Route::delete('/marketplace/products/{id}', [ProductController::class, 'destroy']);
        Route::patch('/marketplace/products/{id}/stock', [ProductController::class, 'updateStock']);

        // Admin orders + reports
        Route::get('/admin/orders', [AdminOrderController::class, 'index']);
        // Rapports de ventes internes (marketplace TANGA) : super_admin uniquement, pas de gating par plan.
        Route::middleware('log.report:sales')->get('/admin/reports/sales', [AdminSalesReportController::class, 'index']);
        Route::middleware('log.report:sales-csv')->get('/admin/reports/sales/export.csv', [AdminSalesReportController::class, 'exportCsv']);
        Route::middleware('log.report:sales-pdf')->get('/admin/reports/sales/export.pdf', [AdminSalesReportController::class, 'exportPdf']);

        // MQTT
        Route::post('/mqtt/test', [MqttController::class, 'testConnection']);
        Route::post('/mqtt/send-command', [MqttController::class, 'sendCommand']);

        // User management - suppression
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
    });

    // =============================================
    // Admin (super_admin + admin_enterprise + technicien)
    // =============================================
    Route::middleware('role:super_admin,admin_enterprise,technicien')->group(function () {
        // Sites CUD
        Route::post('/sites', [SiteController::class, 'store']);
        Route::put('/sites/{id}', [SiteController::class, 'update']);
        Route::delete('/sites/{id}', [SiteController::class, 'destroy']);

        // Departments CUD
        Route::post('/departments', [DepartmentController::class, 'store']);
        Route::put('/departments/{id}', [DepartmentController::class, 'update']);
        Route::delete('/departments/{id}', [DepartmentController::class, 'destroy']);

        // Employees CUD
        Route::post('/employees', [EmployeeController::class, 'store']);
        Route::put('/employees/{id}', [EmployeeController::class, 'update']);
        Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);
        Route::patch('/employees/{id}/toggle-active', [EmployeeController::class, 'toggleActive']);

        // RFID Devices CUD
        Route::post('/rfid/devices', [RfidDeviceController::class, 'store']);
        Route::put('/rfid/devices/{id}', [RfidDeviceController::class, 'update']);
        Route::delete('/rfid/devices/{id}', [RfidDeviceController::class, 'destroy']);

        // RFID : déclenchement du mode scan pour enregistrer une carte (commande SCAN seule)
        Route::post('/rfid/devices/{id}/scan', [MqttController::class, 'scanCard']);

        // Cards management
        Route::post('/cards', [CardController::class, 'store']);
        Route::patch('/cards/{id}/assign', [CardController::class, 'assign']);
        Route::patch('/cards/{id}/unassign', [CardController::class, 'unassign']);
        Route::patch('/cards/{id}/block', [CardController::class, 'block']);
        Route::patch('/cards/{id}/unblock', [CardController::class, 'unblock']);

        // Schedules CUD
        Route::post('/schedules', [ScheduleController::class, 'store']);
        Route::put('/schedules/{id}', [ScheduleController::class, 'update']);
        Route::delete('/schedules/{id}', [ScheduleController::class, 'destroy']);

        // Holidays CUD
        Route::post('/holidays', [HolidayController::class, 'store']);
        Route::put('/holidays/{id}', [HolidayController::class, 'update']);
        Route::delete('/holidays/{id}', [HolidayController::class, 'destroy']);

        // Biometric devices CUD + enrollments
        Route::post('/biometric/devices', [BiometricDeviceController::class, 'store']);
        Route::delete('/biometric/devices/{id}', [BiometricDeviceController::class, 'destroy']);
        Route::post('/biometric/devices/{id}/sync', [BiometricDeviceController::class, 'sync']);
        Route::patch('/biometric/devices/{id}/set-online', [BiometricDeviceController::class, 'setOnline']);
        Route::post('/biometric/enrollments', [EnrollmentController::class, 'store']);
        Route::post('/biometric/enrollments/enroll', [EnrollmentController::class, 'enroll']);
        Route::delete('/biometric/enrollments/{id}', [EnrollmentController::class, 'destroy']);

        // User management - lecture et gestion
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::patch('/users/{id}/toggle-active', [UserController::class, 'toggleActive']);
        Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword']);
    });

    // =============================================
    // Admin sans technicien (super_admin + admin_enterprise uniquement)
    // Feelback et marketplace write - technicien n'y a pas acces
    // =============================================
    Route::middleware('role:super_admin,admin_enterprise')->group(function () {
        // Feelback devices CUD + alert settings
        Route::post('/feelback/devices', [FeelbackDeviceController::class, 'store']);
        Route::put('/feelback/devices/{id}', [FeelbackDeviceController::class, 'update']);
        Route::delete('/feelback/devices/{id}', [FeelbackDeviceController::class, 'destroy']);
        Route::put('/feelback/alerts/settings', [FeelbackAlertController::class, 'updateSettings']);

        // Review QR config CUD
        Route::post('/feelback/review-config', [ReviewConfigController::class, 'store']);
        Route::post('/feelback/review-config/regenerate-token', [ReviewConfigController::class, 'regenerateToken']);
    });

    // =============================================
    // Tous les roles authentifies (lecture)
    // =============================================

    // Companies (lecture)
    Route::get('/companies', [CompanyController::class, 'index']);
    Route::get('/companies/{id}', [CompanyController::class, 'show']);
    Route::get('/companies/{id}/sites', [CompanyController::class, 'sites']);

    // Sites (lecture)
    Route::get('/sites', [SiteController::class, 'index']);
    Route::get('/sites/{id}', [SiteController::class, 'show']);
    Route::get('/sites/{id}/departments', [SiteController::class, 'departments']);

    // Departments (lecture)
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::get('/departments/{id}', [DepartmentController::class, 'show']);

    // Employees (lecture)
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/employees/{id}', [EmployeeController::class, 'show']);

    // RFID Devices (lecture)
    Route::get('/rfid/devices', [RfidDeviceController::class, 'index']);
    Route::get('/rfid/devices/{id}', [RfidDeviceController::class, 'show']);

    // Cards (lecture)
    Route::get('/cards', [CardController::class, 'index']);
    Route::get('/cards/{id}', [CardController::class, 'show']);
    Route::get('/cards/{id}/history', [CardController::class, 'history']);

    // Schedules (lecture)
    Route::get('/schedules', [ScheduleController::class, 'index']);
    Route::get('/schedules/{id}', [ScheduleController::class, 'show']);

    // Absence requests (lecture + soumission/edition par employe)
    Route::get('/absence-requests', [\App\Http\Controllers\Api\AbsenceRequestController::class, 'index']);
    Route::get('/absence-requests/my', [\App\Http\Controllers\Api\AbsenceRequestController::class, 'myRequests']);
    Route::get('/absence-requests/{id}', [\App\Http\Controllers\Api\AbsenceRequestController::class, 'show']);
    Route::post('/absence-requests', [\App\Http\Controllers\Api\AbsenceRequestController::class, 'store']);
    Route::put('/absence-requests/{id}', [\App\Http\Controllers\Api\AbsenceRequestController::class, 'update']);
    Route::patch('/absence-requests/{id}/review', [\App\Http\Controllers\Api\AbsenceRequestController::class, 'review']);
    Route::delete('/absence-requests/{id}', [\App\Http\Controllers\Api\AbsenceRequestController::class, 'destroy']);

    // Holidays (lecture)
    Route::get('/holidays', [HolidayController::class, 'index']);

    // Attendance (lecture)
    Route::get('/attendance/daily', [AttendanceController::class, 'daily']);
    Route::get('/attendance/monthly', [AttendanceController::class, 'monthly']);
    Route::get('/attendance/employee/{employeeId}', [AttendanceController::class, 'byEmployee']);
    Route::get('/attendance/department/{departmentId}', [AttendanceController::class, 'byDepartment']);
    Route::get('/attendance/summary', [AttendanceController::class, 'summary']);
    Route::get('/attendance/biometric', [AttendanceController::class, 'biometric']);
    Route::middleware('log.report:attendance')->get('/attendance/reports', [AttendanceReportController::class, 'index']);
    Route::middleware('log.report:attendance-csv')->get('/attendance/reports/export.csv', [AttendanceReportController::class, 'exportCsv']);
    Route::middleware('log.report:attendance-pdf')->get('/attendance/reports/export.pdf', [AttendanceReportController::class, 'exportPdf']);

    // Biometric (lecture)
    Route::get('/biometric/devices', [BiometricDeviceController::class, 'index']);
    Route::get('/biometric/devices/{id}', [BiometricDeviceController::class, 'show']);
    Route::get('/biometric/enrollments', [EnrollmentController::class, 'index']);
    Route::get('/biometric/enrollments/{id}', [EnrollmentController::class, 'show']);
    Route::get('/biometric/inconsistencies', [BiometricInconsistencyController::class, 'index']);
    Route::get('/biometric/audit-log', [BiometricAuditController::class, 'index']);

    // Feelback + Marketplace (lecture) - technicien n'y a pas acces
    Route::middleware('role:super_admin,admin_enterprise,manager')->group(function () {
        Route::get('/feelback/review-config', [ReviewConfigController::class, 'show']);
        Route::middleware('log.report:review-stats')->get('/feelback/review-stats', [ReviewStatsController::class, 'index']);
        Route::get('/feelback/review-submissions', [ReviewStatsController::class, 'submissions']);
        Route::get('/feelback/stats', [FeelbackStatsController::class, 'index']);
        Route::get('/feelback/entries', [FeelbackEntryController::class, 'index']);
        Route::get('/feelback/devices', [FeelbackDeviceController::class, 'index']);
        Route::get('/feelback/devices/{id}', [FeelbackDeviceController::class, 'show']);
        Route::get('/feelback/alerts', [FeelbackAlertController::class, 'index']);
        Route::get('/feelback/stats/agency/{agencyId}', [FeelbackStatsController::class, 'byAgency']);
        Route::get('/feelback/comparison', [FeelbackStatsController::class, 'comparison']);
        Route::middleware('log.report:feelback')->get('/feelback/reports', [FeelbackReportController::class, 'index']);
        Route::middleware('log.report:feelback-csv')->get('/feelback/reports/export.csv', [FeelbackReportController::class, 'exportCsv']);
        Route::middleware('log.report:feelback-pdf')->get('/feelback/reports/export.pdf', [FeelbackReportController::class, 'exportPdf']);

        // Marketplace products (lecture)
        Route::get('/marketplace/products', [ProductController::class, 'index']);
        Route::get('/marketplace/products/{id}', [ProductController::class, 'show']);

        // Orders
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
        Route::patch('/orders/{id}/cancel', [OrderController::class, 'cancel']);
        Route::post('/orders/{id}/payment', [OrderController::class, 'initiatePayment']);
    });

    // =============================================
    // QR Code Attendance
    // =============================================
    Route::middleware('role:super_admin,admin_enterprise,technicien')->group(function () {
        // QR de site : génération + révocation
        Route::post('/qr-codes/generate', [QrCodeController::class, 'generate']);
        Route::delete('/qr-codes/{id}', [QrCodeController::class, 'revoke']);

        // Enrôlement des téléphones employés
        Route::post('/employees/device/enroll', [EmployeeDeviceController::class, 'enroll']);
        Route::delete('/employees/{id}/device', [EmployeeDeviceController::class, 'revoke']);

        // Sessions d'enrôlement QR (admin crée + poll)
        Route::post('/enroll-session', [EnrollSessionController::class, 'create']);
        Route::get('/enroll-session/{token}', [EnrollSessionController::class, 'status']);
    });

    // QR Code lecture (tous roles authentifiés)
    Route::get('/qr-codes', [QrCodeController::class, 'index']);
    Route::get('/qr-codes/stats', [QrCodeController::class, 'stats']);
    Route::get('/qr-codes/{id}', [QrCodeController::class, 'show']);

    // Pointage QR — lecture (admin)
    Route::get('/qr-attendance', [QrAttendanceController::class, 'index']);

    // =============================================
    // Firmware OTA
    // =============================================
    Route::middleware('role:super_admin,admin_enterprise,technicien')->group(function () {
        // Lecture
        Route::get('/firmware/versions', [FirmwareController::class, 'versions']);
        Route::get('/firmware/versions/{id}', [FirmwareController::class, 'showVersion']);
        Route::get('/firmware/devices/status', [FirmwareController::class, 'deviceStatuses']);
        Route::get('/firmware/logs', [FirmwareController::class, 'logs']);

        // Mise à jour en masse + progression (admin_enterprise, technicien, super_admin)
        Route::middleware('feature:firmware_updates')->post('/firmware/trigger-company-update', [FirmwareController::class, 'triggerCompanyUpdate']);
        Route::get('/firmware/company-update-progress', [FirmwareController::class, 'companyUpdateProgress']);
        Route::middleware('feature:firmware_updates')->post('/firmware/retry-failed', [FirmwareController::class, 'retryFailed']);
        Route::middleware('feature:firmware_updates')->post('/firmware/retry-pending', [FirmwareController::class, 'retryPending']);

        // Ecriture : super_admin + technicien seulement
        Route::middleware('role:super_admin,technicien')->group(function () {
            Route::post('/firmware/versions', [FirmwareController::class, 'upload']);
            Route::delete('/firmware/versions/{id}', [FirmwareController::class, 'deleteVersion']);
            Route::patch('/firmware/versions/{id}/auto-update', [FirmwareController::class, 'setAutoUpdate']);
            Route::post('/firmware/update', [FirmwareController::class, 'triggerUpdate']);
        });

        // Publication + notification : super_admin uniquement
        Route::middleware('role:super_admin')->group(function () {
            Route::patch('/firmware/versions/{id}/publish', [FirmwareController::class, 'publish']);
        });
    });

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/trends', [DashboardController::class, 'trends']);
    Route::get('/dashboard/charts', [DashboardController::class, 'charts']);

    // Analytics avances — fonctionnalite Premium (feature advanced_analytics).
    Route::middleware(['role:super_admin,admin_enterprise,manager', 'feature:advanced_analytics'])
        ->get('/analytics/advanced', [AdvancedAnalyticsController::class, 'index']);

    // Audit log des rapports — accessible aux admins (scoped par company). Feature payante (RH automatises).
    Route::middleware(['role:super_admin,admin_enterprise', 'feature:hr_reports'])
        ->get('/reports/audit-log', [\App\Http\Controllers\Api\ReportAuditLogController::class, 'index']);

    // Planification d'envoi automatique de rapports — admins. Feature payante (RH automatises).
    Route::middleware(['role:super_admin,admin_enterprise', 'feature:hr_reports'])->group(function () {
        Route::get('/reports/schedules', [\App\Http\Controllers\Api\ReportScheduleController::class, 'index']);
        Route::post('/reports/schedules', [\App\Http\Controllers\Api\ReportScheduleController::class, 'store']);
        Route::patch('/reports/schedules/{id}', [\App\Http\Controllers\Api\ReportScheduleController::class, 'update']);
        Route::delete('/reports/schedules/{id}', [\App\Http\Controllers\Api\ReportScheduleController::class, 'destroy']);
        Route::post('/reports/schedules/{id}/send', [\App\Http\Controllers\Api\ReportScheduleController::class, 'sendNow']);
    });

    // Signature numerique des rapports techniciens.
    Route::middleware('role:super_admin,technicien')
        ->post('/technicien-reports', [TechnicienReportController::class, 'store']);

    // =============================================
    // Activites techniciens
    // =============================================
    // Lecture : super_admin voit tout, technicien voit ses propres actions
    Route::middleware('role:super_admin,technicien')->group(function () {
        Route::get('/technicien/activities', [TechnicienActivityController::class, 'index']);
    });
    // Super admin uniquement : syntheses
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/technicien/activities/summary-by-company', [TechnicienActivityController::class, 'summaryByCompany']);
        Route::get('/technicien/{technicienId}/companies', [TechnicienActivityController::class, 'companiesByTechnicien']);
    });

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Badges "attention" du menu (compteurs par role + scoping entreprise)
    Route::get('/menu-badges', [MenuBadgeController::class, 'index']);
    Route::post('/menu-badges/seen', [MenuBadgeController::class, 'seen']);

    // =============================================
    // Paie (super_admin + admin_enterprise) — necessite la feature payroll (plans Garantie/Premium)
    // =============================================
    Route::middleware(['role:super_admin,admin_enterprise', 'feature:payroll'])->group(function () {
        // Configuration
        Route::get('/payroll/config/{companyId}', [PayrollController::class, 'getConfig']);
        Route::put('/payroll/config/{companyId}', [PayrollController::class, 'saveConfig']);
        Route::put('/payroll/config/{companyId}/lateness-rules', [PayrollController::class, 'saveLatenessRules']);

        // Generation
        Route::post('/payroll/generate', [PayrollController::class, 'generate']);

        // Fiches — liste + detail + validation + export
        Route::get('/payroll/payslips', [PayrollController::class, 'index']);
        Route::get('/payroll/payslips/{id}', [PayrollController::class, 'show']);
        Route::patch('/payroll/payslips/{id}/validate', [PayrollController::class, 'validate']);
        // PDF genere cote frontend (jsPDF) — pas d'endpoint necessaire
    });

    // Portail employe — acces a ses propres fiches (tous roles)
    Route::get('/payroll/employees/{employeeId}/payslips', [PayrollController::class, 'myPayslips']);

    // =============================================
    // Support IT (sante systeme + monitoring capteurs + alertes)
    // =============================================
    Route::middleware('role:super_admin,support_it')->prefix('support')->group(function () {
        Route::get('/health', [SupportHealthController::class, 'index']);
        Route::get('/devices/overview', [SupportController::class, 'overview']);
        Route::get('/devices', [SupportController::class, 'devices']);
        Route::get('/devices/{kind}/{id}', [SupportController::class, 'deviceDetail']);
        Route::post('/devices/{kind}/{id}/ping', [SupportController::class, 'pingDevice']);
        Route::post('/devices/{kind}/{id}/command', [SupportController::class, 'sendCommand']);
        Route::get('/companies', [SupportController::class, 'companies']);
        Route::get('/companies/{id}', [SupportController::class, 'companyDetail']);
        Route::post('/companies/{id}/impersonate', [SupportController::class, 'impersonateCompany']);
        Route::post('/users/{id}/reset-password', [SupportController::class, 'resetUserPassword']);
        Route::post('/users/{id}/set-password', [SupportController::class, 'setUserPassword']);
        Route::post('/users/{id}/impersonate', [SupportController::class, 'impersonateUser']);
        Route::get('/witnesses', [SupportController::class, 'listWitnesses']);
        Route::post('/witnesses/{kind}/{id}', [SupportController::class, 'markWitness']);
        Route::delete('/witnesses/{kind}/{id}', [SupportController::class, 'unmarkWitness']);
        Route::get('/alerts', [SupportController::class, 'alerts']);
        Route::post('/alerts/{id}/acknowledge', [SupportController::class, 'acknowledgeAlert']);
        Route::post('/alerts/{id}/resolve', [SupportController::class, 'resolveAlert']);
        Route::get('/tickets', [SupportTicketController::class, 'supportIndex']);
        Route::patch('/tickets/{id}', [SupportTicketController::class, 'supportUpdate']);
    });

    // =============================================
    // Plaintes / tickets cote client (admin_enterprise + manager)
    // =============================================
    Route::middleware('role:admin_enterprise,manager')->prefix('client')->group(function () {
        Route::get('/tickets', [SupportTicketController::class, 'clientIndex']);
        // Ouverture de plainte reservee aux plans incluant le SAV (Garantie/Premium).
        Route::middleware('feature:sav_included')->post('/tickets', [SupportTicketController::class, 'clientStore']);
    });
});
