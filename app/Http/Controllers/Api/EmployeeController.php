<?php

namespace App\Http\Controllers\Api;

use App\Models\Employee;
use App\Http\Resources\EmployeeResource;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends BaseApiController
{
    /**
     * Get paginated list of employees with filters and search.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::query();

        $query->when($request->input('company_id'), function ($q, $companyId) {
            $q->where('company_id', $companyId);
        });

        $query->when($request->input('site_id'), function ($q, $siteId) {
            $q->where('site_id', $siteId);
        });

        $query->when($request->input('department_id'), function ($q, $departmentId) {
            $q->where('department_id', $departmentId);
        });

        $query->when($request->has('is_active'), function ($q) use ($request) {
            $q->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        });

        $query->when($request->input('search'), function ($q, $search) {
            $q->where(function ($query) use ($search) {
                $query->where('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('employee_number', 'LIKE', "%{$search}%");
            });
        });

        $perPage = $request->input('perPage', 15);
        $employees = $query->paginate($perPage);

        return $this->paginatedResponse(EmployeeResource::collection($employees));
    }

    /**
     * Get a single employee by ID with RFID card.
     */
    public function show(string $id): JsonResponse
    {
        $employee = Employee::with('rfidCard')->findOrFail($id);

        return $this->resourceResponse(new EmployeeResource($employee));
    }

    /**
     * Store a new employee.
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = Employee::create($request->validated());

        return $this->resourceResponse(new EmployeeResource($employee), 'Employe cree avec succes', 201);
    }

    /**
     * Update an existing employee.
     */
    public function update(UpdateEmployeeRequest $request, string $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $employee->update($request->validated());

        return $this->resourceResponse(new EmployeeResource($employee), 'Employe mis a jour avec succes');
    }

    /**
     * Delete an employee.
     */
    public function destroy(string $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $employee->delete();

        return $this->noContentResponse();
    }

    /**
     * Toggle the active status of an employee.
     */
    public function toggleActive(string $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $employee->update(['is_active' => !$employee->is_active]);

        return $this->resourceResponse(new EmployeeResource($employee), 'Statut de l\'employe mis a jour');
    }
}
