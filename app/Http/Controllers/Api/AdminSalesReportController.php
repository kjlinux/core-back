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
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'company_id' => 'nullable|string|exists:companies,id',
        ]);

        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');
        $user      = $request->user();

        $orderQuery = Order::query();

        // Company scoping
        if (! $user->isSuperAdmin()) {
            $orderQuery->where('company_id', $this->resolveActiveCompanyId());
        } elseif ($request->filled('company_id')) {
            $orderQuery->where('company_id', $request->input('company_id'));
        }

        if ($startDate) {
            $orderQuery->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $orderQuery->whereDate('created_at', '<=', $endDate);
        }

        // Global stats
        $totalOrders  = (clone $orderQuery)->count();
        $totalRevenue = (clone $orderQuery)->where('payment_status', 'paid')->sum('total');
        $paidOrders   = (clone $orderQuery)->where('payment_status', 'paid')->count();
        $averageBasket = $paidOrders > 0 ? round($totalRevenue / $paidOrders) : 0;
        $pendingOrders = (clone $orderQuery)->where('status', 'pending')->count();

        // Revenue by month — use a single grouped query (same filters as $orderQuery)
        $monthExpr = DB::getDriverName() === 'pgsql'
            ? DB::raw("TO_CHAR(created_at, 'YYYY-MM') as month")
            : DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month");

        $revenueByMonth = (clone $orderQuery)
            ->where('payment_status', 'paid')
            ->select(
                $monthExpr,
                DB::raw('SUM(total) as revenue'),
                DB::raw('COUNT(*) as orders')
            )
            ->groupByRaw('1')
            ->orderByRaw('1')
            ->get();

        // Orders by status
        $ordersByStatus = (clone $orderQuery)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(fn ($item) => ['name' => $this->translateStatus($item->status), 'value' => $item->count]);

        // Top 5 produits par chiffre d'affaires (quantité × prix unitaire)
        $topProducts = OrderItem::query()
            ->whereHas('order', function ($oq) use ($user, $startDate, $endDate, $request) {
                if (! $user->isSuperAdmin()) {
                    $oq->where('company_id', $this->resolveActiveCompanyId());
                } elseif ($request->filled('company_id')) {
                    $oq->where('company_id', $request->input('company_id'));
                }
                if ($startDate) {
                    $oq->whereDate('created_at', '>=', $startDate);
                }
                if ($endDate) {
                    $oq->whereDate('created_at', '<=', $endDate);
                }
            })
            ->select(
                'product_name',
                DB::raw('SUM(quantity) as total_quantity'),
                DB::raw('SUM(quantity * unit_price) as total_revenue')
            )
            ->groupBy('product_name')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'name'     => $item->product_name,
                'value'    => (int) $item->total_revenue,
                'quantity' => (int) $item->total_quantity,
            ]);

        return $this->successResponse([
            'totalOrders'    => $totalOrders,
            'totalRevenue'   => $totalRevenue,
            'averageBasket'  => $averageBasket,
            'pendingOrders'  => $pendingOrders,
            'revenueByMonth' => $revenueByMonth,
            'ordersByStatus' => $ordersByStatus,
            'topProducts'    => $topProducts,
        ]);
    }

    private function translateStatus(string $status): string
    {
        return match ($status) {
            'delivered'  => 'Livrees',
            'processing' => 'En cours',
            'cancelled'  => 'Annulees',
            'pending'    => 'En attente',
            default      => $status,
        };
    }
}
