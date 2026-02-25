<?php

namespace App\Http\Controllers\Api;

use App\Models\Department;
use App\Http\Resources\DepartmentResource;
use App\Http\Requests\Department\StoreDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
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

        $query->when($request->input('company_id'), function ($q, $companyId) {
            $q->where('company_id', $companyId);
        });

        $query->when($request->input('site_id'), function ($q, $siteId) {
            $q->where('site_id', $siteId);
        });

        $perPage = $request->input('perPage', 15);
        $departments = $query->paginate($perPage);

        return $this->paginatedResponse(DepartmentResource::collection($departments));
    }

    /**
     * Get a single department by ID.
     */
    public function show(string $id): JsonResponse
    {
        $department = Department::withCount('employees')->findOrFail($id);

        return $this->resourceResponse(new DepartmentResource($department));
    }

    /**
     * Store a new department.
     */
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = Department::create($request->validated());

        return $this->resourceResponse(new DepartmentResource($department), 'Departement cree avec succes', 201);
    }

    /**
     * Update an existing department.
     */
    public function update(UpdateDepartmentRequest $request, string $id): JsonResponse
    {
        $department = Department::findOrFail($id);
        $department->update($request->validated());

        return $this->resourceResponse(new DepartmentResource($department), 'Departement mis a jour avec succes');
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
