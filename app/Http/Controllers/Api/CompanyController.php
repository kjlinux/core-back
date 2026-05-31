<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\SiteResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends BaseApiController
{
    /**
     * Get all companies with sites, departments, and employee count.
     * Non-super_admin users only see their own company.
     * Supports server-side pagination, search and status filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Company::with('sites.departments', 'admin')
            ->withCount('employees');

        $user = auth()->user();
        if ($user->isSuperAdmin()) {
            // super_admin voit toutes les entreprises
        } elseif ($user->isTechnicien()) {
            // technicien voit toutes les entreprises (il intervient chez n'importe quel client)
            // Si __skipCompanyScope n'est pas passé et qu'un _company_id est fourni, on filtre sur celle-là
            $activeCompanyId = $request->input('_company_id');
            if ($activeCompanyId) {
                $query->where('id', $activeCompanyId);
            }
        } else {
            // admin_enterprise et manager voient uniquement leur entreprise
            $query->where('id', $user->company_id);
        }

        $query->when($request->input('search'), function ($q, $search) {
            $q->where(function ($qq) use ($search) {
                $qq->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%");
            });
        });

        $query->when($request->filled('is_active'), function ($q) use ($request) {
            $q->where('is_active', $request->boolean('is_active'));
        });

        $perPage = (int) $request->input('per_page', 15);

        return $this->paginatedResponse(CompanyResource::collection($query->orderByDesc('created_at')->paginate($perPage)));
    }

    /**
     * Get a single company by ID.
     * Non-super_admin users can only see their own company.
     */
    public function show(string $id): JsonResponse
    {
        $user = auth()->user();

        // Non-super_admin ne peut voir que sa propre entreprise
        if (! $user->isSuperAdmin() && ! $user->isTechnicien() && ! $user->isSupportIt()) {
            if ((string) $user->company_id !== (string) $id) {
                return $this->errorResponse('Accès non autorisé', 403);
            }
        }

        $company = Company::with('sites.departments', 'admin')
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

        return $this->resourceResponse(new CompanyResource($company), 'Entreprise créée avec succès', 201);
    }

    /**
     * Update an existing company.
     * super_admin and technicien may update any company; admin_enterprise only its own.
     * The subscription plan can only be changed by super_admin/technicien (warranty-gated
     * billing), never self-upgraded by an admin_enterprise.
     */
    public function update(UpdateCompanyRequest $request, string $id): JsonResponse
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && ! $user->isTechnicien()) {
            if ((string) $user->company_id !== (string) $id) {
                return $this->errorResponse('Accès non autorisé', 403);
            }
        }

        $data = $request->validated();

        if (! $user->isSuperAdmin() && ! $user->isTechnicien()) {
            unset($data['subscription']);
        }

        $company = Company::findOrFail($id);
        $company->update($data);

        return $this->resourceResponse(new CompanyResource($company), 'Entreprise mise à jour avec succès');
    }

    /**
     * Activate the hardware warranty (12 months, auto-renewing).
     * Unlocks the garantie/premium subscription plans for the company admin.
     */
    public function activateWarranty(string $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $company->warranty_starts_at = now();
        $company->warranty_ends_at = now()->addMonths(12);
        $company->warranty_auto_renew = true;
        $company->save();

        return $this->resourceResponse(
            new CompanyResource($company->fresh()),
            'Garantie activée (12 mois, renouvellement automatique)'
        );
    }

    /**
     * Stop the hardware warranty (disables auto-renewal and ends it immediately).
     */
    public function stopWarranty(string $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $company->warranty_auto_renew = false;
        $company->warranty_ends_at = now();
        $company->save();

        return $this->resourceResponse(
            new CompanyResource($company->fresh()),
            'Garantie arrêtée'
        );
    }

    /**
     * Toggle the active status of a company.
     */
    public function toggleActive(string $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $company->update(['is_active' => ! $company->is_active]);

        return $this->resourceResponse(new CompanyResource($company), 'Statut de l\'entreprise mis à jour');
    }

    /**
     * Get all sites for a specific company.
     * Non-super_admin users can only see sites of their own company.
     */
    public function sites(string $id): JsonResponse
    {
        $user = auth()->user();

        if (! $user->isSuperAdmin() && ! $user->isTechnicien() && ! $user->isSupportIt()) {
            if ((string) $user->company_id !== (string) $id) {
                return $this->errorResponse('Accès non autorisé', 403);
            }
        }

        $company = Company::findOrFail($id);

        $sites = $company->sites()->with('departments')->get();

        return $this->successResponse(SiteResource::collection($sites));
    }
}
