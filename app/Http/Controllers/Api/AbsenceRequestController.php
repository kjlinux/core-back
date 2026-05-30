<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\AbsenceRequestResource;
use App\Models\AbsenceRequest;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class AbsenceRequestController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = AbsenceRequest::with('employee');
        $this->scopeByCompany($query);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }
        if ($request->filled('date_start')) {
            $query->where('date_end', '>=', $request->input('date_start'));
        }
        if ($request->filled('date_end')) {
            $query->where('date_start', '<=', $request->input('date_end'));
        }

        $perPage = (int) $request->input('per_page', 15);
        $items = $query->orderByDesc('created_at')->paginate($perPage);

        return $this->paginatedResponse(AbsenceRequestResource::collection($items));
    }

    public function show(string $id): JsonResponse
    {
        $req = AbsenceRequest::with('employee')->findOrFail($id);
        $this->assertCanAccess($req);

        return $this->resourceResponse(new AbsenceRequestResource($req));
    }

    public function myRequests(Request $request): JsonResponse
    {
        $employeeId = $request->input('employee_id') ?? optional($request->user())->employee_id;
        if (! $employeeId) {
            return $this->errorResponse('Aucun employé associé au compte', 400);
        }
        $items = AbsenceRequest::with('employee')
            ->where('employee_id', $employeeId)
            ->orderByDesc('created_at')
            ->get();

        return $this->successResponse(AbsenceRequestResource::collection($items));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'date_start' => ['required', 'date'],
            'date_end' => ['required', 'date', 'after_or_equal:date_start'],
            'reason' => ['required', 'string'],
            'justificatif' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $employee = Employee::findOrFail($data['employee_id']);
        $data['company_id'] = $employee->company_id;
        $data['status'] = 'pending';

        if ($request->hasFile('justificatif')) {
            $path = $request->file('justificatif')->store('absences', 'public');
            $data['justificatif_url'] = Storage::url($path);
        }
        unset($data['justificatif']);

        $req = AbsenceRequest::create($data);

        return $this->resourceResponse(new AbsenceRequestResource($req->load('employee')), 'Demande créée', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $req = AbsenceRequest::findOrFail($id);
        $this->assertCanAccess($req);

        // L'admin peut modifier tant que la demande est en pending (ou en
        // meme temps qu'une approbation : voir review)
        $data = $request->validate([
            'date_start' => ['sometimes', 'date'],
            'date_end' => ['sometimes', 'date', 'after_or_equal:date_start'],
            'reason' => ['sometimes', 'string'],
        ]);

        if ($req->status !== 'pending') {
            return $this->errorResponse('Seules les demandes en attente peuvent etre modifiees', 422);
        }

        $req->update($data);

        return $this->resourceResponse(new AbsenceRequestResource($req->fresh('employee')), 'Demande mise à jour');
    }

    public function review(Request $request, string $id): JsonResponse
    {
        $req = AbsenceRequest::findOrFail($id);
        $this->assertCanAccess($req);

        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'review_note' => ['nullable', 'string'],
        ]);

        $req->update([
            'status' => $data['status'],
            'review_note' => $data['review_note'] ?? null,
            'reviewed_by' => optional($request->user())->id,
            'reviewed_at' => Carbon::now(),
        ]);

        return $this->resourceResponse(new AbsenceRequestResource($req->fresh('employee')), 'Demande revisee');
    }

    public function destroy(string $id): JsonResponse
    {
        $req = AbsenceRequest::findOrFail($id);
        $this->assertCanAccess($req);
        $req->delete();

        return $this->noContentResponse();
    }

    private function assertCanAccess(AbsenceRequest $req): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(401);
        }
        if ($user->isSuperAdmin() || $user->isSupportIt()) {
            return;
        }
        $companyId = $this->resolveActiveCompanyId();
        if ($companyId && (string) $req->company_id !== (string) $companyId) {
            abort(403, 'Accès non autorisé');
        }
    }
}
