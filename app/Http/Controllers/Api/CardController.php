<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Card\AssignCardRequest;
use App\Http\Requests\Card\BlockCardRequest;
use App\Http\Requests\Card\StoreCardRequest;
use App\Http\Resources\CardHistoryResource;
use App\Http\Resources\RfidCardResource;
use App\Models\CardHistory;
use App\Models\Employee;
use App\Models\RfidCard;
use App\Models\TechnicienActivityLog;
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

        $this->scopeByCompany($query);

        $query->when($request->input('status'), function ($q, $status) {
            $q->where('status', $status);
        });

        $query->when($request->input('search'), function ($q, $search) {
            $q->where('uid', 'LIKE', "%{$search}%");
        });

        $cards = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(RfidCardResource::collection($cards));
    }

    /**
     * Get a single card by ID.
     */
    public function show(string $id): JsonResponse
    {
        $user = auth()->user();
        $card = RfidCard::with('employee')->findOrFail($id);

        // Non-super_admin ne peut voir que les cartes de sa propre entreprise
        if (! $user->isSuperAdmin() && ! $user->isSupportIt()) {
            $companyId = $this->resolveActiveCompanyId();
            if ($companyId && (string) $card->company_id !== (string) $companyId) {
                return $this->errorResponse('Accès non autorisé', 403);
            }
        }

        return $this->resourceResponse(new RfidCardResource($card));
    }

    /**
     * Store a new RFID card.
     */
    public function store(StoreCardRequest $request): JsonResponse
    {
        $data = $this->enforceCompanyId($request->validated());
        $data['status'] = 'inactive';

        $card = RfidCard::create($data);

        TechnicienActivityLog::record('create', 'card', (string) $card->id, $card->uid);

        return $this->resourceResponse(new RfidCardResource($card), 'Carte RFID créée avec succès', 201);
    }

    /**
     * Assign a card to an employee.
     */
    public function assign(AssignCardRequest $request, string $id): JsonResponse
    {
        $card = RfidCard::findOrFail($id);

        // Cloisonnement multi-tenant : la carte et l'employe doivent appartenir a la meme
        // entreprise. Indispensable pour les roles globaux (super_admin / support_it) dont
        // le findOrFail n'est pas cloisonne — sinon un pointage finirait dans la mauvaise
        // entreprise (cf. garde-fou cote MqttListenRfidCommand).
        $employee = Employee::findOrFail($request->input('employee_id'));
        if ((string) $employee->company_id !== (string) $card->company_id) {
            return $this->errorResponse('La carte et l\'employé doivent appartenir à la même entreprise.', 422);
        }

        $card->update([
            'employee_id' => $request->input('employee_id'),
            'status' => 'active',
            'assigned_at' => now(),
        ]);

        CardHistory::create([
            'card_id' => $card->id,
            'action' => 'assigned',
            'performed_by' => Auth::user()->name,
            'details' => 'Carte assignée à l\'employé #'.$request->input('employee_id'),
        ]);

        $card->load('employee');

        TechnicienActivityLog::record('assign', 'card', (string) $card->id, $card->uid);

        return $this->resourceResponse(new RfidCardResource($card), 'Carte assignée avec succès');
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
            'card_id' => $card->id,
            'action' => 'unassigned',
            'performed_by' => Auth::user()->name,
            'details' => 'Carte désassignée de l\'employé #'.$previousEmployeeId,
        ]);

        return $this->resourceResponse(new RfidCardResource($card), 'Carte désassignée avec succès');
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
            'card_id' => $card->id,
            'action' => 'blocked',
            'performed_by' => Auth::user()->name,
            'details' => 'Carte bloquée. Raison : '.$request->input('block_reason'),
        ]);

        return $this->resourceResponse(new RfidCardResource($card), 'Carte bloquée avec succès');
    }

    /**
     * Unblock a card and set it back to active or inactive depending on assignment.
     */
    public function unblock(string $id): JsonResponse
    {
        $card = RfidCard::findOrFail($id);

        // Une carte sans employe assigne revient a "inactive", pas "active"
        $newStatus = $card->employee_id ? 'active' : 'inactive';

        $card->update([
            'status' => $newStatus,
            'blocked_at' => null,
            'block_reason' => null,
        ]);

        CardHistory::create([
            'card_id' => $card->id,
            'action' => 'activated',
            'performed_by' => Auth::user()->name,
            'details' => 'Carte débloquée et réactivée',
        ]);

        return $this->resourceResponse(new RfidCardResource($card), 'Carte débloquée avec succès');
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
