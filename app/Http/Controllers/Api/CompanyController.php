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
     */
    public function index(): JsonResponse
    {
        $companies = Company::with('sites.departments')
            ->withCount('employees')
            ->get();

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
