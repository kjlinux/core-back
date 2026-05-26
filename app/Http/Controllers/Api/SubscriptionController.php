<?php

namespace App\Http\Controllers\Api;

use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends BaseApiController
{
    public function __construct(protected SubscriptionService $service)
    {
    }

    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get();
        return $this->successResponse($plans);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user?->company;
        if (! $company) {
            return $this->errorResponse('Aucune compagnie associee', 404);
        }

        return $this->successResponse([
            'company_id' => $company->id,
            'subscription' => $company->subscription,
            'starts_at' => $company->subscription_starts_at,
            'expires_at' => $company->subscription_expires_at,
            'next_period_paid' => (bool) $company->subscription_next_period_paid,
            'next_expires_at' => $company->subscription_next_expires_at,
            'is_active' => $company->isSubscriptionActive(),
            'warranty_ends_at' => $company->warranty_ends_at,
            'is_warranty_active' => $company->isWarrantyActive(),
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['plan_code' => 'required|string']);
        $user = $request->user();
        $company = $user->company;

        try {
            $result = $this->service->subscribe($company, $data['plan_code'], $user);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse([
            'payment_url' => $result['payment_url'] ?? null,
            'token' => $result['token'] ?? null,
            'payment_id' => $result['payment']?->id,
        ]);
    }

    public function upgrade(Request $request): JsonResponse
    {
        $data = $request->validate(['plan_code' => 'required|string']);
        $user = $request->user();
        $company = $user->company;

        try {
            $result = $this->service->upgrade($company, $data['plan_code'], $user);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse([
            'payment_url' => $result['payment_url'] ?? null,
            'token' => $result['token'] ?? null,
            'payment_id' => $result['payment']?->id,
            'scheduled_at' => $result['scheduled_at'] ?? null,
        ]);
    }

    public function payNextPeriod(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->company;

        try {
            $result = $this->service->payNextPeriod($company, $user);
        } catch (\Throwable $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse([
            'payment_url' => $result['payment_url'] ?? null,
            'token' => $result['token'] ?? null,
            'payment_id' => $result['payment']?->id,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->company;
        $payments = SubscriptionPayment::where('company_id', $company->id)
            ->orderByDesc('created_at')
            ->paginate(20);
        return $this->paginatedResponse($payments);
    }
}
