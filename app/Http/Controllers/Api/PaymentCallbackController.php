<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Services\LigdiCashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentCallbackController extends BaseApiController
{
    public function handle(Request $request, LigdiCashService $ligdiCash): JsonResponse
    {
        $data = $request->all();

        if (!$ligdiCash->verifyCallback($data)) {
            return $this->errorResponse('Callback invalide', 400);
        }

        $orderId = $data['custom_data']['order_id'] ?? null;

        if (!$orderId) {
            return $this->errorResponse('Commande introuvable dans les donnees du callback', 400);
        }

        $order = Order::find($orderId);

        if (!$order) {
            return $this->errorResponse('Commande introuvable', 404);
        }

        $status = $data['status'] ?? 'unknown';

        if ($status === 'completed') {
            $order->update([
                'payment_status' => 'paid',
                'status' => 'confirmed',
            ]);
        } elseif ($status === 'failed') {
            $order->update([
                'payment_status' => 'failed',
            ]);
        }

        return response()->json(['status' => 'ok'], 200);
    }
}
