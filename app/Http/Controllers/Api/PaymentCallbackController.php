<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\SubscriptionPayment;
use App\Services\LigdiCashService;
use App\Services\Payment\IntouchService;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentCallbackController extends BaseApiController
{
    /**
     * Callback LigdiCash — gere a la fois les commandes marketplace et les paiements d'abonnement,
     * distingues via custom_data.type.
     */
    public function handle(Request $request, LigdiCashService $ligdiCash, SubscriptionService $subs): JsonResponse
    {
        $data = $request->all();
        $rawBody = $request->getContent();
        $signature = $request->header('X-LigdiCash-Signature');

        if (! $ligdiCash->verifyCallback($data, $rawBody, $signature)) {
            return $this->errorResponse('Callback invalide', 400);
        }

        $token = $data['token'] ?? null;
        $normalized = $ligdiCash->normalizeStatus((string) ($data['status'] ?? ''));
        $type = $data['custom_data']['type'] ?? 'order';

        if ($type === 'subscription_payment') {
            return $this->processSubscriptionCallback($data, $token, $normalized, $subs);
        }

        return $this->processOrderCallback(
            orderId: $data['custom_data']['order_id'] ?? $data['custom_data']['reference'] ?? null,
            token: $token,
            normalizedStatus: $normalized,
        );
    }

    /**
     * Callback InTouch — gere a la fois les commandes marketplace et les paiements d'abonnement,
     * distingues via custom_data.type.
     */
    public function intouch(Request $request, IntouchService $intouch, SubscriptionService $subs): JsonResponse
    {
        $data = $request->all();
        $rawBody = $request->getContent();
        $signature = $request->header('X-InTouch-Signature');

        if (! $intouch->verifyCallback($data, $rawBody, $signature)) {
            return $this->errorResponse('Callback invalide', 400);
        }

        $token = $data['token'] ?? $data['transaction_id'] ?? null;
        $rawStatus = (string) ($data['status'] ?? '');
        $normalized = $intouch->normalizeStatus($rawStatus);
        $type = $data['custom_data']['type'] ?? 'order';

        if ($type === 'subscription_payment') {
            return $this->processSubscriptionCallback($data, $token, $normalized, $subs);
        }

        return $this->processOrderCallback(
            orderId: $data['custom_data']['order_id'] ?? $data['custom_data']['reference'] ?? null,
            token: $token,
            normalizedStatus: $normalized,
        );
    }

    protected function processOrderCallback(?string $orderId, ?string $token, string $normalizedStatus): JsonResponse
    {
        if (! $orderId) {
            return $this->errorResponse('Commande introuvable dans les donnees du callback', 400);
        }

        $result = DB::transaction(function () use ($orderId, $token, $normalizedStatus) {
            /** @var Order|null $order */
            $order = Order::lockForUpdate()->find($orderId);

            if (! $order) {
                return ['code' => 404, 'message' => 'Commande introuvable'];
            }

            if ($token && $order->payment_token && $order->payment_token !== $token) {
                Log::warning('Payment callback token mismatch', [
                    'order_id' => $orderId,
                    'stored_token_prefix' => substr((string) $order->payment_token, 0, 8),
                ]);

                return ['code' => 409, 'message' => 'Token de paiement incoherent'];
            }

            if (in_array($order->payment_status, ['paid', 'failed'], true)) {
                return ['code' => 200, 'message' => 'already_processed'];
            }

            if ($normalizedStatus === 'completed') {
                $order->update([
                    'payment_status' => 'paid',
                    'status' => 'confirmed',
                    'payment_token' => $token ?: $order->payment_token,
                ]);
            } elseif ($normalizedStatus === 'failed') {
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

    protected function processSubscriptionCallback(array $data, ?string $token, string $normalizedStatus, SubscriptionService $subs): JsonResponse
    {
        $paymentId = $data['custom_data']['subscription_payment_id'] ?? null;

        if (! $paymentId) {
            // Fallback : retrouver via le token
            $payment = $token ? SubscriptionPayment::where('gateway_token', $token)->first() : null;
        } else {
            $payment = SubscriptionPayment::find($paymentId);
        }

        if (! $payment) {
            return $this->errorResponse('Paiement abonnement introuvable', 404);
        }

        if ($payment->payment_status !== SubscriptionPayment::STATUS_PENDING) {
            return response()->json(['status' => 'already_processed'], 200);
        }

        if ($normalizedStatus === 'completed') {
            $subs->activatePayment($payment);
        } elseif ($normalizedStatus === 'failed') {
            $payment->update([
                'payment_status' => SubscriptionPayment::STATUS_FAILED,
                'gateway_response' => $data,
            ]);
        }

        return response()->json(['status' => 'ok'], 200);
    }
}
