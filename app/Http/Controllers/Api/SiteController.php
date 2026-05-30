<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Site\StoreSiteRequest;
use App\Http\Requests\Site\UpdateSiteRequest;
use App\Http\Resources\DepartmentResource;
use App\Http\Resources\SiteResource;
use App\Models\Site;
use App\Models\TechnicienActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController extends BaseApiController
{
    /**
     * Get paginated list of sites with optional company filter.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Site::with('departments');

        $this->scopeByCompany($query);

        $query->when($request->input('search'), function ($q, $search) {
            $q->where(function ($qq) use ($search) {
                $qq->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('address', 'LIKE', "%{$search}%")
                    ->orWhereHas('company', function ($cq) use ($search) {
                        $cq->where('name', 'LIKE', "%{$search}%");
                    });
            });
        });

        $perPage = (int) $request->input('per_page', 15);
        $sites = $query->paginate($perPage);

        return $this->paginatedResponse(SiteResource::collection($sites));
    }

    /**
     * Get a single site by ID.
     */
    public function show(string $id): JsonResponse
    {
        $site = Site::with('departments')->findOrFail($id);

        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! $user->isSupportIt()) {
            $companyId = $this->resolveActiveCompanyId();
            if ($companyId && (string) $site->company_id !== (string) $companyId) {
                return $this->errorResponse('Accès non autorisé', 403);
            }
        }

        return $this->resourceResponse(new SiteResource($site));
    }

    /**
     * Store a new site.
     */
    public function store(StoreSiteRequest $request): JsonResponse
    {
        $data = $this->enforceCompanyId($request->validated());
        $site = Site::create($data);

        TechnicienActivityLog::record('create', 'site', (string) $site->id, $site->name);

        return $this->resourceResponse(new SiteResource($site), 'Site créé avec succès', 201);
    }

    /**
     * Update an existing site.
     */
    public function update(UpdateSiteRequest $request, string $id): JsonResponse
    {
        $site = Site::findOrFail($id);
        $site->update($request->validated());

        TechnicienActivityLog::record('update', 'site', (string) $site->id, $site->name);

        return $this->resourceResponse(new SiteResource($site), 'Site mis à jour avec succès');
    }

    /**
     * Delete a site.
     */
    public function destroy(string $id): JsonResponse
    {
        $site = Site::findOrFail($id);
        TechnicienActivityLog::record('delete', 'site', (string) $site->id, $site->name);
        $site->delete();

        return $this->noContentResponse();
    }

    /**
     * Get all departments for a specific site.
     */
    public function departments(string $id): JsonResponse
    {
        $site = Site::findOrFail($id);

        $departments = $site->departments;

        return $this->successResponse(DepartmentResource::collection($departments));
    }
}
