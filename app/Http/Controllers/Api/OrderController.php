<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Services\LigdiCashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Order::with(['items', 'company']);

        // super_admin voit toutes les commandes (ou filtre par company_id optionnel)
        // Les autres roles voient uniquement les commandes de leur entreprise
        if ($user->isSuperAdmin()) {
            if ($request->filled('company_id')) {
                $query->where('company_id', $request->input('company_id'));
            }
        } else {
            $companyId = $this->resolveActiveCompanyId();
            if ($companyId) {
                $query->where('company_id', $companyId);
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(OrderResource::collection($orders));
    }

    public function show(string $id): JsonResponse
    {
        $user = auth()->user();
        $order = Order::with('items')->findOrFail($id);

        // Verifier que l'utilisateur a le droit de voir cette commande
        if (! $user->isSuperAdmin()) {
            $companyId = $this->resolveActiveCompanyId();
            if ($order->company_id !== $companyId) {
                return $this->errorResponse('Accès non autorisé', 403);
            }
        }

        return $this->resourceResponse(new OrderResource($order));
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $data = $request->validated();

        $order = DB::transaction(function () use ($data, $request) {
            $subtotal = 0;
            $orderItems = [];

            foreach ($data['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                $quantity = $item['quantity'];
                $unitPrice = $product->price;
                $totalPrice = $unitPrice * $quantity;
                $subtotal += $totalPrice;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                    'customization' => $item['customization'] ?? null,
                ];
            }

            $deliveryFee = 2000;
            $total = $subtotal + $deliveryFee;

            $order = Order::create([
                'order_number' => 'ORD-'.strtoupper(Str::random(8)),
                'company_id' => $data['company_id'] ?? $request->user()->company_id,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'total' => $total,
                'currency' => 'XOF',
                'status' => 'pending',
                'payment_method' => $data['payment_method'] ?? 'ligdicash',
                'payment_status' => 'pending',
                'delivery_address' => $data['delivery_address'] ?? [],
            ]);

            foreach ($orderItems as $itemData) {
                $order->items()->create($itemData);
            }

            return $order;
        });

        return $this->resourceResponse(
            new OrderResource($order->load('items')),
            '',
            201
        );
    }

    public function cancel(string $id): JsonResponse
    {
        $user = auth()->user();
        $order = Order::findOrFail($id);

        // Verifier que l'utilisateur a le droit d'annuler cette commande
        if (! $user->isSuperAdmin()) {
            $companyId = $this->resolveActiveCompanyId();
            if ($order->company_id !== $companyId) {
                return $this->errorResponse('Accès non autorisé', 403);
            }
        }

        if ($order->status !== 'pending') {
            return $this->errorResponse('Seules les commandes en attente peuvent etre annulees', 422);
        }

        $order->update(['status' => 'cancelled']);

        return $this->resourceResponse(new OrderResource($order));
    }

    public function initiatePayment(string $id, LigdiCashService $gateway): JsonResponse
    {
        $user = auth()->user();
        $order = Order::with('items')->findOrFail($id);

        // Verifier que l'utilisateur a le droit d'acceder a cette commande
        if (! $user->isSuperAdmin()) {
            $companyId = $this->resolveActiveCompanyId();
            if ($order->company_id !== $companyId) {
                return $this->errorResponse('Accès non autorisé', 403);
            }
        }

        // Paiement manuel : pas de passerelle, juste confirmation en attente
        if ($order->payment_method === 'manual') {
            return $this->successResponse([
                'payment_url' => null,
                'token' => null,
                'message' => 'Commande enregistrée. Un administrateur vous contactera pour confirmer le paiement.',
            ]);
        }

        $items = $order->items->map(function ($item) {
            return [
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ];
        })->toArray();

        // La passerelle attend que la somme des lignes egale le montant total facture.
        // On ajoute donc explicitement les frais de livraison comme ligne dediee.
        if ($order->delivery_fee > 0) {
            $items[] = [
                'name' => 'Frais de livraison',
                'quantity' => 1,
                'unit_price' => $order->delivery_fee,
            ];
        }

        $result = $gateway->createPayment([
            'amount' => $order->total,
            'reference' => $order->id,
            'description' => 'Commande '.$order->order_number,
            'type' => 'order',
            'items' => $items,
            'customer' => [
                'firstname' => $user->first_name ?? '',
                'lastname' => $user->last_name ?? '',
                'email' => $user->email ?? null,
                'phone' => $order->delivery_address['phone'] ?? null,
            ],
            'custom_data' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
        ]);

        if (! ($result['success'] ?? false)) {
            // Passerelle indisponible : commande créée, retourner succès avec flag pending
            return $this->successResponse([
                'payment_url' => null,
                'token' => null,
                'pending' => true,
                'message' => 'La passerelle de paiement est temporairement indisponible. Votre commande est enregistrée et sera traitée manuellement.',
            ]);
        }

        $order->update(['payment_token' => $result['token'] ?? null]);

        return $this->successResponse([
            'payment_url' => $result['payment_url'] ?? null,
            'token' => $result['token'] ?? null,
            'pending' => false,
        ]);
    }
}
