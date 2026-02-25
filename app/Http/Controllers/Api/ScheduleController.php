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

        $query->when($request->input('company_id'), function ($q, $companyId) {
            $q->where('company_id', $companyId);
        });

        $perPage = $request->input('perPage', 15);
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
        $schedule = Schedule::create($request->validated());

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
