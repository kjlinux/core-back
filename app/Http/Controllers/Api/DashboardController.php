<?php

namespace App\Http\Controllers\Api;

use App\Models\BiometricDevice;
use App\Models\Company;
use App\Models\Employee;
use App\Models\FeelbackAlert;
use App\Models\FeelbackDevice;
use App\Models\FeelbackEntry;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends BaseApiController
{
    public function stats(): JsonResponse
    {
        $activeCompanies = Company::where('is_active', true)->count();

        $connectedDevices = BiometricDevice::where('is_online', true)->count()
            + FeelbackDevice::where('is_online', true)->count();

        $totalEmployees = Employee::count();

        $totalEntries = FeelbackEntry::count();
        $bonEntries = FeelbackEntry::where('level', 'bon')->count();
        $globalSatisfactionRate = $totalEntries > 0
            ? round(($bonEntries / $totalEntries) * 100, 2)
            : 0;

        $rfidCardsSold = OrderItem::sum('quantity');

        $marketplaceRevenue = Order::where('payment_status', 'paid')->sum('total');

        $technicalAlerts = FeelbackAlert::where('is_read', false)->count();

        return $this->successResponse([
            'activeCompanies' => $activeCompanies,
            'connectedDevices' => $connectedDevices,
            'totalEmployees' => $totalEmployees,
            'globalSatisfactionRate' => $globalSatisfactionRate,
            'rfidCardsSold' => $rfidCardsSold,
            'marketplaceRevenue' => $marketplaceRevenue,
            'technicalAlerts' => $technicalAlerts,
        ]);
    }

    public function trends(Request $request): JsonResponse
    {
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
        $currentEmployees = Employee::where('created_at', '>=', $currentStart)->count();
        $previousEmployees = Employee::whereBetween('created_at', [$previousStart, $previousEnd])->count();
        $trends[] = $this->buildTrend('Employes', $currentEmployees, $previousEmployees);

        // Orders trend
        $currentOrders = Order::where('created_at', '>=', $currentStart)->count();
        $previousOrders = Order::whereBetween('created_at', [$previousStart, $previousEnd])->count();
        $trends[] = $this->buildTrend('Commandes', $currentOrders, $previousOrders);

        // Revenue trend
        $currentRevenue = Order::where('payment_status', 'paid')
            ->where('created_at', '>=', $currentStart)
            ->sum('total');
        $previousRevenue = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->sum('total');
        $trends[] = $this->buildTrend('Revenus', $currentRevenue, $previousRevenue);

        // Satisfaction trend
        $currentTotal = FeelbackEntry::where('created_at', '>=', $currentStart)->count();
        $currentBon = FeelbackEntry::where('level', 'bon')
            ->where('created_at', '>=', $currentStart)
            ->count();
        $currentRate = $currentTotal > 0 ? round(($currentBon / $currentTotal) * 100, 2) : 0;

        $previousTotal = FeelbackEntry::whereBetween('created_at', [$previousStart, $previousEnd])->count();
        $previousBon = FeelbackEntry::where('level', 'bon')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count();
        $previousRate = $previousTotal > 0 ? round(($previousBon / $previousTotal) * 100, 2) : 0;

        $trends[] = $this->buildTrend('Satisfaction', $currentRate, $previousRate);

        // Alerts trend
        $currentAlerts = FeelbackAlert::where('created_at', '>=', $currentStart)->count();
        $previousAlerts = FeelbackAlert::whereBetween('created_at', [$previousStart, $previousEnd])->count();
        $trends[] = $this->buildTrend('Alertes', $currentAlerts, $previousAlerts);

        return $this->successResponse($trends);
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
