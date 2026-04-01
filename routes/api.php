<?php

use App\Http\Controllers\Api\AdminOrderController;
use App\Http\Controllers\Api\QrCodeController;
use App\Http\Controllers\Api\QrAttendanceController;
use App\Http\Controllers\Api\EmployeeDeviceController;
use App\Http\Controllers\Api\EnrollSessionController;
use App\Http\Controllers\Api\FirmwareController;
use App\Http\Controllers\Api\AdminSalesReportController;
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
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\FeelbackAlertController;
use App\Http\Controllers\Api\FeelbackDeviceController;
use App\Http\Controllers\Api\FeelbackEntryController;
use App\Http\Controllers\Api\FeelbackReportController;
use App\Http\Controllers\Api\FeelbackStatsController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Controllers\Api\MqttController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentCallbackController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PublicReviewController;
use App\Http\Controllers\Api\ResetPasswordController;
use App\Http\Controllers\Api\ReviewConfigController;
use App\Http\Controllers\Api\ReviewStatsController;
use App\Http\Controllers\Api\RfidDeviceController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Firmware version check — appelee par les capteurs ESP32 (sans auth)
Route::get('/firmware/version.json', [FirmwareController::class, 'latestVersion']);

// Public routes (sans auth)
Route::prefix('public')->group(function () {
    Route::get('/review/{token}', [PublicReviewController::class, 'show']);
    Route::post('/review/{token}/submit', [PublicReviewController::class, 'submit']);
});

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/logout', [AuthController::class, 'logout']);
Route::post('/auth/forgot-password', ForgotPasswordController::class);
Route::post('/auth/reset-password', ResetPasswordController::class);
Route::post('/payment/callback', [PaymentCallbackController::class, 'handle']);

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
    // Super Admin + Technicien (setup/onboarding)
    // =============================================
    Route::middleware('role:super_admin,technicien')->group(function () {
        // Companies CUD
        Route::post('/companies', [CompanyController::class, 'store']);
        Route::put('/companies/{id}', [CompanyController::class, 'update']);
        Route::patch('/companies/{id}/toggle-active', [CompanyController::class, 'toggleActive']);

    });

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
        Route::get('/admin/reports/sales', [AdminSalesReportController::class, 'index']);

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

    // Holidays (lecture)
    Route::get('/holidays', [HolidayController::class, 'index']);

    // Attendance (lecture)
    Route::get('/attendance/daily', [AttendanceController::class, 'daily']);
    Route::get('/attendance/monthly', [AttendanceController::class, 'monthly']);
    Route::get('/attendance/employee/{employeeId}', [AttendanceController::class, 'byEmployee']);
    Route::get('/attendance/department/{departmentId}', [AttendanceController::class, 'byDepartment']);
    Route::get('/attendance/summary', [AttendanceController::class, 'summary']);
    Route::get('/attendance/biometric', [AttendanceController::class, 'biometric']);
    Route::get('/attendance/reports', [AttendanceReportController::class, 'index']);

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
        Route::get('/feelback/review-stats', [ReviewStatsController::class, 'index']);
        Route::get('/feelback/review-submissions', [ReviewStatsController::class, 'submissions']);
        Route::get('/feelback/stats', [FeelbackStatsController::class, 'index']);
        Route::get('/feelback/entries', [FeelbackEntryController::class, 'index']);
        Route::get('/feelback/devices', [FeelbackDeviceController::class, 'index']);
        Route::get('/feelback/devices/{id}', [FeelbackDeviceController::class, 'show']);
        Route::get('/feelback/alerts', [FeelbackAlertController::class, 'index']);
        Route::get('/feelback/stats/agency/{agencyId}', [FeelbackStatsController::class, 'byAgency']);
        Route::get('/feelback/comparison', [FeelbackStatsController::class, 'comparison']);
        Route::get('/feelback/reports', [FeelbackReportController::class, 'index']);

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

    // Scan QR (public — depuis le téléphone de l'employé, sans auth requise)
    Route::get('/qr-attendance', [QrAttendanceController::class, 'index']);
    Route::post('/qr-attendance/scan', [QrAttendanceController::class, 'scan']);

    // Identifier si un appareil est enrôlé (utilisé par la page de scan mobile)
    Route::post('/employees/device/identify', [EmployeeDeviceController::class, 'identify']);

    // Sessions d'enrôlement QR (soumission depuis le téléphone — sans auth)
    Route::post('/enroll-session/{token}/submit', [EnrollSessionController::class, 'submit']);

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
        Route::post('/firmware/trigger-company-update', [FirmwareController::class, 'triggerCompanyUpdate']);
        Route::get('/firmware/company-update-progress', [FirmwareController::class, 'companyUpdateProgress']);
        Route::post('/firmware/retry-failed', [FirmwareController::class, 'retryFailed']);

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

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
});
