<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminSalesReportController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $orderQuery = Order::query();

        if ($startDate) {
            $orderQuery->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $orderQuery->whereDate('created_at', '<=', $endDate);
        }

        // Global stats
        $totalOrders = (clone $orderQuery)->count();
        $totalRevenue = (clone $orderQuery)->where('payment_status', 'paid')->sum('total');
        $averageBasket = $totalOrders > 0 ? round($totalRevenue / $totalOrders) : 0;
        $pendingOrders = (clone $orderQuery)->where('status', 'pending')->count();

        // Revenue by month
        $revenueByMonth = Order::where('payment_status', 'paid')
            ->when($startDate, fn ($q) => $q->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('created_at', '<=', $endDate))
            ->select(
                DB::raw("TO_CHAR(created_at, 'YYYY-MM') as month"),
                DB::raw('SUM(total) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Orders by status
        $ordersByStatus = (clone $orderQuery)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(fn ($item) => ['name' => $this->translateStatus($item->status), 'value' => $item->count]);

        // Top products
        $topProducts = OrderItem::query()
            ->when($startDate, fn ($q) => $q->whereHas('order', fn ($oq) => $oq->whereDate('created_at', '>=', $startDate)))
            ->when($endDate, fn ($q) => $q->whereHas('order', fn ($oq) => $oq->whereDate('created_at', '<=', $endDate)))
            ->select('product_name', DB::raw('SUM(quantity) as total_quantity'))
            ->groupBy('product_name')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get()
            ->map(fn ($item) => ['name' => $item->product_name, 'value' => $item->total_quantity]);

        return $this->successResponse([
            'totalOrders' => $totalOrders,
            'totalRevenue' => $totalRevenue,
            'averageBasket' => $averageBasket,
            'pendingOrders' => $pendingOrders,
            'revenueByMonth' => $revenueByMonth,
            'ordersByStatus' => $ordersByStatus,
            'topProducts' => $topProducts,
        ]);
    }

    private function translateStatus(string $status): string
    {
        return match ($status) {
            'delivered' => 'Livrees',
            'processing' => 'En cours',
            'cancelled' => 'Annulees',
            'pending' => 'En attente',
            default => $status,
        };
    }
}
