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
        $auditLogs = BiometricAuditLog::orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(BiometricAuditResource::collection($auditLogs));
    }
}
