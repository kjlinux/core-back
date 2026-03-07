<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BiometricInconsistencyController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $employeeScope = function ($query) use ($user) {
            if (!$user->isSuperAdmin()) {
                $query->whereHas('employee', function ($q) use ($user) {
                    $q->where('company_id', $user->company_id);
                });
            }
        };

        $doubleBadgeIds = AttendanceRecord::where('is_double_badge', true)
            ->tap($employeeScope)
            ->pluck('id');

        $baseQuery = AttendanceRecord::select('employee_id', 'date')
            ->groupBy('employee_id', 'date')
            ->havingRaw('COUNT(*) > 1');

        if (!$user->isSuperAdmin()) {
            $baseQuery->whereIn('employee_id', function ($q) use ($user) {
                $q->select('id')->from('employees')->where('company_id', $user->company_id);
            });
        }

        $duplicateIds = AttendanceRecord::select('attendance_records.id')
            ->joinSub(
                $baseQuery,
                'duplicates',
                function ($join) {
                    $join->on('attendance_records.employee_id', '=', 'duplicates.employee_id')
                        ->on('attendance_records.date', '=', 'duplicates.date');
                }
            )
            ->pluck('id');

        $allInconsistentIds = $doubleBadgeIds->merge($duplicateIds)->unique();

        $records = AttendanceRecord::with('employee')
            ->whereIn('id', $allInconsistentIds)
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(AttendanceRecordResource::collection($records));
    }
}
