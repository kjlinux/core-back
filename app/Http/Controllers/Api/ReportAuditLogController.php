<?php

namespace App\Http\Controllers\Api;

use App\Models\ReportAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportAuditLogController extends BaseApiController
{
    /**
     * Liste paginée des générations de rapports.
     * - super_admin : voit tout (peut filtrer par company_id)
     * - admin_enterprise : scopé à sa propre company
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = ReportAuditLog::query()
            ->with(['user:id,first_name,last_name,email,role', 'company:id,name'])
            ->orderByDesc('created_at');

        $this->scopeByCompany($query);

        if ($request->filled('report_type')) {
            $query->where('report_type', $request->input('report_type'));
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $perPage = min((int) $request->input('per_page', 30), 100);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'perPage'     => $paginator->perPage(),
                'total'       => $paginator->total(),
                'totalPages'  => $paginator->lastPage(),
            ],
        ]);
    }
}
