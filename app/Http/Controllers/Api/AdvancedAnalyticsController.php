<?php

namespace App\Http\Controllers\Api;

use App\Models\AttendanceRecord;
use App\Models\Department;
use App\Models\Employee;
use App\Models\FeelbackEntry;
use App\Models\Site;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Analytics avances — fonctionnalite Premium (feature advanced_analytics).
 *
 * Agregats sur les 6 derniers mois, scopes par compagnie active (super_admin sans
 * compagnie active = vue globale). Toutes les statistiques sont calculees via des
 * requetes groupees (pas de N+1).
 */
class AdvancedAnalyticsController extends BaseApiController
{
    private const PRESENT_STATUSES = ['present', 'left_early'];

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $isSuperAdmin = $user->isSuperAdmin();
        $companyId = $isSuperAdmin ? null : $this->resolveActiveCompanyId();

        $months = (int) $request->integer('months', 6);
        $months = max(3, min(12, $months));

        $start = now()->subMonths($months - 1)->startOfMonth();

        return $this->successResponse([
            'period' => [
                'months' => $months,
                'start' => $start->toDateString(),
                'end' => now()->toDateString(),
            ],
            'monthlyAttendance' => $this->monthlyAttendance($companyId, $start),
            'punctuality' => $this->punctuality($companyId, $start),
            'attendanceByDepartment' => $this->attendanceByDepartment($companyId, $start),
            'attendanceBySite' => $this->attendanceBySite($companyId, $start),
            'headcountEvolution' => $this->headcountEvolution($companyId, $start, $months),
            'satisfactionByMonth' => $this->satisfactionByMonth($companyId, $start),
        ]);
    }

    /**
     * Expression SQL "annee-mois" portable PostgreSQL / MySQL (alignee sur DashboardController).
     */
    private function monthExpr(string $column = 'created_at'): string
    {
        return DB::getDriverName() === 'pgsql'
            ? "TO_CHAR($column, 'YYYY-MM')"
            : "DATE_FORMAT($column, '%Y-%m')";
    }

    /**
     * @return array<int, array{label:string, ym:string}>
     */
    private function monthBuckets(Carbon $start): array
    {
        $buckets = [];
        $cursor = $start->copy();
        $end = now()->startOfMonth();

        while ($cursor->lte($end)) {
            $buckets[] = [
                'label' => $cursor->locale('fr')->isoFormat('MMM YY'),
                'ym' => $cursor->format('Y-m'),
            ];
            $cursor->addMonth();
        }

        return $buckets;
    }

    /**
     * Presents / retards / absents par mois.
     */
    private function monthlyAttendance(?string $companyId, Carbon $start): array
    {
        $expr = $this->monthExpr('attendance_records.date');

        $base = AttendanceRecord::query()
            ->where('attendance_records.date', '>=', $start->toDateString())
            // Borne supérieure : ne jamais agréger des pointages datés dans le futur.
            ->where('attendance_records.date', '<=', now()->toDateString());
        if ($companyId) {
            $base->whereHas('employee', fn ($q) => $q->where('company_id', $companyId));
        }

        $rows = (clone $base)
            ->selectRaw("$expr as ym, attendance_records.status as status, COUNT(*) as total")
            ->groupByRaw("$expr, attendance_records.status")
            ->get();

        $present = [];
        $late = [];
        $absent = [];
        foreach ($rows as $row) {
            if (in_array($row->status, self::PRESENT_STATUSES, true)) {
                $present[$row->ym] = ($present[$row->ym] ?? 0) + (int) $row->total;
            } elseif ($row->status === 'late') {
                $late[$row->ym] = (int) $row->total;
            } elseif ($row->status === 'absent') {
                $absent[$row->ym] = (int) $row->total;
            }
        }

        return collect($this->monthBuckets($start))->map(fn ($b) => [
            'label' => $b['label'],
            'present' => (int) ($present[$b['ym']] ?? 0),
            'late' => (int) ($late[$b['ym']] ?? 0),
            'absent' => (int) ($absent[$b['ym']] ?? 0),
        ])->all();
    }

    /**
     * Taux de ponctualité = arrivées à l'heure / (arrivées à l'heure + retards).
     *
     * « À l'heure » = PRESENT_STATUSES (present + left_early). Un départ anticipé
     * (left_early) reste une ARRIVÉE ponctuelle, donc il compte au numérateur ; la
     * ponctualité mesure l'heure d'arrivée, pas la durée de présence. Les absences
     * et congés sont exclus des deux côtés (ni présent, ni en retard).
     */
    private function punctuality(?string $companyId, Carbon $start): array
    {
        $base = AttendanceRecord::query()
            ->where('date', '>=', $start->toDateString())
            ->where('date', '<=', now()->toDateString());
        if ($companyId) {
            $base->whereHas('employee', fn ($q) => $q->where('company_id', $companyId));
        }

        $present = (clone $base)->whereIn('status', self::PRESENT_STATUSES)->count();
        $late = (clone $base)->where('status', 'late')->count();
        $total = $present + $late;

        return [
            'present' => $present,
            'late' => $late,
            'punctualityRate' => $total > 0 ? round(($present / $total) * 100, 1) : 0.0,
        ];
    }

    /**
     * Comparaison presence/retards par departement sur la periode.
     */
    private function attendanceByDepartment(?string $companyId, Carbon $start): array
    {
        $deptQuery = Department::query();
        if ($companyId) {
            $deptQuery->where('company_id', $companyId);
        }
        $departments = $deptQuery->get(['id', 'name']);
        if ($departments->isEmpty()) {
            return [];
        }

        $rows = AttendanceRecord::query()
            ->join('employees', 'attendance_records.employee_id', '=', 'employees.id')
            ->where('attendance_records.date', '>=', $start->toDateString())
            ->where('attendance_records.date', '<=', now()->toDateString())
            ->whereIn('employees.department_id', $departments->pluck('id'))
            ->when($companyId, fn ($q) => $q->where('employees.company_id', $companyId))
            ->groupBy('employees.department_id', 'attendance_records.status')
            ->selectRaw('employees.department_id as dept_id, attendance_records.status as status, COUNT(*) as total')
            ->get();

        $byDept = [];
        foreach ($rows as $row) {
            $byDept[$row->dept_id] ??= ['present' => 0, 'late' => 0, 'absent' => 0];
            if (in_array($row->status, self::PRESENT_STATUSES, true)) {
                $byDept[$row->dept_id]['present'] += (int) $row->total;
            } elseif ($row->status === 'late') {
                $byDept[$row->dept_id]['late'] += (int) $row->total;
            } elseif ($row->status === 'absent') {
                $byDept[$row->dept_id]['absent'] += (int) $row->total;
            }
        }

        return $departments->map(fn ($d) => [
            'label' => $d->name,
            'present' => $byDept[$d->id]['present'] ?? 0,
            'late' => $byDept[$d->id]['late'] ?? 0,
            'absent' => $byDept[$d->id]['absent'] ?? 0,
        ])->all();
    }

    /**
     * Comparaison presence/retards par site sur la periode.
     */
    private function attendanceBySite(?string $companyId, Carbon $start): array
    {
        $siteQuery = Site::query();
        if ($companyId) {
            $siteQuery->where('company_id', $companyId);
        }
        $sites = $siteQuery->get(['id', 'name']);
        if ($sites->isEmpty()) {
            return [];
        }

        $rows = AttendanceRecord::query()
            ->join('employees', 'attendance_records.employee_id', '=', 'employees.id')
            ->where('attendance_records.date', '>=', $start->toDateString())
            ->where('attendance_records.date', '<=', now()->toDateString())
            ->whereIn('employees.site_id', $sites->pluck('id'))
            ->when($companyId, fn ($q) => $q->where('employees.company_id', $companyId))
            ->groupBy('employees.site_id', 'attendance_records.status')
            ->selectRaw('employees.site_id as site_id, attendance_records.status as status, COUNT(*) as total')
            ->get();

        $bySite = [];
        foreach ($rows as $row) {
            $bySite[$row->site_id] ??= ['present' => 0, 'late' => 0, 'absent' => 0];
            if (in_array($row->status, self::PRESENT_STATUSES, true)) {
                $bySite[$row->site_id]['present'] += (int) $row->total;
            } elseif ($row->status === 'late') {
                $bySite[$row->site_id]['late'] += (int) $row->total;
            } elseif ($row->status === 'absent') {
                $bySite[$row->site_id]['absent'] += (int) $row->total;
            }
        }

        return $sites->map(fn ($s) => [
            'label' => $s->name,
            'present' => $bySite[$s->id]['present'] ?? 0,
            'late' => $bySite[$s->id]['late'] ?? 0,
            'absent' => $bySite[$s->id]['absent'] ?? 0,
        ])->all();
    }

    /**
     * Evolution de l'effectif actif : nouvelles embauches par mois + effectif cumule.
     */
    private function headcountEvolution(?string $companyId, Carbon $start, int $months): array
    {
        $expr = $this->monthExpr('created_at');

        $base = Employee::query();
        if ($companyId) {
            $base->where('company_id', $companyId);
        }

        // « Effectif actif » (cf. nom de la méthode) : on ne compte que les
        // employés actifs, sinon la courbe ne décroît jamais (départs ignorés).
        $newByMonth = (clone $base)
            ->where('is_active', true)
            ->where('created_at', '>=', $start)
            ->selectRaw("$expr as ym, COUNT(*) as total")
            ->groupByRaw($expr)
            ->pluck('total', 'ym');

        // Effectif present au debut de la periode (embauches anterieures).
        $baseline = (clone $base)->where('is_active', true)->where('created_at', '<', $start)->count();

        $running = $baseline;

        return collect($this->monthBuckets($start))->map(function ($b) use (&$running, $newByMonth) {
            $new = (int) ($newByMonth[$b['ym']] ?? 0);
            $running += $new;

            return [
                'label' => $b['label'],
                'newHires' => $new,
                'headcount' => $running,
            ];
        })->all();
    }

    /**
     * Taux de satisfaction (feelback "bon") par mois sur la periode.
     */
    private function satisfactionByMonth(?string $companyId, Carbon $start): array
    {
        $expr = $this->monthExpr('created_at');

        $base = FeelbackEntry::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<=', now());
        if ($companyId) {
            $base->whereHas('site', fn ($q) => $q->where('company_id', $companyId));
        }

        $totals = (clone $base)
            ->selectRaw("$expr as ym, COUNT(*) as total")
            ->groupByRaw($expr)
            ->pluck('total', 'ym');

        $bons = (clone $base)->where('level', 'bon')
            ->selectRaw("$expr as ym, COUNT(*) as total")
            ->groupByRaw($expr)
            ->pluck('total', 'ym');

        return collect($this->monthBuckets($start))->map(function ($b) use ($totals, $bons) {
            $total = (int) ($totals[$b['ym']] ?? 0);
            $bon = (int) ($bons[$b['ym']] ?? 0);

            return [
                'label' => $b['label'],
                'total' => $total,
                'satisfactionRate' => $total > 0 ? round(($bon / $total) * 100, 1) : 0.0,
            ];
        })->all();
    }
}
