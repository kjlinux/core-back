<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\TechnicienActivityLogResource;
use App\Models\TechnicienActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TechnicienActivityController extends BaseApiController
{
    /**
     * Liste les activites des techniciens.
     * Super admin : peut filtrer par company_id et/ou technicien_id.
     * Technicien : voit uniquement ses propres activites sur l'entreprise active.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = TechnicienActivityLog::with(['technicien', 'company'])
            ->orderBy('created_at', 'desc');

        if ($user->isSuperAdmin()) {
            if ($request->filled('company_id')) {
                $query->where('company_id', $request->input('company_id'));
            }
            if ($request->filled('technicien_id')) {
                $query->where('technicien_id', $request->input('technicien_id'));
            }
        } elseif ($user->isTechnicien()) {
            $query->where('technicien_id', $user->id);
            $companyId = $request->input('_company_id') ?? $user->company_id;
            if ($companyId) {
                $query->where('company_id', $companyId);
            }
        } else {
            return $this->errorResponse('Acces refuse', 403);
        }

        if ($request->filled('resource_type')) {
            $query->where('resource_type', $request->input('resource_type'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $perPage = min((int) $request->input('per_page', 50), 200);
        $paginated = $query->paginate($perPage);

        return $this->paginatedResponse(TechnicienActivityLogResource::collection($paginated));
    }

    /**
     * Synthese par technicien pour une entreprise donnee (super_admin uniquement).
     */
    public function summaryByCompany(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin()) {
            return $this->errorResponse('Acces reserve au super admin', 403);
        }

        $request->validate([
            'company_id' => 'required|exists:companies,id',
        ]);

        $companyId = $request->input('company_id');

        // Techniciens ayant travaille sur cette entreprise
        $techniciens = User::where('role', 'technicien')
            ->whereHas('activityLogs', fn($q) => $q->where('company_id', $companyId))
            ->get(['id', 'first_name', 'last_name', 'email']);

        $summary = $techniciens->map(function (User $tech) use ($companyId) {
            $logs = TechnicienActivityLog::where('technicien_id', $tech->id)
                ->where('company_id', $companyId)
                ->selectRaw('resource_type, action, count(*) as count')
                ->groupBy('resource_type', 'action')
                ->get();

            $lastActivity = TechnicienActivityLog::where('technicien_id', $tech->id)
                ->where('company_id', $companyId)
                ->latest()
                ->value('created_at');

            $totalActions = TechnicienActivityLog::where('technicien_id', $tech->id)
                ->where('company_id', $companyId)
                ->count();

            return [
                'technicien' => [
                    'id'        => (string) $tech->id,
                    'fullName'  => trim($tech->first_name . ' ' . $tech->last_name),
                    'email'     => $tech->email,
                ],
                'totalActions'  => $totalActions,
                'lastActivity'  => $lastActivity,
                'breakdown'     => $logs->map(fn($l) => [
                    'resourceType' => $l->resource_type,
                    'action'       => $l->action,
                    'count'        => $l->count,
                ]),
            ];
        });

        return $this->successResponse($summary);
    }

    /**
     * Liste toutes les entreprises sur lesquelles un technicien a travaille (super_admin).
     */
    public function companiesByTechnicien(Request $request, string $technicienId): JsonResponse
    {
        $user = $request->user();
        if (!$user->isSuperAdmin()) {
            return $this->errorResponse('Acces reserve au super admin', 403);
        }

        $companies = TechnicienActivityLog::where('technicien_id', $technicienId)
            ->with('company')
            ->selectRaw('company_id, count(*) as total_actions, max(created_at) as last_activity')
            ->groupBy('company_id')
            ->get()
            ->map(fn($row) => [
                'company' => [
                    'id'   => (string) $row->company_id,
                    'name' => $row->company?->name,
                ],
                'totalActions' => $row->total_actions,
                'lastActivity' => $row->last_activity,
            ]);

        return $this->successResponse($companies);
    }
}
