<?php

namespace App\Http\Controllers\Api;

use App\Models\Company;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\SiteResource;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use Illuminate\Http\JsonResponse;

class CompanyController extends BaseApiController
{
    /**
     * Get all companies with sites, departments, and employee count.
     * Non-super_admin users only see their own company.
     */
    public function index(): JsonResponse
    {
        $query = Company::with('sites.departments')
            ->withCount('employees');

        $user = auth()->user();
        if ($user->isSuperAdmin()) {
            // super_admin voit toutes les entreprises
        } elseif ($user->isTechnicien()) {
            // technicien voit toutes les entreprises (il intervient chez n'importe quel client)
            // Si un X-Active-Company-Id est précisé, on filtre uniquement sur celle-là
            $headerCompanyId = request()->header('X-Active-Company-Id');
            if ($headerCompanyId) {
                $query->where('id', $headerCompanyId);
            }
        } else {
            // admin_enterprise et manager voient uniquement leur entreprise
            $query->where('id', $user->company_id);
        }

        $companies = $query->get();

        return $this->successResponse(CompanyResource::collection($companies));
    }

    /**
     * Get a single company by ID.
     */
    public function show(string $id): JsonResponse
    {
        $company = Company::with('sites.departments')
            ->withCount('employees')
            ->findOrFail($id);

        return $this->resourceResponse(new CompanyResource($company));
    }

    /**
     * Store a new company.
     */
    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $company = Company::create($request->validated());

        return $this->resourceResponse(new CompanyResource($company), 'Entreprise creee avec succes', 201);
    }

    /**
     * Update an existing company.
     */
    public function update(UpdateCompanyRequest $request, string $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $company->update($request->validated());

        return $this->resourceResponse(new CompanyResource($company), 'Entreprise mise a jour avec succes');
    }

    /**
     * Toggle the active status of a company.
     */
    public function toggleActive(string $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $company->update(['is_active' => !$company->is_active]);

        return $this->resourceResponse(new CompanyResource($company), 'Statut de l\'entreprise mis a jour');
    }

    /**
     * Get all sites for a specific company.
     */
    public function sites(string $id): JsonResponse
    {
        $company = Company::findOrFail($id);

        $sites = $company->sites()->with('departments')->get();

        return $this->successResponse(SiteResource::collection($sites));
    }
}
