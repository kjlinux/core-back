<?php

namespace App\Http\Controllers\Api;

use App\Mail\EmployeeCreatedMail;
use App\Models\Employee;
use App\Models\TechnicienActivityLog;
use App\Models\User;
use App\Http\Resources\EmployeeResource;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmployeeController extends BaseApiController
{
    /**
     * Get paginated list of employees with filters and search.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::query();

        $this->scopeByCompany($query);

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

        $perPage = $request->input('per_page', 15);
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
     * Cree automatiquement un compte utilisateur avec le role "employe"
     * et envoie un email avec les identifiants de connexion.
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $data = $this->enforceCompanyId($request->validated());
        $employee = Employee::create($data);

        // Generer un mot de passe temporaire
        $plainPassword = Str::random(12);

        // Creer le compte utilisateur lie a l'employe
        $user = User::create([
            'name'        => $employee->first_name . ' ' . $employee->last_name,
            'first_name'  => $employee->first_name,
            'last_name'   => $employee->last_name,
            'email'       => $employee->email,
            'phone'       => $employee->phone,
            'password'    => Hash::make($plainPassword),
            'role'        => 'employe',
            'company_id'  => $employee->company_id,
            'employee_id' => $employee->id,
            'is_active'   => true,
        ]);

        // Envoyer l'email de bienvenue avec les identifiants
        try {
            Mail::to($employee->email)->send(new EmployeeCreatedMail($employee, $user, $plainPassword));
        } catch (\Exception $e) {
            \Log::error('EmployeeCreatedMail failed for employee ' . $employee->id . ': ' . $e->getMessage());
        }

        TechnicienActivityLog::record('create', 'employee', (string) $employee->id, $employee->full_name);

        return $this->resourceResponse(new EmployeeResource($employee), 'Employe cree avec succes', 201);
    }

    /**
     * Update an existing employee.
     */
    public function update(UpdateEmployeeRequest $request, string $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $employee->update($request->validated());

        TechnicienActivityLog::record('update', 'employee', (string) $employee->id, $employee->first_name . ' ' . $employee->last_name);

        return $this->resourceResponse(new EmployeeResource($employee), 'Employe mis a jour avec succes');
    }

    /**
     * Delete an employee.
     */
    public function destroy(string $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        TechnicienActivityLog::record('delete', 'employee', (string) $employee->id, $employee->first_name . ' ' . $employee->last_name);
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

        TechnicienActivityLog::record(
            $employee->is_active ? 'activate' : 'deactivate',
            'employee',
            (string) $employee->id,
            $employee->first_name . ' ' . $employee->last_name,
        );

        return $this->resourceResponse(new EmployeeResource($employee), 'Statut de l\'employe mis a jour');
    }
}
