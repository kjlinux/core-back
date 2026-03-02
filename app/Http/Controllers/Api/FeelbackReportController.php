<?php

namespace App\Http\Controllers\Api;

use App\Models\FeelbackEntry;
use App\Models\FeelbackDevice;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeelbackReportController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'site_id' => 'nullable|string|exists:sites,id',
            'type' => 'nullable|string|in:global,site,agent,period',
        ]);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $siteId = $request->input('site_id');

        // Base query
        $query = FeelbackEntry::query();

        if ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }
        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        // Global stats
        $totalResponses = (clone $query)->count();
        $bonCount = (clone $query)->where('level', 'bon')->count();
        $neutreCount = (clone $query)->where('level', 'neutre')->count();
        $mauvaisCount = (clone $query)->where('level', 'mauvais')->count();

        $bonRate = $totalResponses > 0 ? round(($bonCount / $totalResponses) * 100, 1) : 0;
        $neutreRate = $totalResponses > 0 ? round(($neutreCount / $totalResponses) * 100, 1) : 0;
        $mauvaisRate = $totalResponses > 0 ? round(($mauvaisCount / $totalResponses) * 100, 1) : 0;

        // Stats by site
        $sitesQuery = Site::query();
        if ($siteId) {
            $sitesQuery->where('id', $siteId);
        }
        $sites = $sitesQuery->get();

        $bySite = [];
        foreach ($sites as $site) {
            $siteQuery = FeelbackEntry::where('site_id', $site->id);
            if ($startDate) {
                $siteQuery->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $siteQuery->whereDate('created_at', '<=', $endDate);
            }

            $siteTotal = $siteQuery->count();
            if ($siteTotal === 0) continue;

            $siteBon = (clone $siteQuery)->where('level', 'bon')->count();
            $siteNeutre = (clone $siteQuery)->where('level', 'neutre')->count();
            $siteMauvais = (clone $siteQuery)->where('level', 'mauvais')->count();

            $bySite[] = [
                'siteId' => (string) $site->id,
                'site' => $site->name,
                'totalResponses' => $siteTotal,
                'bon' => $siteBon,
                'neutre' => $siteNeutre,
                'mauvais' => $siteMauvais,
                'satisfactionRate' => round(($siteBon / $siteTotal) * 100, 1),
            ];
        }

        // Stats by agent (device)
        $devicesQuery = FeelbackDevice::with('site');
        if ($siteId) {
            $devicesQuery->where('site_id', $siteId);
        }
        $devices = $devicesQuery->get();

        $byAgent = [];
        foreach ($devices as $device) {
            $deviceQuery = FeelbackEntry::where('device_id', $device->id);
            if ($startDate) {
                $deviceQuery->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $deviceQuery->whereDate('created_at', '<=', $endDate);
            }

            $deviceTotal = $deviceQuery->count();
            if ($deviceTotal === 0) continue;

            $deviceBon = (clone $deviceQuery)->where('level', 'bon')->count();
            $deviceNeutre = (clone $deviceQuery)->where('level', 'neutre')->count();
            $deviceMauvais = (clone $deviceQuery)->where('level', 'mauvais')->count();

            $byAgent[] = [
                'agentId' => (string) $device->id,
                'agent' => $device->name ?? $device->serial_number,
                'site' => $device->site?->name ?? '-',
                'totalResponses' => $deviceTotal,
                'bon' => $deviceBon,
                'neutre' => $deviceNeutre,
                'mauvais' => $deviceMauvais,
                'satisfactionRate' => round(($deviceBon / $deviceTotal) * 100, 1),
            ];
        }

        return $this->successResponse([
            'totalResponses' => $totalResponses,
            'bonRate' => $bonRate,
            'neutreRate' => $neutreRate,
            'mauvaisRate' => $mauvaisRate,
            'bySite' => $bySite,
            'byAgent' => $byAgent,
        ]);
    }
}
