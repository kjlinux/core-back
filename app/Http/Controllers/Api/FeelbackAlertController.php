<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\FeelbackAlertResource;
use App\Models\FeelbackAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeelbackAlertController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = FeelbackAlert::with(['site', 'device']);

        $user = $request->user();
        if (! $user->isSuperAdmin()) {
            $activeCompanyId = $this->resolveActiveCompanyId();
            $query->where('company_id', $activeCompanyId);
        }

        $query->when($request->filled('site_id'), function ($q) use ($request) {
            $q->where('site_id', $request->input('site_id'));
        });

        $query->when($request->filled('type'), function ($q) use ($request) {
            $q->where('type', $request->input('type'));
        });

        $query->when($request->has('is_read'), function ($q) use ($request) {
            $q->where('is_read', filter_var($request->input('is_read'), FILTER_VALIDATE_BOOLEAN));
        });

        $query->when($request->input('search'), function ($q, $search) {
            $q->where(function ($qq) use ($search) {
                $qq->where('message', 'LIKE', "%{$search}%")
                    ->orWhereHas('site', function ($siteQuery) use ($search) {
                        $siteQuery->where('name', 'LIKE', "%{$search}%");
                    });
            });
        });

        $alerts = $query->orderBy('created_at', 'desc')
            ->paginate((int) $request->input('per_page', 15));

        return $this->paginatedResponse(FeelbackAlertResource::collection($alerts));
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'threshold' => 'required|integer',
            'offline_delay_minutes' => 'nullable|integer|min:1',
        ]);

        $threshold = $validated['threshold'];
        cache()->put('feelback_alert_threshold', $threshold);

        $offlineDelayMinutes = $validated['offline_delay_minutes'] ?? null;
        if ($offlineDelayMinutes !== null) {
            cache()->put('feelback_alert_offline_delay_minutes', $offlineDelayMinutes);
        }

        return $this->successResponse(
            [
                'threshold' => $threshold,
                'offline_delay_minutes' => $offlineDelayMinutes,
            ],
            'Paramètres mis à jour'
        );
    }
}
