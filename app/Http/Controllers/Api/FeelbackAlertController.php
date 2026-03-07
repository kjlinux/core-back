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
        if (!$user->isSuperAdmin()) {
            $query->whereHas('site', function ($q) use ($user) {
                $q->where('company_id', $user->company_id);
            });
        }

        if ($request->filled('site_id')) {
            $query->where('site_id', $request->site_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('is_read')) {
            $query->where('is_read', filter_var($request->is_read, FILTER_VALIDATE_BOOLEAN));
        }

        $alerts = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(FeelbackAlertResource::collection($alerts));
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'threshold' => 'required|integer',
        ]);

        $threshold = $request->input('threshold');

        cache()->put('feelback_alert_threshold', $threshold);

        return $this->successResponse(
            ['threshold' => $threshold],
            'Parametres mis a jour'
        );
    }
}
