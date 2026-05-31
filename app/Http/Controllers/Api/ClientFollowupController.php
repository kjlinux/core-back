<?php

namespace App\Http\Controllers\Api;

use App\Models\ClientFollowupCall;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientFollowupController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = ClientFollowupCall::with(['company', 'installationSheet', 'assignee'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('type')) {
            $query->where('call_type', $request->input('type'));
        }
        if ($request->filled('assignee_id')) {
            $query->where('assigned_to_user_id', $request->input('assignee_id'));
        }
        if ($request->filled('overdue')) {
            $query->where('scheduled_at', '<', now())->where('status', ClientFollowupCall::STATUS_PENDING);
        }

        // super_admin et technicien voient tous les followups (cross-company TANGA-interne).
        return $this->paginatedResponse($query->paginate(50));
    }

    public function show(string $id): JsonResponse
    {
        $call = ClientFollowupCall::with(['company', 'installationSheet', 'assignee'])->findOrFail($id);

        return $this->successResponse($call);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'status' => 'sometimes|in:pending,done,skipped,escalated',
            'result' => 'sometimes|nullable|in:ok,partial,problem',
            'usage_rate' => 'sometimes|nullable|numeric|min:0|max:100',
            'satisfaction_score' => 'sometimes|nullable|integer|min:1|max:10',
            'notes' => 'sometimes|nullable|string|max:5000',
            'actions' => 'sometimes|nullable|array',
            'assigned_to_user_id' => 'sometimes|nullable|integer|exists:users,id',
            'called_at' => 'sometimes|nullable|date',
        ]);

        $call = ClientFollowupCall::findOrFail($id);
        $call->fill($data);
        if (($data['status'] ?? null) === 'done' && ! $call->called_at) {
            $call->called_at = now();
        }
        $call->save();

        return $this->successResponse($call->fresh(['company', 'installationSheet', 'assignee']));
    }

    public function escalate(string $id): JsonResponse
    {
        $call = ClientFollowupCall::findOrFail($id);
        $call->status = ClientFollowupCall::STATUS_ESCALATED;
        $call->save();

        return $this->successResponse($call);
    }

    public function dashboard(): JsonResponse
    {
        $byStatus = ClientFollowupCall::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $missingJ2 = ClientFollowupCall::query()
            ->where('call_type', ClientFollowupCall::TYPE_J2)
            ->where('status', ClientFollowupCall::STATUS_PENDING)
            ->where('scheduled_at', '<', now())
            ->count();

        $avgUsage = ClientFollowupCall::query()
            ->where('call_type', ClientFollowupCall::TYPE_J7)
            ->whereNotNull('usage_rate')
            ->avg('usage_rate');

        $avgSatisfaction = ClientFollowupCall::query()
            ->where('call_type', ClientFollowupCall::TYPE_J30)
            ->whereNotNull('satisfaction_score')
            ->avg('satisfaction_score');

        return $this->successResponse([
            'by_status' => $byStatus,
            'missing_j2_overdue' => $missingJ2,
            'avg_usage_j7' => $avgUsage ? round((float) $avgUsage, 1) : null,
            'avg_satisfaction_j30' => $avgSatisfaction ? round((float) $avgSatisfaction, 1) : null,
        ]);
    }
}
