<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Department\StoreDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends BaseApiController
{
    /**
     * Get paginated list of departments with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Department::withCount('employees');

        $this->scopeByCompany($query);

        $query->when($request->input('site_id'), function ($q, $siteId) {
            $q->where('site_id', $siteId);
        });

        $query->when($request->input('search'), function ($q, $search) {
            $q->where(function ($qq) use ($search) {
                $qq->where('name', 'LIKE', "%{$search}%")
                    ->orWhereHas('site', function ($sq) use ($search) {
                        $sq->where('name', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('company', function ($cq) use ($search) {
                        $cq->where('name', 'LIKE', "%{$search}%");
                    });
            });
        });

        $perPage = (int) $request->input('per_page', 15);
        $departments = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->paginatedResponse(DepartmentResource::collection($departments));
    }

    /**
     * Get a single department by ID.
     */
    public function show(string $id): JsonResponse
    {
        $department = Department::withCount('employees')->findOrFail($id);

        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! $user->isSupportIt()) {
            $companyId = $this->resolveActiveCompanyId();
            if ($companyId && (string) $department->company_id !== (string) $companyId) {
                return $this->errorResponse('Accès non autorisé', 403);
            }
        }

        return $this->resourceResponse(new DepartmentResource($department));
    }

    /**
     * Store a new department.
     */
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $data = $this->enforceCompanyId($request->validated());
        $department = Department::create($data);

        return $this->resourceResponse(new DepartmentResource($department), 'Département créé avec succès', 201);
    }

    /**
     * Update an existing department.
     */
    public function update(UpdateDepartmentRequest $request, string $id): JsonResponse
    {
        $department = Department::findOrFail($id);
        $department->update($request->validated());

        return $this->resourceResponse(new DepartmentResource($department), 'Département mis à jour avec succès');
    }

    /**
     * Delete a department.
     */
    public function destroy(string $id): JsonResponse
    {
        $department = Department::findOrFail($id);
        $department->delete();

        return $this->noContentResponse();
    }
}
