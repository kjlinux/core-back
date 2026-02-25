<?php

namespace App\Http\Controllers\Api;

use App\Models\Holiday;
use App\Http\Resources\HolidayResource;
use App\Http\Requests\Holiday\StoreHolidayRequest;
use App\Http\Requests\Holiday\UpdateHolidayRequest;
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

        $query->when($request->input('company_id'), function ($q, $companyId) {
            $q->where('company_id', $companyId);
        });

        $perPage = $request->input('perPage', 15);
        $holidays = $query->paginate($perPage);

        return $this->paginatedResponse(HolidayResource::collection($holidays));
    }

    /**
     * Store a new holiday.
     */
    public function store(StoreHolidayRequest $request): JsonResponse
    {
        $holiday = Holiday::create($request->validated());

        return $this->resourceResponse(new HolidayResource($holiday), 'Jour ferie cree avec succes', 201);
    }

    /**
     * Update an existing holiday.
     */
    public function update(UpdateHolidayRequest $request, string $id): JsonResponse
    {
        $holiday = Holiday::findOrFail($id);
        $holiday->update($request->validated());

        return $this->resourceResponse(new HolidayResource($holiday), 'Jour ferie mis a jour avec succes');
    }

    /**
     * Delete a holiday.
     */
    public function destroy(string $id): JsonResponse
    {
        $holiday = Holiday::findOrFail($id);
        $holiday->delete();

        return $this->noContentResponse();
    }
}
