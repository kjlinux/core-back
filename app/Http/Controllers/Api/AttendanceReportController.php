<?php

namespace App\Http\Controllers\Api;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceReportController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'type' => 'nullable|string|in:daily,monthly,late,absence',
            'company_id' => 'nullable|string|exists:companies,id',
            'site_id' => 'nullable|string|exists:sites,id',
            'department_id' => 'nullable|string|exists:departments,id',
        ]);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $type = $request->input('type', 'daily');

        // Build employee query with location filters
        $employeeQuery = Employee::where('is_active', true);
        $this->applyEmployeeFilters($employeeQuery, $request);
        $employeeIds = $employeeQuery->pluck('id');

        // Build attendance query
        $attendanceQuery = AttendanceRecord::with(['employee.department', 'employee.site'])
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$startDate, $endDate]);

        $records = $attendanceQuery->get();

        // Group records by employee
        $rows = $records->groupBy('employee_id')->map(function ($employeeRecords) {
            $employee = $employeeRecords->first()->employee;
            if (!$employee) return null;

            $totalDays = $employeeRecords->count();
            // left_early = présent mais parti avant la fin — compté comme présent
            $presentDays = $employeeRecords->whereIn('status', ['present', 'left_early'])->count();
            $absentDays = $employeeRecords->where('status', 'absent')->count();
            $lateDays = $employeeRecords->where('status', 'late')->count();

            // Overtime = minutes worked beyond scheduled end time
            $overtimeMinutes = $employeeRecords->sum('overtime_minutes');
            $overtime = $overtimeMinutes > 0 ? round($overtimeMinutes / 60, 1) : 0;

            // Taux de présence = (présent + retard + parti tôt) / total jours
            // Un employé en retard est bien venu — il ne doit pas baisser son taux de présence
            $attendedDays = $presentDays + $lateDays;
            $rate = $totalDays > 0
                ? round(($attendedDays / $totalDays) * 100, 1)
                : 0.0;

            return [
                'employeeId' => (string) $employee->id,
                'employee' => $employee->first_name . ' ' . $employee->last_name,
                'department' => $employee->department?->name ?? '-',
                'site' => $employee->site?->name ?? '-',
                'present' => $presentDays,
                'absent' => $absentDays,
                'late' => $lateDays,
                'overtime' => $overtime,
                'rate' => $rate,
            ];
        })->filter()->values()->toArray();

        // Apply type-specific filtering
        if ($type === 'late') {
            $rows = array_values(array_filter($rows, fn ($r) => $r['late'] > 0));
        } elseif ($type === 'absence') {
            $rows = array_values(array_filter($rows, fn ($r) => $r['absent'] > 0));
        }

        // Calculate totals
        $totalPresent = array_sum(array_column($rows, 'present'));
        $totalAbsent = array_sum(array_column($rows, 'absent'));
        $totalLate = array_sum(array_column($rows, 'late'));

        return $this->successResponse([
            'totalEmployees' => count($rows),
            'totalPresent' => $totalPresent,
            'totalAbsent' => $totalAbsent,
            'totalLate' => $totalLate,
            'rows' => $rows,
        ]);
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
