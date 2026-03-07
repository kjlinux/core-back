<?php

namespace App\Http\Controllers\Api;

use App\Models\Schedule;
use App\Http\Resources\ScheduleResource;
use App\Http\Requests\Schedule\StoreScheduleRequest;
use App\Http\Requests\Schedule\UpdateScheduleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends BaseApiController
{
    /**
     * Get paginated list of schedules with optional company filter.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Schedule::query();

        $this->scopeByCompany($query);

        $perPage = $request->input('per_page', 15);
        $schedules = $query->paginate($perPage);

        return $this->paginatedResponse(ScheduleResource::collection($schedules));
    }

    /**
     * Get a single schedule by ID.
     */
    public function show(string $id): JsonResponse
    {
        $schedule = Schedule::findOrFail($id);

        return $this->resourceResponse(new ScheduleResource($schedule));
    }

    /**
     * Store a new schedule.
     */
    public function store(StoreScheduleRequest $request): JsonResponse
    {
        $data = $this->enforceCompanyId($request->validated());
        $schedule = Schedule::create($data);

        return $this->resourceResponse(new ScheduleResource($schedule), 'Horaire cree avec succes', 201);
    }

    /**
     * Update an existing schedule.
     */
    public function update(UpdateScheduleRequest $request, string $id): JsonResponse
    {
        $schedule = Schedule::findOrFail($id);
        $schedule->update($request->validated());

        return $this->resourceResponse(new ScheduleResource($schedule), 'Horaire mis a jour avec succes');
    }

    /**
     * Delete a schedule.
     */
    public function destroy(string $id): JsonResponse
    {
        $schedule = Schedule::findOrFail($id);
        $schedule->delete();

        return $this->noContentResponse();
    }
}
