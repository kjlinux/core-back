<?php

namespace App\Http\Controllers\Api;

use App\Models\Company;
use App\Models\SubscriptionPayment;
use App\Services\Subscription\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminSubscriptionController extends BaseApiController
{
    public function __construct(protected SubscriptionService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $companies = Company::query()
            ->select(['id', 'name', 'email', 'subscription', 'subscription_starts_at', 'subscription_expires_at', 'subscription_next_period_paid', 'subscription_next_expires_at', 'warranty_ends_at', 'is_active'])
            ->orderBy('name')
            ->paginate(50);
        return $this->paginatedResponse($companies);
    }

    public function update(Request $request, string $companyId): JsonResponse
    {
        $data = $request->validate([
            'plan_code'        => 'required|in:freemium,garantie,premium',
            'expires_at'       => 'nullable|date',
            'warranty_ends_at' => 'nullable|date',
        ]);

        $company = Company::findOrFail($companyId);
        $expiresAt = isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null;

        try {
            $this->service->adminChange($company, $data['plan_code'], $expiresAt, $request->user());
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        if (array_key_exists('warranty_ends_at', $data)) {
            $company->warranty_ends_at = $data['warranty_ends_at'] ? Carbon::parse($data['warranty_ends_at']) : null;
            if ($data['warranty_ends_at'] && ! $company->warranty_starts_at) {
                $company->warranty_starts_at = now();
            }
            $company->save();
        }

        return $this->successResponse($company->fresh());
    }

    public function analytics(): JsonResponse
    {
        $byPlan = Company::query()
            ->select('subscription', DB::raw('count(*) as total'))
            ->groupBy('subscription')
            ->pluck('total', 'subscription');

        $monthlyRevenue = SubscriptionPayment::query()
            ->where('payment_status', SubscriptionPayment::STATUS_PAID)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('amount_xof');

        $last12 = SubscriptionPayment::query()
            ->select(DB::raw("to_char(created_at, 'YYYY-MM') as month"), DB::raw('sum(amount_xof) as total'))
            ->where('payment_status', SubscriptionPayment::STATUS_PAID)
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return $this->successResponse([
            'by_plan' => $byPlan,
            'revenue_current_month_xof' => $monthlyRevenue,
            'revenue_by_month' => $last12,
        ]);
    }
}
