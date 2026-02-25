<?php

namespace App\Http\Controllers\Api;

use App\Models\Site;
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

        $query->when($request->input('company_id'), function ($q, $companyId) {
            $q->where('company_id', $companyId);
        });

        $perPage = $request->input('perPage', 15);
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
        $site = Site::create($request->validated());

        return $this->resourceResponse(new SiteResource($site), 'Site cree avec succes', 201);
    }

    /**
     * Update an existing site.
     */
    public function update(UpdateSiteRequest $request, string $id): JsonResponse
    {
        $site = Site::findOrFail($id);
        $site->update($request->validated());

        return $this->resourceResponse(new SiteResource($site), 'Site mis a jour avec succes');
    }

    /**
     * Delete a site.
     */
    public function destroy(string $id): JsonResponse
    {
        $site = Site::findOrFail($id);
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
