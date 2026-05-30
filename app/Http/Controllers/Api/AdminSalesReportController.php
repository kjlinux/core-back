<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\OrderItem;
use App\Support\CsvExporter;
use App\Support\ReportPdfRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminSalesReportController extends BaseApiController
{
    public function exportCsv(Request $request): StreamedResponse
    {
        $payload = $this->buildReport($request);

        $headers = ['Mois', 'Commandes', 'CA (FCFA)'];
        $monthly = collect($payload['revenueByMonth']);
        $rows = $monthly->map(fn ($r) => [
            is_object($r) ? ($r->month ?? '') : ($r['month'] ?? ''),
            is_object($r) ? ($r->orders ?? 0) : ($r['orders'] ?? 0),
            is_object($r) ? ($r->revenue ?? 0) : ($r['revenue'] ?? 0),
        ])->toArray();

        // Lignes de synthèse en tête.
        $summary = [
            ['Total commandes', '', $payload['totalOrders']],
            ['CA total (FCFA)', '', $payload['totalRevenue']],
            ['Panier moyen (FCFA)', '', $payload['averageBasket']],
            ['Commandes en attente', '', $payload['pendingOrders']],
            ['', '', ''],
        ];

        $filename = sprintf(
            'rapport-ventes_%s_au_%s.csv',
            $request->input('start_date', 'tout'),
            $request->input('end_date', 'tout'),
        );

        return CsvExporter::stream($filename, $headers, array_merge($summary, $rows));
    }

    public function exportPdf(Request $request): Response
    {
        $payload = $this->buildReport($request);

        $headers = ['Mois', 'Commandes', 'CA (FCFA)'];
        $rows = collect($payload['revenueByMonth'])->map(fn ($r) => [
            is_object($r) ? ($r->month ?? '') : ($r['month'] ?? ''),
            is_object($r) ? ($r->orders ?? 0) : ($r['orders'] ?? 0),
            is_object($r) ? ($r->revenue ?? 0) : ($r['revenue'] ?? 0),
        ])->toArray();

        $summary = [
            ['label' => 'Total commandes', 'value' => $payload['totalOrders']],
            ['label' => 'CA total', 'value' => number_format((float) $payload['totalRevenue'], 0, ',', ' ').' FCFA'],
            ['label' => 'Panier moyen', 'value' => number_format((float) $payload['averageBasket'], 0, ',', ' ').' FCFA'],
            ['label' => 'En attente', 'value' => $payload['pendingOrders']],
        ];

        $subtitle = sprintf('Du %s au %s', $request->input('start_date', 'debut'), $request->input('end_date', 'fin'));
        $pdf = ReportPdfRenderer::render('Rapport de ventes', $headers, $rows, $summary, $subtitle);

        return $pdf->download(sprintf('rapport-ventes_%s_au_%s.pdf', $request->input('start_date', 'tout'), $request->input('end_date', 'tout')));
    }

    public function index(Request $request): JsonResponse
    {
        return $this->successResponse($this->buildReport($request));
    }

    private function buildReport(Request $request): array
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'company_id' => 'nullable|string|exists:companies,id',
        ]);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $user = $request->user();

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
        $totalOrders = (clone $orderQuery)->count();
        $totalRevenue = (clone $orderQuery)->where('payment_status', 'paid')->sum('total');
        $paidOrders = (clone $orderQuery)->where('payment_status', 'paid')->count();
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
                'name' => $item->product_name,
                'value' => (int) $item->total_revenue,
                'quantity' => (int) $item->total_quantity,
            ]);

        return [
            'totalOrders' => $totalOrders,
            'totalRevenue' => $totalRevenue,
            'averageBasket' => $averageBasket,
            'pendingOrders' => $pendingOrders,
            'revenueByMonth' => $revenueByMonth,
            'ordersByStatus' => $ordersByStatus,
            'topProducts' => $topProducts,
        ];
    }

    private function translateStatus(string $status): string
    {
        return match ($status) {
            'delivered' => 'Livrées',
            'processing' => 'En cours',
            'cancelled' => 'Annulées',
            'pending' => 'En attente',
            default => $status,
        };
    }
}
