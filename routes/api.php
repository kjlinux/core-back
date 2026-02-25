<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\BiometricDeviceController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\BiometricAuditController;
use App\Http\Controllers\Api\BiometricInconsistencyController;
use App\Http\Controllers\Api\FeelbackStatsController;
use App\Http\Controllers\Api\FeelbackEntryController;
use App\Http\Controllers\Api\FeelbackDeviceController;
use App\Http\Controllers\Api\FeelbackAlertController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AdminOrderController;
use App\Http\Controllers\Api\PaymentCallbackController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\MqttController;

// Public routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/payment/callback', [PaymentCallbackController::class, 'handle']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Companies
    Route::get('/companies', [CompanyController::class, 'index']);
    Route::post('/companies', [CompanyController::class, 'store']);
    Route::get('/companies/{id}', [CompanyController::class, 'show']);
    Route::put('/companies/{id}', [CompanyController::class, 'update']);
    Route::patch('/companies/{id}/toggle-active', [CompanyController::class, 'toggleActive']);
    Route::get('/companies/{id}/sites', [CompanyController::class, 'sites']);

    // Sites
    Route::get('/sites', [SiteController::class, 'index']);
    Route::post('/sites', [SiteController::class, 'store']);
    Route::get('/sites/{id}', [SiteController::class, 'show']);
    Route::put('/sites/{id}', [SiteController::class, 'update']);
    Route::delete('/sites/{id}', [SiteController::class, 'destroy']);
    Route::get('/sites/{id}/departments', [SiteController::class, 'departments']);

    // Departments
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::post('/departments', [DepartmentController::class, 'store']);
    Route::get('/departments/{id}', [DepartmentController::class, 'show']);
    Route::put('/departments/{id}', [DepartmentController::class, 'update']);
    Route::delete('/departments/{id}', [DepartmentController::class, 'destroy']);

    // Employees
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::post('/employees', [EmployeeController::class, 'store']);
    Route::get('/employees/{id}', [EmployeeController::class, 'show']);
    Route::put('/employees/{id}', [EmployeeController::class, 'update']);
    Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);
    Route::patch('/employees/{id}/toggle-active', [EmployeeController::class, 'toggleActive']);

    // RFID Cards
    Route::get('/cards', [CardController::class, 'index']);
    Route::post('/cards', [CardController::class, 'store']);
    Route::get('/cards/{id}', [CardController::class, 'show']);
    Route::patch('/cards/{id}/assign', [CardController::class, 'assign']);
    Route::patch('/cards/{id}/unassign', [CardController::class, 'unassign']);
    Route::patch('/cards/{id}/block', [CardController::class, 'block']);
    Route::patch('/cards/{id}/unblock', [CardController::class, 'unblock']);
    Route::get('/cards/{id}/history', [CardController::class, 'history']);

    // Schedules
    Route::get('/schedules', [ScheduleController::class, 'index']);
    Route::post('/schedules', [ScheduleController::class, 'store']);
    Route::get('/schedules/{id}', [ScheduleController::class, 'show']);
    Route::put('/schedules/{id}', [ScheduleController::class, 'update']);
    Route::delete('/schedules/{id}', [ScheduleController::class, 'destroy']);

    // Holidays
    Route::get('/holidays', [HolidayController::class, 'index']);
    Route::post('/holidays', [HolidayController::class, 'store']);
    Route::put('/holidays/{id}', [HolidayController::class, 'update']);
    Route::delete('/holidays/{id}', [HolidayController::class, 'destroy']);

    // Attendance
    Route::get('/attendance/daily', [AttendanceController::class, 'daily']);
    Route::get('/attendance/monthly', [AttendanceController::class, 'monthly']);
    Route::get('/attendance/employee/{employeeId}', [AttendanceController::class, 'byEmployee']);
    Route::get('/attendance/department/{departmentId}', [AttendanceController::class, 'byDepartment']);
    Route::get('/attendance/summary', [AttendanceController::class, 'summary']);
    Route::get('/attendance/biometric', [AttendanceController::class, 'biometric']);

    // Biometric
    Route::get('/biometric/devices', [BiometricDeviceController::class, 'index']);
    Route::post('/biometric/devices', [BiometricDeviceController::class, 'store']);
    Route::get('/biometric/devices/{id}', [BiometricDeviceController::class, 'show']);
    Route::delete('/biometric/devices/{id}', [BiometricDeviceController::class, 'destroy']);
    Route::post('/biometric/devices/{id}/sync', [BiometricDeviceController::class, 'sync']);
    Route::get('/biometric/enrollments', [EnrollmentController::class, 'index']);
    Route::post('/biometric/enrollments', [EnrollmentController::class, 'store']);
    Route::delete('/biometric/enrollments/{id}', [EnrollmentController::class, 'destroy']);
    Route::get('/biometric/inconsistencies', [BiometricInconsistencyController::class, 'index']);
    Route::get('/biometric/audit-log', [BiometricAuditController::class, 'index']);

    // Feelback
    Route::get('/feelback/stats', [FeelbackStatsController::class, 'index']);
    Route::get('/feelback/entries', [FeelbackEntryController::class, 'index']);
    Route::get('/feelback/devices', [FeelbackDeviceController::class, 'index']);
    Route::post('/feelback/devices', [FeelbackDeviceController::class, 'store']);
    Route::get('/feelback/devices/{id}', [FeelbackDeviceController::class, 'show']);
    Route::put('/feelback/devices/{id}', [FeelbackDeviceController::class, 'update']);
    Route::delete('/feelback/devices/{id}', [FeelbackDeviceController::class, 'destroy']);
    Route::post('/feelback/devices/{id}/restart', [FeelbackDeviceController::class, 'restart']);
    Route::get('/feelback/alerts', [FeelbackAlertController::class, 'index']);
    Route::put('/feelback/alerts/settings', [FeelbackAlertController::class, 'updateSettings']);
    Route::get('/feelback/stats/agency/{agencyId}', [FeelbackStatsController::class, 'byAgency']);
    Route::get('/feelback/comparison', [FeelbackStatsController::class, 'comparison']);

    // Marketplace
    Route::get('/marketplace/products', [ProductController::class, 'index']);
    Route::post('/marketplace/products', [ProductController::class, 'store']);
    Route::get('/marketplace/products/{id}', [ProductController::class, 'show']);
    Route::put('/marketplace/products/{id}', [ProductController::class, 'update']);
    Route::patch('/marketplace/products/{id}/stock', [ProductController::class, 'updateStock']);

    // Orders
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::patch('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::post('/orders/{id}/payment', [OrderController::class, 'initiatePayment']);

    // Admin Orders (super_admin only)
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/admin/orders', [AdminOrderController::class, 'index']);
    });

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/trends', [DashboardController::class, 'trends']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // MQTT
    Route::post('/mqtt/test', [MqttController::class, 'testConnection']);
    Route::post('/mqtt/send-command', [MqttController::class, 'sendCommand']);
});
