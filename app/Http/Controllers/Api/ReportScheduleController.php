<?php

namespace App\Http\Controllers\Api;

use App\Models\ReportSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportScheduleController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = ReportSchedule::query()
            ->with(['user:id,first_name,last_name', 'company:id,name'])
            ->orderByDesc('created_at');

        $this->scopeByCompany($query);

        return $this->successResponse($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatePayload($request);

        $data = $this->enforceCompanyId($data);
        $data['user_id'] = $request->user()->id;

        $schedule = new ReportSchedule($data);
        $schedule->next_run_at = $schedule->computeNextRun();
        $schedule->save();

        return $this->successResponse($schedule, 'Planification créée', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $query = ReportSchedule::whereKey($id);
        $this->scopeByCompany($query);
        $schedule = $query->firstOrFail();

        $data = $this->validatePayload($request, partial: true);
        $schedule->fill($data);

        // Si la fréquence change, on recalcule la prochaine échéance.
        if (array_key_exists('frequency', $data)) {
            $schedule->next_run_at = $schedule->computeNextRun();
        }
        $schedule->save();

        return $this->successResponse($schedule);
    }

    public function destroy(string $id): JsonResponse
    {
        $query = ReportSchedule::whereKey($id);
        $this->scopeByCompany($query);
        $schedule = $query->firstOrFail();
        $schedule->delete();

        return $this->noContentResponse();
    }

    private function validatePayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'report_type' => "$required|string|in:attendance,feelback,sales",
            'format' => 'sometimes|string|in:pdf,csv',
            'frequency' => "$required|string|in:daily,weekly,monthly",
            'filters' => 'sometimes|nullable|array',
            'recipients' => "$required|array|min:1",
            'recipients.*' => 'email',
            'company_id' => 'sometimes|nullable|uuid|exists:companies,id',
            'is_active' => 'sometimes|boolean',
        ]);
    }
}
