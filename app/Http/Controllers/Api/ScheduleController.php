<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Schedule\StoreScheduleRequest;
use App\Http\Requests\Schedule\UpdateScheduleRequest;
use App\Http\Resources\ScheduleResource;
use App\Models\Schedule;
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

        $query->when($request->input('search'), function ($q, $search) {
            $q->where(function ($qq) use ($search) {
                $qq->where('name', 'LIKE', "%{$search}%")
                    ->orWhereHas('company', function ($cq) use ($search) {
                        $cq->where('name', 'LIKE', "%{$search}%");
                    });
            });
        });

        $perPage = (int) $request->input('per_page', 15);
        $schedules = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->paginatedResponse(ScheduleResource::collection($schedules));
    }

    /**
     * Get a single schedule by ID.
     */
    public function show(string $id): JsonResponse
    {
        $schedule = Schedule::findOrFail($id);

        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! $user->isSupportIt()) {
            $companyId = $this->resolveActiveCompanyId();
            if ($companyId && (string) $schedule->company_id !== (string) $companyId) {
                return $this->errorResponse('Accès non autorisé', 403);
            }
        }

        return $this->resourceResponse(new ScheduleResource($schedule));
    }

    /**
     * Store a new schedule.
     */
    public function store(StoreScheduleRequest $request): JsonResponse
    {
        $data = $this->enforceCompanyId($request->validated());
        $schedule = Schedule::create($data);

        return $this->resourceResponse(new ScheduleResource($schedule), 'Horaire créé avec succès', 201);
    }

    /**
     * Update an existing schedule.
     */
    public function update(UpdateScheduleRequest $request, string $id): JsonResponse
    {
        $schedule = Schedule::findOrFail($id);

        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! $user->isSupportIt()) {
            $companyId = $this->resolveActiveCompanyId();
            if ($companyId && (string) $schedule->company_id !== (string) $companyId) {
                return $this->errorResponse('Accès non autorisé', 403);
            }
        }

        $schedule->update($request->validated());

        return $this->resourceResponse(new ScheduleResource($schedule), 'Horaire mis à jour avec succès');
    }

    /**
     * Delete a schedule.
     */
    public function destroy(string $id): JsonResponse
    {
        $schedule = Schedule::findOrFail($id);

        $user = auth()->user();
        if (! $user->isSuperAdmin() && ! $user->isSupportIt()) {
            $companyId = $this->resolveActiveCompanyId();
            if ($companyId && (string) $schedule->company_id !== (string) $companyId) {
                return $this->errorResponse('Accès non autorisé', 403);
            }
        }

        $schedule->delete();

        return $this->noContentResponse();
    }
}
