<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

class BaseApiController extends Controller
{
    protected function successResponse($data, string $message = '', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
        ], $status);
    }

    protected function resourceResponse(JsonResource $resource, string $message = '', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $resource,
            'message' => $message,
        ], $status);
    }

    protected function paginatedResponse(ResourceCollection $collection): JsonResponse
    {
        $paginator = $collection->resource;

        return response()->json([
            'data' => $collection,
            'meta' => [
                'currentPage' => $paginator->currentPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'totalPages' => $paginator->lastPage(),
            ],
        ]);
    }

    protected function errorResponse(string $message, int $status = 400, $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    protected function noContentResponse(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => null,
        ]);
    }

    /**
     * Auto-inject company_id from authenticated user for non-super_admin.
     */
    protected function enforceCompanyId(array $data): array
    {
        $user = auth()->user();
        if ($user && !$user->isSuperAdmin() && $user->company_id) {
            $data['company_id'] = $user->company_id;
        }

        return $data;
    }

    /**
     * Scope a query by company_id for non-super_admin users.
     * Super admins can optionally filter by company_id via request param.
     */
    protected function scopeByCompany($query, string $companyIdColumn = 'company_id'): void
    {
        $user = auth()->user();
        if (!$user) return;

        if ($user->isSuperAdmin()) {
            $requestCompanyId = request()->input('company_id');
            if ($requestCompanyId) {
                $query->where($companyIdColumn, $requestCompanyId);
            }
        } else {
            $query->where($companyIdColumn, $user->company_id);
        }
    }
}
