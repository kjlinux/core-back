<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
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
        $query = Order::with('items')
            ->where('company_id', $request->user()->company_id);

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
        $order = Order::with('items')->findOrFail($id);

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
                'order_number' => 'ORD-' . strtoupper(Str::random(8)),
                'company_id' => $data['company_id'] ?? $request->user()->company_id,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'total' => $total,
                'currency' => 'XOF',
                'status' => 'pending',
                'payment_method' => $data['payment_method'] ?? 'mobile_money',
                'payment_status' => 'pending',
                'delivery_address' => $data['delivery_address'] ?? null,
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
        $order = Order::findOrFail($id);

        if ($order->status !== 'pending') {
            return $this->errorResponse('Seules les commandes en attente peuvent etre annulees', 422);
        }

        $order->update(['status' => 'cancelled']);

        return $this->resourceResponse(new OrderResource($order));
    }

    public function initiatePayment(string $id, LigdiCashService $ligdiCash): JsonResponse
    {
        $order = Order::with('items')->findOrFail($id);

        // Paiement manuel : pas de passerelle, juste confirmation en attente
        if ($order->payment_method === 'manual') {
            return $this->successResponse([
                'payment_url' => null,
                'token' => null,
                'message' => 'Commande enregistree. Un administrateur vous contactera pour confirmer le paiement.',
            ]);
        }

        $items = $order->items->map(function ($item) {
            return [
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ];
        })->toArray();

        $result = $ligdiCash->createPayment([
            'amount' => $order->total,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'description' => 'Commande ' . $order->order_number,
            'items' => $items,
        ]);

        if (!$result['success']) {
            // Passerelle indisponible : commande créée, retourner succès avec flag pending
            return $this->successResponse([
                'payment_url' => null,
                'token' => null,
                'pending' => true,
                'message' => 'La passerelle de paiement est temporairement indisponible. Votre commande est enregistree et sera traitee manuellement.',
            ]);
        }

        $order->update(['payment_token' => $result['token']]);

        return $this->successResponse([
            'payment_url' => $result['payment_url'],
            'token' => $result['token'],
            'pending' => false,
        ]);
    }
}
