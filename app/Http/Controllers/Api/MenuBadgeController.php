<?php

namespace App\Http\Controllers\Api;

use App\Services\MenuBadgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class MenuBadgeController extends BaseApiController
{
    /**
     * Compteurs "attention" du menu pour l'utilisateur connecte (cache court).
     */
    public function index(MenuBadgeService $service): JsonResponse
    {
        $user = auth()->user();
        $companyId = $this->resolveActiveCompanyId();

        $cacheKey = "menu-badges:{$user->id}:".($companyId ?? 'all');
        $badges = Cache::remember($cacheKey, 45, fn () => $service->forUser($user, $companyId));

        return $this->successResponse($badges);
    }

    /**
     * Marque une section du menu comme consultee : son badge disparait jusqu'a
     * l'arrivee de nouveaux elements.
     */
    public function seen(Request $request, MenuBadgeService $service): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', Rule::in(MenuBadgeService::KEYS)],
        ]);

        $user = auth()->user();
        $service->markSeen($user, $this->resolveActiveCompanyId(), $validated['key']);

        return $this->successResponse(['ok' => true]);
    }
}
