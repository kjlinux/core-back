<?php

namespace App\Http\Controllers\Api;

use App\Models\AttendanceRecord;
use App\Models\BiometricDevice;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\FeelbackAlert;
use App\Models\FeelbackDevice;
use App\Models\FeelbackEntry;
use App\Models\FingerprintEnrollment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RfidCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends BaseApiController
{
    public function stats(): JsonResponse
    {
        $user = auth()->user();
        $isSuperAdmin = $user->isSuperAdmin();
        $companyId = $isSuperAdmin ? null : $this->resolveActiveCompanyId();

        $activeCompanies = $isSuperAdmin
            ? Company::where('is_active', true)->count()
            : (Company::where('id', $companyId)->where('is_active', true)->exists() ? 1 : 0);

        $bioDeviceQuery = BiometricDevice::where('is_online', true);
        $feelbackDeviceQuery = FeelbackDevice::where('is_online', true);
        if (! $isSuperAdmin) {
            $bioDeviceQuery->where('company_id', $companyId);
            $feelbackDeviceQuery->where('company_id', $companyId);
        }
        $connectedDevices = $bioDeviceQuery->count() + $feelbackDeviceQuery->count();

        $employeeQuery = Employee::query();
        if (! $isSuperAdmin) {
            $employeeQuery->where('company_id', $companyId);
        }
        $totalEmployees = $employeeQuery->count();

        $entryQuery = FeelbackEntry::query();
        if (! $isSuperAdmin) {
            $entryQuery->whereHas('site', fn ($q) => $q->where('company_id', $companyId));
        }
        $totalEntries = (clone $entryQuery)->count();
        $bonEntries = (clone $entryQuery)->where('level', 'bon')->count();
        $globalSatisfactionRate = $totalEntries > 0
            ? round(($bonEntries / $totalEntries) * 100, 2)
            : 0;

        $orderQuery = Order::query();
        if (! $isSuperAdmin) {
            $orderQuery->where('company_id', $companyId);
        }
        // Si super_admin SANS company active sélectionnée → total global.
        // Sinon (admin entreprise, ou super_admin avec company active) → scopé via orderQuery déjà filtré.
        $rfidCardsSold = ($isSuperAdmin && ! $companyId)
            ? OrderItem::sum('quantity')
            : OrderItem::whereIn('order_id', (clone $orderQuery)->pluck('id'))->sum('quantity');

        $marketplaceRevenue = (clone $orderQuery)->where('payment_status', 'paid')->sum('total');

        $alertQuery = FeelbackAlert::where('is_read', false);
        if (! $isSuperAdmin) {
            $alertQuery->whereHas('site', fn ($q) => $q->where('company_id', $companyId));
        }
        $technicalAlerts = $alertQuery->count();

        // Attendance today
        $today = now()->toDateString();
        $attendanceQuery = AttendanceRecord::where('date', $today);
        if (! $isSuperAdmin) {
            $attendanceQuery->whereHas('employee', fn ($q) => $q->where('company_id', $companyId));
        }
        $presentToday = (clone $attendanceQuery)->where('status', 'present')->count();
        $lateToday = (clone $attendanceQuery)->where('status', 'late')->count();
        $absentToday = (clone $attendanceQuery)->where('status', 'absent')->count();
        $leftEarlyToday = (clone $attendanceQuery)->where('status', 'left_early')->count();

        // left_early = présent ce jour mais parti tôt → compte comme présence (numérateur + dénominateur).
        $totalForRate = $presentToday + $lateToday + $absentToday + $leftEarlyToday;
        $attendanceRate = $totalForRate > 0
            ? round((($presentToday + $lateToday + $leftEarlyToday) / $totalForRate) * 100, 1)
            : 0;

        // Orders
        $orderQuery = Order::query();
        if (! $isSuperAdmin) {
            $orderQuery->where('company_id', $companyId);
        }
        $totalOrders = (clone $orderQuery)->count();
        $pendingOrders = (clone $orderQuery)->where('status', 'pending')->count();

        // Biometric enrollments
        $enrollQuery = FingerprintEnrollment::where('status', 'enrolled');
        if (! $isSuperAdmin) {
            $enrollQuery->whereHas('employee', fn ($q) => $q->where('company_id', $companyId));
        }
        $biometricEnrolled = $enrollQuery->count();

        // Active RFID cards
        $cardQuery = RfidCard::where('status', 'active');
        if (! $isSuperAdmin) {
            $cardQuery->where('company_id', $companyId);
        }
        $activeCards = $cardQuery->count();

        return $this->successResponse([
            'activeCompanies' => $activeCompanies,
            'connectedDevices' => $connectedDevices,
            'totalEmployees' => $totalEmployees,
            'globalSatisfactionRate' => $globalSatisfactionRate,
            'rfidCardsSold' => $rfidCardsSold,
            'marketplaceRevenue' => $marketplaceRevenue,
            'technicalAlerts' => $technicalAlerts,
            'presentToday' => $presentToday,
            'absentToday' => $absentToday,
            'lateToday' => $lateToday,
            'attendanceRate' => $attendanceRate,
            'totalOrders' => $totalOrders,
            'pendingOrders' => $pendingOrders,
            'biometricEnrolled' => $biometricEnrolled,
            'activeCards' => $activeCards,
        ]);
    }

    public function trends(Request $request): JsonResponse
    {
        $user = auth()->user();
        $isSuperAdmin = $user->isSuperAdmin();
        $companyId = $isSuperAdmin ? null : $this->resolveActiveCompanyId();

        $period = $request->input('period', 'month');

        $now = now();
        $trends = [];

        if ($period === 'week') {
            $currentStart = $now->copy()->startOfWeek();
            $previousStart = $now->copy()->subWeek()->startOfWeek();
            $previousEnd = $now->copy()->subWeek()->endOfWeek();
        } elseif ($period === 'year') {
            $currentStart = $now->copy()->startOfYear();
            $previousStart = $now->copy()->subYear()->startOfYear();
            $previousEnd = $now->copy()->subYear()->endOfYear();
        } else {
            $currentStart = $now->copy()->startOfMonth();
            $previousStart = $now->copy()->subMonth()->startOfMonth();
            $previousEnd = $now->copy()->subMonth()->endOfMonth();
        }

        // Employees trend
        $empQuery = Employee::query();
        if (! $isSuperAdmin) {
            $empQuery->where('company_id', $companyId);
        }
        $currentEmployees = (clone $empQuery)->where('created_at', '>=', $currentStart)->count();
        $previousEmployees = (clone $empQuery)->whereBetween('created_at', [$previousStart, $previousEnd])->count();
        $trends[] = $this->buildTrend('Employés', $currentEmployees, $previousEmployees);

        // Orders trend
        $orderQuery = Order::query();
        if (! $isSuperAdmin) {
            $orderQuery->where('company_id', $companyId);
        }
        $currentOrders = (clone $orderQuery)->where('created_at', '>=', $currentStart)->count();
        $previousOrders = (clone $orderQuery)->whereBetween('created_at', [$previousStart, $previousEnd])->count();
        $trends[] = $this->buildTrend('Commandes', $currentOrders, $previousOrders);

        // Revenue trend
        $revQuery = Order::where('payment_status', 'paid');
        if (! $isSuperAdmin) {
            $revQuery->where('company_id', $companyId);
        }
        $currentRevenue = (clone $revQuery)->where('created_at', '>=', $currentStart)->sum('total');
        $previousRevenue = (clone $revQuery)->whereBetween('created_at', [$previousStart, $previousEnd])->sum('total');
        $trends[] = $this->buildTrend('Revenus', $currentRevenue, $previousRevenue);

        // Satisfaction trend
        $entryBase = FeelbackEntry::query();
        if (! $isSuperAdmin) {
            $entryBase->whereHas('site', fn ($q) => $q->where('company_id', $companyId));
        }
        $currentTotal = (clone $entryBase)->where('created_at', '>=', $currentStart)->count();
        $currentBon = (clone $entryBase)->where('level', 'bon')
            ->where('created_at', '>=', $currentStart)
            ->count();
        $currentRate = $currentTotal > 0 ? round(($currentBon / $currentTotal) * 100, 2) : 0;

        $previousTotal = (clone $entryBase)->whereBetween('created_at', [$previousStart, $previousEnd])->count();
        $previousBon = (clone $entryBase)->where('level', 'bon')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count();
        $previousRate = $previousTotal > 0 ? round(($previousBon / $previousTotal) * 100, 2) : 0;

        $trends[] = $this->buildTrend('Satisfaction', $currentRate, $previousRate);

        // Alerts trend
        $alertBase = FeelbackAlert::query();
        if (! $isSuperAdmin) {
            $alertBase->whereHas('site', fn ($q) => $q->where('company_id', $companyId));
        }
        $currentAlerts = (clone $alertBase)->where('created_at', '>=', $currentStart)->count();
        $previousAlerts = (clone $alertBase)->whereBetween('created_at', [$previousStart, $previousEnd])->count();
        $trends[] = $this->buildTrend('Alertes', $currentAlerts, $previousAlerts);

        return $this->successResponse($trends);
    }

    public function charts(): JsonResponse
    {
        $user = auth()->user();
        $isSuperAdmin = $user->isSuperAdmin();
        $companyId = $isSuperAdmin ? null : $this->resolveActiveCompanyId();

        // Attendance trend — last 7 days (1 requête GROUP BY au lieu de 7)
        $dateStart7 = now()->subDays(6)->toDateString();
        $dateEnd7 = now()->toDateString();

        $attBase = AttendanceRecord::whereBetween('date', [$dateStart7, $dateEnd7])
            ->whereIn('status', ['present', 'late', 'left_early']);
        if (! $isSuperAdmin) {
            $attBase->whereHas('employee', fn ($sq) => $sq->where('company_id', $companyId));
        }
        $attByDay = $attBase->selectRaw('date, COUNT(*) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        $attendanceTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $attendanceTrend[] = [
                'label' => $day->locale('fr')->isoFormat('ddd D'),
                'value' => (int) ($attByDay[$day->toDateString()] ?? 0),
            ];
        }

        // Satisfaction trend — last 7 days (2 requêtes GROUP BY au lieu de 14)
        $feelBase = FeelbackEntry::whereDate('created_at', '>=', $dateStart7)
            ->whereDate('created_at', '<=', $dateEnd7);
        if (! $isSuperAdmin) {
            $feelBase->whereHas('site', fn ($sq) => $sq->where('company_id', $companyId));
        }
        $feelTotals = (clone $feelBase)->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'day');
        $feelBons = (clone $feelBase)->where('level', 'bon')
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->pluck('total', 'day');

        $satisfactionTrend = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $dayStr = $day->toDateString();
            $total = (int) ($feelTotals[$dayStr] ?? 0);
            $bon = (int) ($feelBons[$dayStr] ?? 0);
            $satisfactionTrend[] = [
                'label' => $day->locale('fr')->isoFormat('ddd D'),
                'value' => $total > 0 ? round(($bon / $total) * 100, 1) : 0,
            ];
        }

        // Attendance by department — today (une seule requete groupee, evite le N+1)
        $today = now()->toDateString();
        $deptQuery = Department::query();
        if (! $isSuperAdmin) {
            $deptQuery->where('company_id', $companyId);
        }
        $departments = $deptQuery->get(['id', 'name']);

        $countsByDept = AttendanceRecord::query()
            ->join('employees', 'attendance_records.employee_id', '=', 'employees.id')
            ->where('attendance_records.date', $today)
            ->whereIn('attendance_records.status', ['present', 'late'])
            ->whereIn('employees.department_id', $departments->pluck('id'))
            ->groupBy('employees.department_id')
            ->selectRaw('employees.department_id as dept_id, COUNT(*) as total')
            ->pluck('total', 'dept_id');

        $attendanceByDepartment = $departments
            ->map(fn ($d) => ['label' => $d->name, 'value' => (int) ($countsByDept[$d->id] ?? 0)])
            ->filter(fn ($d) => $d['value'] > 0)
            ->values()
            ->toArray();

        // Revenue monthly — last 6 months (super admin only) — une seule requête groupée.
        $revenueMonthly = [];
        if ($isSuperAdmin) {
            $from = now()->subMonths(5)->startOfMonth();
            $monthExpr = DB::getDriverName() === 'pgsql'
                ? "TO_CHAR(created_at, 'YYYY-MM')"
                : "DATE_FORMAT(created_at, '%Y-%m')";

            $revenueByMonth = Order::where('payment_status', 'paid')
                ->where('created_at', '>=', $from)
                ->selectRaw("$monthExpr as ym, SUM(total) as revenue")
                ->groupBy('ym')
                ->pluck('revenue', 'ym');

            for ($i = 5; $i >= 0; $i--) {
                $month = now()->subMonths($i);
                $key = $month->format('Y-m');
                $label = $month->locale('fr')->isoFormat('MMM YY');
                $revenueMonthly[] = ['label' => $label, 'value' => (float) ($revenueByMonth[$key] ?? 0)];
            }
        }

        // Companies by module (super admin only)
        $companiesByModule = [];
        if ($isSuperAdmin) {
            $companiesByModule = [
                ['label' => 'Pointage RFID', 'value' => Company::where('is_active', true)->count()],
                ['label' => 'Biométrique',   'value' => BiometricDevice::distinct('company_id')->count('company_id')],
                ['label' => 'Feelback',      'value' => FeelbackDevice::distinct('company_id')->count('company_id')],
            ];
        }

        return $this->successResponse([
            'attendanceTrend' => $attendanceTrend,
            'satisfactionTrend' => $satisfactionTrend,
            'attendanceByDepartment' => $attendanceByDepartment,
            'revenueMonthly' => $revenueMonthly,
            'companiesByModule' => $companiesByModule,
        ]);
    }

    private function buildTrend(string $label, float $value, float $previousValue): array
    {
        $changePercent = $previousValue > 0
            ? round((($value - $previousValue) / $previousValue) * 100, 2)
            : ($value > 0 ? 100 : 0);

        return [
            'label' => $label,
            'value' => $value,
            'previousValue' => $previousValue,
            'changePercent' => $changePercent,
        ];
    }
}
