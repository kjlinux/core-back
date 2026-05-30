<?php

namespace App\Http\Controllers\Api;

use App\Enums\ExpectedDaysStrategy;
use App\Models\AbsenceRequest;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollConfig;
use App\Models\Schedule;
use App\Services\AttendanceStatsService;
use App\Support\CsvExporter;
use App\Support\ReportPdfRenderer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceReportController extends BaseApiController
{
    public function __construct(private readonly AttendanceStatsService $statsService) {}

    public function index(Request $request): JsonResponse
    {
        $report = $this->buildReport($request);

        return $this->successResponse($report);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $report = $this->buildReport($request);
        $headers = ['Matricule', 'Employé', 'Département', 'Site', 'Présents', 'Absents', 'Retards', 'Heures sup.', 'Taux (%)'];
        $rows = array_map(fn ($r) => [
            $r['employeeId'],
            $r['employee'],
            $r['department'],
            $r['site'],
            $r['present'],
            $r['absent'],
            $r['late'],
            $r['overtime'],
            $r['rate'],
        ], $report['rows']);

        $filename = sprintf(
            'rapport-presence_%s_%s_au_%s.csv',
            $request->input('type', 'daily'),
            $request->input('start_date'),
            $request->input('end_date'),
        );

        return CsvExporter::stream($filename, $headers, $rows);
    }

    public function exportPdf(Request $request): Response
    {
        $report = $this->buildReport($request);
        $headers = ['Matricule', 'Employé', 'Département', 'Site', 'Présents', 'Absents', 'Retards', 'Heures sup.', 'Taux (%)'];
        $rows = array_map(fn ($r) => [
            $r['employeeId'], $r['employee'], $r['department'], $r['site'],
            $r['present'], $r['absent'], $r['late'], $r['overtime'], $r['rate'],
        ], $report['rows']);

        $summary = [
            ['label' => 'Employés', 'value' => $report['totalEmployees']],
            ['label' => 'Présents', 'value' => $report['totalPresent']],
            ['label' => 'Absents', 'value' => $report['totalAbsent']],
            ['label' => 'Retards', 'value' => $report['totalLate']],
        ];

        $subtitle = sprintf('Du %s au %s', $request->input('start_date'), $request->input('end_date'));
        $pdf = ReportPdfRenderer::render('Rapport de présence', $headers, $rows, $summary, $subtitle);

        $filename = sprintf('rapport-presence_%s_au_%s.pdf', $request->input('start_date'), $request->input('end_date'));

        return $pdf->download($filename);
    }

    /**
     * Construit le rapport de présence.
     *
     * Le calcul est piloté uniquement par [start_date, end_date] : le paramètre
     * `type` (daily/monthly) est purement indicatif côté serveur (aucune
     * réécriture des dates), tandis que late/absence filtre les lignes produites.
     * Les jours ouvrés attendus, les absences (dérivées) et le taux de présence
     * sont délégués à AttendanceStatsService.
     */
    private function buildReport(Request $request): array
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'type' => 'nullable|string|in:daily,monthly,late,absence',
            'company_id' => 'nullable|string|exists:companies,id',
            'site_id' => 'nullable|string|exists:sites,id',
            'department_id' => 'nullable|string|exists:departments,id',
            'source' => 'nullable|string|in:rfid,qrcode,biometric,manual,mobile',
        ]);

        $start = Carbon::parse($request->input('start_date'))->startOfDay();
        $end = Carbon::parse($request->input('end_date'))->endOfDay();
        $type = $request->input('type', 'daily');

        $user = $request->user();
        $companyId = $user && $user->isSuperAdmin()
            ? $request->input('company_id')
            : $this->resolveActiveCompanyId();

        // Itérer sur les EMPLOYÉS (et non sur les pointages groupés) : un employé
        // sans aucun pointage doit apparaître en absence totale, pas disparaître.
        $employeeQuery = Employee::where('is_active', true)->with(['department', 'site']);
        $this->applyEmployeeFilters($employeeQuery, $request);
        $employees = $employeeQuery->get();
        $employeeIds = $employees->pluck('id');

        // Tout précharger en une fois (évite les N+1 dans le service pur).
        // `source` (optionnel) restreint le rapport à un canal de pointage donné,
        // ex. biometric pour le rapport biométrique.
        $recordsByEmployee = AttendanceRecord::whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->when($request->filled('source'), fn ($query) => $query->where('source', $request->input('source')))
            ->get()
            ->groupBy('employee_id');

        $leavesByEmployee = AbsenceRequest::where('status', 'approved')
            ->whereIn('employee_id', $employeeIds)
            ->where('date_start', '<=', $end->toDateString())
            ->where('date_end', '>=', $start->toDateString())
            ->get()
            ->groupBy('employee_id');

        $companySchedules = $companyId
            ? Schedule::where('company_id', $companyId)->get()
            : Schedule::query()->get();

        // Les jours fériés sont propres à une entreprise : on ne les applique que
        // si une entreprise est ciblée (le front impose la sélection pour super_admin).
        $companyHolidays = $companyId
            ? Holiday::where('company_id', $companyId)->get()
            : collect();

        $config = $companyId
            ? PayrollConfig::where('company_id', $companyId)->first()
            : null;

        $strategy = ExpectedDaysStrategy::fromConfig(config('attendance.expected_days_strategy'));

        return $this->statsService->buildReport(
            $employees,
            $start,
            $end,
            $recordsByEmployee,
            $companySchedules,
            $companyHolidays,
            $leavesByEmployee,
            $strategy,
            $config,
            $type,
        );
    }

    private function applyEmployeeFilters($query, Request $request): void
    {
        $user = $request->user();
        $companyId = $user->isSuperAdmin() ? $request->input('company_id') : $this->resolveActiveCompanyId();
        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        if ($request->filled('site_id')) {
            $query->where('site_id', $request->input('site_id'));
        }
        if ($request->filled('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }
    }
}
