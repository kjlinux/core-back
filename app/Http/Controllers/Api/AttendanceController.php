<?php

namespace App\Http\Controllers\Api;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Http\Resources\AttendanceRecordResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends BaseApiController
{
    /**
     * Get attendance records for a specific date with stats.
     */
    public function daily(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        $date = $request->input('date');

        $query = AttendanceRecord::with('employee.department')
            ->whereDate('date', $date);

        if ($request->filled('source')) {
            $query->where('source', $request->input('source'));
        }

        $this->applyLocationFilters($query, $request);

        $records = $query->get();

        $employeeQuery = Employee::where('is_active', true);
        $user = $request->user();
        $companyId = $user->isSuperAdmin() ? $request->input('company_id') : $this->resolveActiveCompanyId();
        if ($companyId) {
            $employeeQuery->where('company_id', $companyId);
        }
        if ($request->input('site_id')) {
            $employeeQuery->where('site_id', $request->input('site_id'));
        }
        if ($request->input('department_id')) {
            $employeeQuery->where('department_id', $request->input('department_id'));
        }
        $totalEmployees = $employeeQuery->count();

        $present = $records->whereIn('status', ['present', 'left_early'])->count();
        $absent = $records->where('status', 'absent')->count();
        $late = $records->where('status', 'late')->count();

        return $this->successResponse([
            'date' => $date,
            'totalEmployees' => $totalEmployees,
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'records' => AttendanceRecordResource::collection($records),
        ]);
    }

    /**
     * Get attendance summary for a given month.
     */
    public function monthly(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        $month = $request->input('month');
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();

        $query = AttendanceRecord::with('employee.department')
            ->whereBetween('date', [$startDate, $endDate]);

        $this->applyLocationFilters($query, $request);

        $records = $query->get();

        $summaries = $records->groupBy('employee_id')->map(function ($employeeRecords) {
            $employee = $employeeRecords->first()->employee;
            $totalDays = $employeeRecords->count();
            $presentDays = $employeeRecords->whereIn('status', ['present', 'late', 'left_early'])->count();
            $absentDays = $employeeRecords->where('status', 'absent')->count();
            $lateDays = $employeeRecords->where('status', 'late')->count();
            $totalLateMinutes = $employeeRecords->sum('late_minutes');

            return [
                'employee_id' => $employeeRecords->first()->employee_id,
                'employee_name' => $employee ? $employee->first_name . ' ' . $employee->last_name : null,
                'employee_number' => $employee ? $employee->employee_number : null,
                'department' => $employee && $employee->department ? $employee->department->name : null,
                'totalDays' => $totalDays,
                'presentDays' => $presentDays,
                'absentDays' => $absentDays,
                'lateDays' => $lateDays,
                'totalLateMinutes' => $totalLateMinutes,
            ];
        })->values()->toArray();

        return $this->successResponse([
            'month' => $month,
            'summaries' => $summaries,
        ]);
    }

    /**
     * Get attendance records for a specific employee between dates.
     */
    public function byEmployee(Request $request, string $employeeId): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        Employee::findOrFail($employeeId);

        $records = AttendanceRecord::with('employee.department')
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$request->input('start_date'), $request->input('end_date')])
            ->orderBy('date', 'desc')
            ->get();

        return $this->successResponse(AttendanceRecordResource::collection($records));
    }

    /**
     * Get attendance records for all employees in a department between dates.
     */
    public function byDepartment(Request $request, string $departmentId): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $employeeIds = Employee::where('department_id', $departmentId)->pluck('id');

        $records = AttendanceRecord::with('employee.department')
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$request->input('start_date'), $request->input('end_date')])
            ->orderBy('date', 'desc')
            ->get();

        return $this->successResponse(AttendanceRecordResource::collection($records));
    }

    /**
     * Get attendance summary with extended filters.
     */
    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        $month = $request->input('month');
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $month)->endOfMonth();

        $query = AttendanceRecord::with('employee.department')
            ->whereBetween('date', [$startDate, $endDate]);

        $this->applyLocationFilters($query, $request);

        $records = $query->get();

        $totalRecords = $records->count();
        $presentCount = $records->whereIn('status', ['present', 'late', 'left_early'])->count();
        $absentCount = $records->where('status', 'absent')->count();
        $lateCount = $records->where('status', 'late')->count();

        $employeeSummaries = $records->groupBy('employee_id')->map(function ($employeeRecords) {
            $employee = $employeeRecords->first()->employee;
            $totalDays = $employeeRecords->count();
            $presentDays = $employeeRecords->whereIn('status', ['present', 'late', 'left_early'])->count();
            $absentDays = $employeeRecords->where('status', 'absent')->count();
            $lateDays = $employeeRecords->where('status', 'late')->count();
            $totalLateMinutes = $employeeRecords->sum('late_minutes');

            return [
                'employee_id' => $employeeRecords->first()->employee_id,
                'employee_name' => $employee ? $employee->first_name . ' ' . $employee->last_name : null,
                'employee_number' => $employee ? $employee->employee_number : null,
                'department' => $employee && $employee->department ? $employee->department->name : null,
                'totalDays' => $totalDays,
                'presentDays' => $presentDays,
                'absentDays' => $absentDays,
                'lateDays' => $lateDays,
                'totalLateMinutes' => $totalLateMinutes,
            ];
        })->values()->toArray();

        return $this->successResponse([
            'month' => $month,
            'totalRecords' => $totalRecords,
            'present' => $presentCount,
            'absent' => $absentCount,
            'late' => $lateCount,
            'employees' => $employeeSummaries,
        ]);
    }

    /**
     * Get daily attendance records filtered to biometric source only.
     */
    public function biometric(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        $date = $request->input('date');

        $query = AttendanceRecord::with('employee.department')
            ->whereDate('date', $date)
            ->where('source', 'biometric');

        $this->applyLocationFilters($query, $request);

        $records = $query->get();

        $employeeQuery = Employee::where('is_active', true);
        $user = $request->user();
        $bioCompanyId = $user->isSuperAdmin() ? $request->input('company_id') : $this->resolveActiveCompanyId();
        if ($bioCompanyId) {
            $employeeQuery->where('company_id', $bioCompanyId);
        }
        if ($request->input('site_id')) {
            $employeeQuery->where('site_id', $request->input('site_id'));
        }
        if ($request->input('department_id')) {
            $employeeQuery->where('department_id', $request->input('department_id'));
        }
        $totalEmployees = $employeeQuery->count();

        $present = $records->whereIn('status', ['present', 'left_early'])->count();
        $absent = $records->where('status', 'absent')->count();
        $late = $records->where('status', 'late')->count();
        $doubleBadgeCount = $records->where('is_double_badge', true)->count();

        return $this->successResponse([
            'date' => $date,
            'source' => 'biometric',
            'totalEmployees' => $totalEmployees,
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'doubleBadgeCount' => $doubleBadgeCount,
            'records' => AttendanceRecordResource::collection($records),
        ]);
    }

    /**
     * Apply company, site, and department filters via employee relationships.
     * Non-super_admin users are always scoped to their own company.
     */
    private function applyLocationFilters($query, Request $request): void
    {
        $user = $request->user();
        $companyId = $user->isSuperAdmin() ? $request->input('company_id') : $this->resolveActiveCompanyId();

        if ($companyId || $request->input('site_id') || $request->input('department_id')) {
            $query->whereHas('employee', function ($q) use ($companyId, $request) {
                if ($companyId) {
                    $q->where('company_id', $companyId);
                }
                $q->when($request->input('site_id'), function ($q, $siteId) {
                    $q->where('site_id', $siteId);
                });
                $q->when($request->input('department_id'), function ($q, $departmentId) {
                    $q->where('department_id', $departmentId);
                });
            });
        }
    }
}
