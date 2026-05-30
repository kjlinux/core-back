<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Holiday\StoreHolidayRequest;
use App\Http\Requests\Holiday\UpdateHolidayRequest;
use App\Http\Resources\HolidayResource;
use App\Models\Holiday;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HolidayController extends BaseApiController
{
    /**
     * Get paginated list of holidays with optional company filter.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Holiday::query();

        $this->scopeByCompany($query);

        $query->when($request->input('search'), function ($q, $search) {
            $q->where('name', 'LIKE', "%{$search}%");
        });

        $query->when($request->input('year'), function ($q, $year) {
            $q->whereYear('date', $year);
        });

        $perPage = (int) $request->input('per_page', 15);
        $holidays = $query->paginate($perPage);

        return $this->paginatedResponse(HolidayResource::collection($holidays));
    }

    /**
     * Store a new holiday.
     */
    public function store(StoreHolidayRequest $request): JsonResponse
    {
        $data = $this->enforceCompanyId($request->validated());
        $holiday = Holiday::create($data);

        return $this->resourceResponse(new HolidayResource($holiday), 'Jour férié créé avec succès', 201);
    }

    /**
     * Update an existing holiday.
     */
    public function update(UpdateHolidayRequest $request, string $id): JsonResponse
    {
        $holiday = Holiday::findOrFail($id);

        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! $user->isSupportIt()) {
            $companyId = $this->resolveActiveCompanyId();
            if ($companyId && (string) $holiday->company_id !== (string) $companyId) {
                return $this->errorResponse('Accès non autorisé', 403);
            }
        }

        $holiday->update($request->validated());

        return $this->resourceResponse(new HolidayResource($holiday), 'Jour férié mis à jour avec succès');
    }

    /**
     * Delete a holiday.
     */
    public function destroy(string $id): JsonResponse
    {
        $holiday = Holiday::findOrFail($id);

        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! $user->isSupportIt()) {
            $companyId = $this->resolveActiveCompanyId();
            if ($companyId && (string) $holiday->company_id !== (string) $companyId) {
                return $this->errorResponse('Accès non autorisé', 403);
            }
        }

        $holiday->delete();

        return $this->noContentResponse();
    }
}
