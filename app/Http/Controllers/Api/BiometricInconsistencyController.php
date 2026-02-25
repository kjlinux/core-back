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
        $doubleBadgeIds = AttendanceRecord::where('is_double_badge', true)
            ->pluck('id');

        $duplicateIds = AttendanceRecord::select('id')
            ->whereIn(
                DB::raw('CONCAT(employee_id, "-", date)'),
                AttendanceRecord::select(DB::raw('CONCAT(employee_id, "-", date)'))
                    ->groupBy('employee_id', 'date')
                    ->havingRaw('COUNT(*) > 1')
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
