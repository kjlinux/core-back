<?php

namespace App\Http\Controllers\Api;

use App\Models\Site;
use App\Models\TechnicienActivityLog;
use App\Http\Resources\SiteResource;
use App\Http\Resources\DepartmentResource;
use App\Http\Requests\Site\StoreSiteRequest;
use App\Http\Requests\Site\UpdateSiteRequest;
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

        $perPage = $request->input('per_page', 15);
        $sites = $query->paginate($perPage);

        return $this->paginatedResponse(SiteResource::collection($sites));
    }

    /**
     * Get a single site by ID.
     */
    public function show(string $id): JsonResponse
    {
        $site = Site::with('departments')->findOrFail($id);

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

        return $this->resourceResponse(new SiteResource($site), 'Site cree avec succes', 201);
    }

    /**
     * Update an existing site.
     */
    public function update(UpdateSiteRequest $request, string $id): JsonResponse
    {
        $site = Site::findOrFail($id);
        $site->update($request->validated());

        TechnicienActivityLog::record('update', 'site', (string) $site->id, $site->name);

        return $this->resourceResponse(new SiteResource($site), 'Site mis a jour avec succes');
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
