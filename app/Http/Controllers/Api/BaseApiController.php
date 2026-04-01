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
     * Résout le company_id effectif pour la requête en cours.
     * - super_admin : utilise X-Active-Company-Id si fourni, sinon null (voit tout)
     * - technicien  : utilise X-Active-Company-Id s'il l'a sélectionné, sinon son company_id
     * - autres rôles : company_id fixe de l'utilisateur
     */
    public function resolveActiveCompanyIdPublic(): ?string { return $this->resolveActiveCompanyId(); }

    protected function resolveActiveCompanyId(): ?string
    {
        $user = auth()->user();
        if (!$user) return null;

        // Company id passed as query param ?_company_id (header was blocked by CORS on some proxies)
        $activeCompanyId = request()->input('_company_id');

        \Log::info('[resolveActiveCompanyId]', [
            'user_id'          => $user->id,
            'role'             => $user->role,
            'user_company_id'  => $user->company_id,
            '_company_id_param'=> $activeCompanyId,
            'all_params'       => request()->query(),
        ]);

        if ($user->isSuperAdmin()) {
            return $activeCompanyId ?: null;
        }

        if ($user->isTechnicien()) {
            $resolved = $activeCompanyId ?: $user->company_id;
            if (!$resolved) {
                abort(403, 'Technicien: aucune entreprise active selectionnee.');
            }
            return $resolved;
        }

        return $user->company_id;
    }

    /**
     * Auto-inject company_id from authenticated user for non-super_admin.
     */
    protected function enforceCompanyId(array $data): array
    {
        $user = auth()->user();
        if (!$user) return $data;

        if ($user->isSuperAdmin()) {
            // super_admin peut explicitement passer un company_id
            return $data;
        }

        $activeCompanyId = $this->resolveActiveCompanyId();
        if ($activeCompanyId) {
            $data['company_id'] = $activeCompanyId;
        }

        return $data;
    }

    /**
     * Scope a query by company_id for non-super_admin users.
     * Super admins can optionally filter by company_id via request param or X-Active-Company-Id header.
     */
    protected function scopeByCompany($query, string $companyIdColumn = 'company_id'): void
    {
        $user = auth()->user();
        if (!$user) return;

        $activeCompanyId = $this->resolveActiveCompanyId();

        if ($user->isSuperAdmin()) {
            // Filtre optionnel via query param ou header
            $paramCompanyId = request()->input('company_id') ?: $activeCompanyId;
            if ($paramCompanyId) {
                $query->where($companyIdColumn, $paramCompanyId);
            }
        } else {
            if ($activeCompanyId) {
                $query->where($companyIdColumn, $activeCompanyId);
            }
        }
    }
}
