<?php

namespace App\Http\Controllers\Api;

use App\Models\FeelbackEntry;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeelbackStatsController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = FeelbackEntry::query();
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            $filterCompanyId = $request->input('company_id') ?: $this->resolveActiveCompanyId();
            if ($filterCompanyId) {
                $query->whereHas('site', function ($q) use ($filterCompanyId) {
                    $q->where('company_id', $filterCompanyId);
                });
            }
        } else {
            $activeCompanyId = $this->resolveActiveCompanyId();
            $query->whereHas('site', function ($q) use ($activeCompanyId) {
                $q->where('company_id', $activeCompanyId);
            });
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        if ($request->filled('site_id')) {
            $query->where('site_id', $request->site_id);
        }

        $totalResponses = $query->count();
        $bon = (clone $query)->where('level', 'bon')->count();
        $neutre = (clone $query)->where('level', 'neutre')->count();
        $mauvais = (clone $query)->where('level', 'mauvais')->count();
        $satisfactionRate = $totalResponses > 0 ? round(($bon / $totalResponses) * 100, 2) : 0;

        $stats = [
            'period' => [
                'startDate' => $request->input('start_date'),
                'endDate' => $request->input('end_date'),
            ],
            'totalResponses' => $totalResponses,
            'bon' => $bon,
            'neutre' => $neutre,
            'mauvais' => $mauvais,
            'satisfactionRate' => $satisfactionRate,
        ];

        return $this->successResponse($stats);
    }

    public function byAgency(Request $request, string $agencyId): JsonResponse
    {
        $user = $request->user();
        $siteQuery = Site::where('id', $agencyId);
        if (!$user->isSuperAdmin()) {
            $siteQuery->where('company_id', $this->resolveActiveCompanyId());
        }
        $site = $siteQuery->firstOrFail();

        $query = FeelbackEntry::where('site_id', $agencyId);

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $totalResponses = $query->count();
        $bon = (clone $query)->where('level', 'bon')->count();
        $neutre = (clone $query)->where('level', 'neutre')->count();
        $mauvais = (clone $query)->where('level', 'mauvais')->count();
        $satisfactionRate = $totalResponses > 0 ? round(($bon / $totalResponses) * 100, 2) : 0;

        $stats = [
            'siteName' => $site->name,
            'siteId' => (string) $site->id,
            'period' => [
                'startDate' => $request->input('start_date'),
                'endDate' => $request->input('end_date'),
            ],
            'totalResponses' => $totalResponses,
            'bon' => $bon,
            'neutre' => $neutre,
            'mauvais' => $mauvais,
            'satisfactionRate' => $satisfactionRate,
        ];

        return $this->successResponse($stats);
    }

    public function comparison(Request $request): JsonResponse
    {
        $user = $request->user();
        $siteQuery = Site::query();

        if ($user->isSuperAdmin()) {
            $filterCompanyId = $request->input('company_id') ?: $this->resolveActiveCompanyId();
            if ($filterCompanyId) {
                $siteQuery->where('company_id', $filterCompanyId);
            }
        } else {
            $siteQuery->where('company_id', $this->resolveActiveCompanyId());
        }

        $sites = $siteQuery->get();
        $comparison = [];

        foreach ($sites as $site) {
            $query = FeelbackEntry::where('site_id', $site->id);

            if ($request->filled('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            $totalResponses = $query->count();
            $bon = (clone $query)->where('level', 'bon')->count();
            $neutre = (clone $query)->where('level', 'neutre')->count();
            $mauvais = (clone $query)->where('level', 'mauvais')->count();
            $satisfactionRate = $totalResponses > 0 ? round(($bon / $totalResponses) * 100, 2) : 0;

            $comparison[] = [
                'siteName' => $site->name,
                'siteId' => (string) $site->id,
                'totalResponses' => $totalResponses,
                'bon' => $bon,
                'neutre' => $neutre,
                'mauvais' => $mauvais,
                'satisfactionRate' => $satisfactionRate,
            ];
        }

        return $this->successResponse($comparison);
    }
}
