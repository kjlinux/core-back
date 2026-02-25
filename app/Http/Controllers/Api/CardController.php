<?php

namespace App\Http\Controllers\Api;

use App\Models\RfidCard;
use App\Models\CardHistory;
use App\Http\Resources\RfidCardResource;
use App\Http\Resources\CardHistoryResource;
use App\Http\Requests\Card\StoreCardRequest;
use App\Http\Requests\Card\AssignCardRequest;
use App\Http\Requests\Card\BlockCardRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CardController extends BaseApiController
{
    /**
     * Get all cards with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = RfidCard::with('employee');

        $query->when($request->input('company_id'), function ($q, $companyId) {
            $q->where('company_id', $companyId);
        });

        $query->when($request->input('status'), function ($q, $status) {
            $q->where('status', $status);
        });

        $cards = $query->get();

        return $this->successResponse(RfidCardResource::collection($cards));
    }

    /**
     * Get a single card by ID.
     */
    public function show(string $id): JsonResponse
    {
        $card = RfidCard::with('employee')->findOrFail($id);

        return $this->resourceResponse(new RfidCardResource($card));
    }

    /**
     * Store a new RFID card.
     */
    public function store(StoreCardRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = 'inactive';

        $card = RfidCard::create($data);

        return $this->resourceResponse(new RfidCardResource($card), 'Carte RFID creee avec succes', 201);
    }

    /**
     * Assign a card to an employee.
     */
    public function assign(AssignCardRequest $request, string $id): JsonResponse
    {
        $card = RfidCard::findOrFail($id);

        $card->update([
            'employee_id' => $request->input('employee_id'),
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        CardHistory::create([
            'rfid_card_id' => $card->id,
            'action' => 'assigned',
            'performed_by' => Auth::user()->name,
            'details' => 'Carte assignee a l\'employe #' . $request->input('employee_id'),
        ]);

        $card->load('employee');

        return $this->resourceResponse(new RfidCardResource($card), 'Carte assignee avec succes');
    }

    /**
     * Unassign a card from its employee.
     */
    public function unassign(string $id): JsonResponse
    {
        $card = RfidCard::findOrFail($id);

        $previousEmployeeId = $card->employee_id;

        $card->update([
            'employee_id' => null,
            'status' => 'inactive',
            'assigned_at' => null,
        ]);

        CardHistory::create([
            'rfid_card_id' => $card->id,
            'action' => 'unassigned',
            'performed_by' => Auth::user()->name,
            'details' => 'Carte desassignee de l\'employe #' . $previousEmployeeId,
        ]);

        return $this->resourceResponse(new RfidCardResource($card), 'Carte desassignee avec succes');
    }

    /**
     * Block a card.
     */
    public function block(BlockCardRequest $request, string $id): JsonResponse
    {
        $card = RfidCard::findOrFail($id);

        $card->update([
            'status' => 'blocked',
            'blocked_at' => now(),
            'block_reason' => $request->input('block_reason'),
        ]);

        CardHistory::create([
            'rfid_card_id' => $card->id,
            'action' => 'blocked',
            'performed_by' => Auth::user()->name,
            'details' => 'Carte bloquee. Raison: ' . $request->input('block_reason'),
        ]);

        return $this->resourceResponse(new RfidCardResource($card), 'Carte bloquee avec succes');
    }

    /**
     * Unblock a card and set it back to active.
     */
    public function unblock(string $id): JsonResponse
    {
        $card = RfidCard::findOrFail($id);

        $card->update([
            'status' => 'active',
            'blocked_at' => null,
            'block_reason' => null,
        ]);

        CardHistory::create([
            'rfid_card_id' => $card->id,
            'action' => 'activated',
            'performed_by' => Auth::user()->name,
            'details' => 'Carte debloquee et reactivee',
        ]);

        return $this->resourceResponse(new RfidCardResource($card), 'Carte debloquee avec succes');
    }

    /**
     * Get the history of a specific card.
     */
    public function history(string $id): JsonResponse
    {
        $card = RfidCard::findOrFail($id);

        $history = $card->history()->orderBy('created_at', 'desc')->get();

        return $this->successResponse(CardHistoryResource::collection($history));
    }
}
