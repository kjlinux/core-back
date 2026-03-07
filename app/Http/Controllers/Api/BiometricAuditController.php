<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\BiometricAuditResource;
use App\Models\BiometricAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BiometricAuditController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = BiometricAuditLog::query();

        $user = $request->user();
        if (!$user->isSuperAdmin()) {
            $companyUserIds = \App\Models\User::where('company_id', $user->company_id)->pluck('id');
            $query->whereIn('user_id', $companyUserIds);
        }

        $auditLogs = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(BiometricAuditResource::collection($auditLogs));
    }
}
