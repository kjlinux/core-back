<?php

namespace App\Http\Controllers\Api;

use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SupportTicketController extends BaseApiController
{
    // === Côté client (admin_enterprise, manager) ===

    public function clientIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        $tickets = SupportTicket::query()
            ->where('company_id', $user->company_id)
            ->orderByDesc('created_at')
            ->get();
        return $this->successResponse($tickets);
    }

    public function clientStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => 'required|string|max:200',
            'message' => 'required|string|max:5000',
            'priority' => 'nullable|in:low,medium,high',
        ]);

        $user = $request->user();
        if (!$user->company_id) {
            return $this->errorResponse('Utilisateur sans compagnie', 422);
        }

        $ticket = SupportTicket::create([
            'company_id' => $user->company_id,
            'created_by_user_id' => $user->id,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'priority' => $data['priority'] ?? SupportTicket::PRIORITY_MEDIUM,
            'status' => SupportTicket::STATUS_OPEN,
        ]);

        return $this->successResponse($ticket, 'Plainte enregistrée', 201);
    }

    // === Côté support (support_it, super_admin) ===

    public function supportIndex(Request $request): JsonResponse
    {
        $q = SupportTicket::query()->with(['company:id,name,phone,email', 'createdBy:id,first_name,last_name,email,phone']);

        if ($s = $request->input('status')) $q->where('status', $s);
        if ($p = $request->input('priority')) $q->where('priority', $p);
        if ($c = $request->input('company_id')) $q->where('company_id', $c);

        $tickets = $q->orderByDesc('created_at')->get()->map(fn (SupportTicket $t) => [
            'id' => $t->id,
            'subject' => $t->subject,
            'message' => $t->message,
            'priority' => $t->priority,
            'status' => $t->status,
            'supportNotes' => $t->support_notes,
            'createdAt' => $t->created_at?->toIso8601String(),
            'resolvedAt' => $t->resolved_at?->toIso8601String(),
            'company' => $t->company ? [
                'id' => $t->company->id,
                'name' => $t->company->name,
                'phone' => $t->company->phone,
                'email' => $t->company->email,
            ] : null,
            'createdBy' => $t->createdBy ? [
                'id' => $t->createdBy->id,
                'name' => trim(($t->createdBy->first_name ?? '') . ' ' . ($t->createdBy->last_name ?? '')) ?: $t->createdBy->email,
                'email' => $t->createdBy->email,
                'phone' => $t->createdBy->phone,
            ] : null,
        ]);

        return $this->successResponse($tickets);
    }

    public function supportUpdate(Request $request, string $id): JsonResponse
    {
        $ticket = SupportTicket::find($id);
        if (!$ticket) return $this->errorResponse('Ticket introuvable', 404);

        $data = $request->validate([
            'status' => 'nullable|in:open,in_progress,resolved',
            'support_notes' => 'nullable|string|max:5000',
        ]);

        if (isset($data['status'])) {
            $ticket->status = $data['status'];
            if ($data['status'] === SupportTicket::STATUS_RESOLVED) {
                $ticket->resolved_at = now();
                $ticket->resolved_by_user_id = $request->user()->id;
            }
        }
        if (array_key_exists('support_notes', $data)) {
            $ticket->support_notes = $data['support_notes'];
        }
        $ticket->save();

        Log::info('[Support] mise à jour ticket', [
            'support_user_id' => $request->user()->id,
            'ticket_id' => $ticket->id,
            'company_id' => $ticket->company_id,
            'new_status' => $ticket->status,
        ]);

        return $this->successResponse($ticket->fresh());
    }
}
