<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Services\LigdiCashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentCallbackController extends BaseApiController
{
    public function handle(Request $request, LigdiCashService $ligdiCash): JsonResponse
    {
        $data = $request->all();
        $rawBody = $request->getContent();
        $signature = $request->header('X-LigdiCash-Signature');

        if (! $ligdiCash->verifyCallback($data, $rawBody, $signature)) {
            return $this->errorResponse('Callback invalide', 400);
        }

        $orderId = $data['custom_data']['order_id'] ?? null;

        if (! $orderId) {
            return $this->errorResponse('Commande introuvable dans les donnees du callback', 400);
        }

        $token = $data['token'] ?? null;
        $status = $data['status'] ?? 'unknown';

        // Verrouillage + idempotence dans une transaction
        $result = DB::transaction(function () use ($orderId, $token, $status) {
            /** @var Order|null $order */
            $order = Order::lockForUpdate()->find($orderId);

            if (! $order) {
                return ['code' => 404, 'message' => 'Commande introuvable'];
            }

            // Idempotence : si l'ordre a deja un token de paiement et que c'est le
            // meme, ou si le statut final a deja ete pose, on ne refait rien.
            if ($token && $order->payment_token && $order->payment_token !== $token) {
                Log::warning('LigdiCash callback token mismatch', [
                    'order_id' => $orderId,
                    'stored_token_prefix' => substr((string) $order->payment_token, 0, 8),
                ]);

                return ['code' => 409, 'message' => 'Token de paiement incoherent'];
            }

            if (in_array($order->payment_status, ['paid', 'failed'], true)) {
                return ['code' => 200, 'message' => 'already_processed'];
            }

            if ($status === 'completed') {
                $order->update([
                    'payment_status' => 'paid',
                    'status' => 'confirmed',
                    'payment_token' => $token ?: $order->payment_token,
                ]);
            } elseif ($status === 'failed') {
                $order->update([
                    'payment_status' => 'failed',
                    'payment_token' => $token ?: $order->payment_token,
                ]);
            }

            return ['code' => 200, 'message' => 'ok'];
        });

        if (($result['code'] ?? 200) >= 400) {
            return $this->errorResponse($result['message'], $result['code']);
        }

        return response()->json(['status' => $result['message']], 200);
    }
}
