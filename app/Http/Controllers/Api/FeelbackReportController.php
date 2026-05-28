<?php

namespace App\Http\Controllers\Api;

use App\Models\Department;
use App\Models\FeelbackEntry;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeelbackReportController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'start_date'         => 'nullable|date',
            'end_date'           => 'nullable|date|after_or_equal:start_date',
            'company_id'         => 'nullable|string|exists:companies,id',
            'site_id'            => 'nullable|string|exists:sites,id',
            'department_id'      => 'nullable|string|exists:departments,id',
            'type'               => 'nullable|string|in:global,site,department,period',
            'period_granularity' => 'nullable|string|in:day,week,month',
        ]);

        $startDate    = $request->input('start_date');
        $endDate      = $request->input('end_date');
        $siteId       = $request->input('site_id');
        $departmentId = $request->input('department_id');
        $granularity  = $request->input('period_granularity', 'month');
        $user         = $request->user();

        // For super_admin: use explicit company_id param if provided, otherwise no filter (sees all).
        // For others: always scoped to their active company.
        if ($user->isSuperAdmin()) {
            $activeCompanyId = $request->filled('company_id') ? $request->input('company_id') : null;
        } else {
            $activeCompanyId = $this->resolveActiveCompanyId();
        }
        $deptSiteIds = null;
        if ($departmentId) {
            $deptSiteIds = Site::whereHas('departments', fn ($q) => $q->where('id', $departmentId))->pluck('id');
        }

        // Build a base FeelbackEntry query with all shared filters
        // All column references are qualified to avoid JOIN ambiguity later
        $base = function () use ($activeCompanyId, $startDate, $endDate, $siteId, $deptSiteIds) {
            $q = FeelbackEntry::query();
            if ($activeCompanyId) {
                $q->whereHas('site', fn ($sq) => $sq->where('company_id', $activeCompanyId));
            }
            if ($startDate) {
                $q->whereDate('feelback_entries.created_at', '>=', $startDate);
            }
            if ($endDate) {
                $q->whereDate('feelback_entries.created_at', '<=', $endDate);
            }
            if ($siteId) {
                $q->where('feelback_entries.site_id', $siteId);
            }
            if ($deptSiteIds !== null) {
                $q->whereIn('feelback_entries.site_id', $deptSiteIds);
            }
            return $q;
        };

        // ── Global stats (1 query) ────────────────────────────────────────────
        $globalRow = $base()
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN level = 'bon' THEN 1 ELSE 0 END) as bon"),
                DB::raw("SUM(CASE WHEN level = 'neutre' THEN 1 ELSE 0 END) as neutre"),
                DB::raw("SUM(CASE WHEN level = 'mauvais' THEN 1 ELSE 0 END) as mauvais")
            )
            ->first();

        $totalResponses = (int) ($globalRow->total ?? 0);
        $bonCount       = (int) ($globalRow->bon ?? 0);
        $neutreCount    = (int) ($globalRow->neutre ?? 0);
        $mauvaisCount   = (int) ($globalRow->mauvais ?? 0);

        $bonRate     = $totalResponses > 0 ? round($bonCount / $totalResponses * 100, 1) : 0;
        $neutreRate  = $totalResponses > 0 ? round($neutreCount / $totalResponses * 100, 1) : 0;
        $mauvaisRate = $totalResponses > 0 ? round($mauvaisCount / $totalResponses * 100, 1) : 0;

        // ── By site (1 query + 1 lookup) ─────────────────────────────────────
        $siteRows = $base()
            ->select(
                'feelback_entries.site_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN level = 'bon' THEN 1 ELSE 0 END) as bon"),
                DB::raw("SUM(CASE WHEN level = 'neutre' THEN 1 ELSE 0 END) as neutre"),
                DB::raw("SUM(CASE WHEN level = 'mauvais' THEN 1 ELSE 0 END) as mauvais")
            )
            ->groupBy('feelback_entries.site_id')
            ->get();

        $sitesById = Site::whereIn('id', $siteRows->pluck('site_id'))->pluck('name', 'id');

        $bySite = $siteRows->map(fn ($r) => [
            'siteId'           => (string) $r->site_id,
            'site'             => $sitesById[$r->site_id] ?? '-',
            'totalResponses'   => (int) $r->total,
            'bon'              => (int) $r->bon,
            'neutre'           => (int) $r->neutre,
            'mauvais'          => (int) $r->mauvais,
            'satisfactionRate' => $r->total > 0 ? round($r->bon / $r->total * 100, 1) : 0,
        ])->values()->toArray();

        // ── By department ─────────────────────────────────────────────────────
        // feelback_entries are linked to sites, not departments.
        // We resolve department→site, then reuse the already-computed per-site stats.
        // This avoids JOIN multiplication when a site has multiple departments.
        $siteStatsBySiteId = $siteRows->keyBy('site_id');
        $siteNamesById     = $sitesById; // already fetched above

        $departments = Department::whereIn('site_id', $siteRows->pluck('site_id'))
            ->get(['id', 'name', 'site_id']);

        $byDepartment = $departments->map(function ($dept) use ($siteStatsBySiteId, $siteNamesById) {
            $stats = $siteStatsBySiteId[$dept->site_id] ?? null;
            $total = $stats ? (int) $stats->total : 0;
            $bon   = $stats ? (int) $stats->bon : 0;
            return [
                'departmentId'     => (string) $dept->id,
                'department'       => $dept->name,
                'site'             => $siteNamesById[$dept->site_id] ?? '-',
                'totalResponses'   => $total,
                'bon'              => $bon,
                'neutre'           => $stats ? (int) $stats->neutre : 0,
                'mauvais'          => $stats ? (int) $stats->mauvais : 0,
                'satisfactionRate' => $total > 0 ? round($bon / $total * 100, 1) : 0,
            ];
        })->values()->toArray();

        // ── By period (1 query, PostgreSQL DATE_TRUNC) ───────────────────────
        // Use explicit GROUP BY/ORDER BY expressions to avoid positional ambiguity on PostgreSQL
        [$periodSelectExpr, $periodGroupExpr] = match ($granularity) {
            'day'  => [
                DB::raw("DATE(feelback_entries.created_at) as period"),
                "DATE(feelback_entries.created_at)",
            ],
            'week' => [
                DB::raw("TO_CHAR(DATE_TRUNC('week', feelback_entries.created_at), 'YYYY-WW') as period"),
                "DATE_TRUNC('week', feelback_entries.created_at)",
            ],
            default => [
                DB::raw("TO_CHAR(DATE_TRUNC('month', feelback_entries.created_at), 'YYYY-MM') as period"),
                "DATE_TRUNC('month', feelback_entries.created_at)",
            ],
        };

        $periodRows = $base()
            ->select(
                $periodSelectExpr,
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN level = 'bon' THEN 1 ELSE 0 END) as bon"),
                DB::raw("SUM(CASE WHEN level = 'neutre' THEN 1 ELSE 0 END) as neutre"),
                DB::raw("SUM(CASE WHEN level = 'mauvais' THEN 1 ELSE 0 END) as mauvais")
            )
            ->groupByRaw($periodGroupExpr)
            ->orderByRaw($periodGroupExpr)
            ->get();

        $byPeriod = $periodRows->map(fn ($r) => [
            'period'           => (string) $r->period,
            'totalResponses'   => (int) $r->total,
            'bon'              => (int) $r->bon,
            'neutre'           => (int) $r->neutre,
            'mauvais'          => (int) $r->mauvais,
            'satisfactionRate' => $r->total > 0 ? round($r->bon / $r->total * 100, 1) : 0,
        ])->values()->toArray();

        return $this->successResponse([
            'totalResponses' => $totalResponses,
            'bonRate'        => $bonRate,
            'neutreRate'     => $neutreRate,
            'mauvaisRate'    => $mauvaisRate,
            'bySite'         => $bySite,
            'byDepartment'   => $byDepartment,
            'byPeriod'       => $byPeriod,
        ]);
    }
}
