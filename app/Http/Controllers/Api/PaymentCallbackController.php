<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\SubscriptionPayment;
use App\Services\LigdiCashService;
use App\Services\Payment\IntouchService;
use App\Services\Payment\PaymentGatewayInterface;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Database\Eloquent\Builder;
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
            return $this->processSubscriptionCallback($data, $token, $normalized, $subs, $ligdiCash);
        }

        return $this->processOrderCallback(
            data: $data,
            token: $token,
            normalizedStatus: $normalized,
            gateway: $ligdiCash,
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
            return $this->processSubscriptionCallback($data, $token, $normalized, $subs, $intouch);
        }

        return $this->processOrderCallback(
            data: $data,
            token: $token,
            normalizedStatus: $normalized,
            gateway: $intouch,
        );
    }

    /**
     * @param  array<string,mixed>  $data
     */
    protected function processOrderCallback(array $data, ?string $token, string $normalizedStatus, PaymentGatewayInterface $gateway): JsonResponse
    {
        $orderId = $data['custom_data']['order_id'] ?? $data['custom_data']['reference'] ?? null;

        if (! $orderId) {
            return $this->errorResponse('Commande introuvable dans les donnees du callback', 400);
        }

        // Defense en profondeur : si le callback annonce un succes, on re-confirme aupres de la
        // passerelle (avec le token STOCKE) avant toute mise a jour. Hors transaction pour ne pas
        // tenir un verrou pendant l'appel HTTP. On refuse si la passerelle contredit explicitement
        // le callback ; fail-open (avec log) si elle est injoignable.
        if ($normalizedStatus === 'completed') {
            $order = Order::find($orderId);
            if (! $order) {
                return $this->errorResponse('Commande introuvable', 404);
            }

            // Le callback est authentifie par signature : le montant/devise qu'il annonce
            // font foi et doivent correspondre a la commande (garde-fou de reconciliation).
            if (! $this->callbackAmountMatchesOrder($data, $order)) {
                return response()->json(['status' => 'ignored'], 200);
            }

            // On ne re-confirme qu'avec le token STOCKE en base (issu de l'initiation du
            // paiement), jamais avec le token du callback. Un "completed" sur une commande
            // sans token stocke est anormal : on l'ignore plutot que d'y faire confiance.
            if (! $order->payment_token) {
                Log::warning('Callback commande "completed" sans token stocke : ignore', ['order_id' => $orderId]);

                return response()->json(['status' => 'ignored'], 200);
            }

            $confirmed = $gateway->confirmTransaction($order->payment_token);

            if (in_array($confirmed, ['failed', 'pending'], true)) {
                Log::warning('Callback commande contredit par la passerelle : confirmation refusee', [
                    'order_id' => $orderId,
                    'callback_status' => $normalizedStatus,
                    'gateway_status' => $confirmed,
                ]);

                return response()->json(['status' => 'ignored'], 200);
            }

            if ($confirmed === 'unknown') {
                Log::warning('Confirmation passerelle indisponible : commande confirmee sur le statut du callback', [
                    'order_id' => $orderId,
                ]);
            }
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

    protected function processSubscriptionCallback(array $data, ?string $token, string $normalizedStatus, SubscriptionService $subs, PaymentGatewayInterface $gateway): JsonResponse
    {
        $companyId = $data['custom_data']['company_id'] ?? null;
        $paymentId = $data['custom_data']['subscription_payment_id'] ?? null;

        // Recherche SCOPEE par societe : le company_id est embarque dans custom_data a
        // l'initiation (cf. SubscriptionService::createPayment). Sans ce scope, un callback
        // pourrait cibler le paiement d'un autre tenant (SubscriptionPayment n'a pas de
        // global scope car les callbacks sont non authentifies).
        $scope = fn (Builder $q): Builder => $companyId ? $q->where('company_id', $companyId) : $q;

        if ($paymentId) {
            $payment = $scope(SubscriptionPayment::query())->whereKey($paymentId)->first();
        } else {
            $payment = $token
                ? $scope(SubscriptionPayment::query())->where('gateway_token', $token)->first()
                : null;
        }

        if (! $payment) {
            return $this->errorResponse('Paiement abonnement introuvable', 404);
        }

        if ($payment->payment_status !== SubscriptionPayment::STATUS_PENDING) {
            return response()->json(['status' => 'already_processed'], 200);
        }

        if ($normalizedStatus === 'completed') {
            // Defense en profondeur : ne jamais activer sur la seule foi du statut du corps
            // du callback. On re-interroge la passerelle avec le token STOCKE en base (jamais
            // celui du callback). On refuse si la passerelle contredit explicitement le callback ;
            // on reste fail-open (avec log) si elle est injoignable pour ne pas bloquer un
            // paiement legitime lors d'une indisponibilite transitoire.
            if (! $payment->gateway_token) {
                Log::warning('Callback abonnement "completed" sans token stocke : ignore', [
                    'payment_id' => $payment->id,
                ]);

                return response()->json(['status' => 'ignored'], 200);
            }

            $confirmed = $gateway->confirmTransaction($payment->gateway_token);

            if (in_array($confirmed, ['failed', 'pending'], true)) {
                Log::warning('Callback abonnement contredit par la passerelle : activation refusee', [
                    'payment_id' => $payment->id,
                    'callback_status' => $normalizedStatus,
                    'gateway_status' => $confirmed,
                ]);

                return response()->json(['status' => 'ignored'], 200);
            }

            if ($confirmed === 'unknown') {
                Log::warning('Confirmation passerelle indisponible : activation sur le statut du callback', [
                    'payment_id' => $payment->id,
                ]);
            }

            // Activation idempotente sous verrou : on re-verifie l'etat PENDING en tenant un
            // verrou sur la ligne paiement, pour empecher une double-activation si deux
            // callbacks concurrents passent le pre-controle ci-dessus.
            DB::transaction(function () use ($payment, $subs): void {
                $locked = SubscriptionPayment::lockForUpdate()->find($payment->id);
                if (! $locked || $locked->payment_status !== SubscriptionPayment::STATUS_PENDING) {
                    return;
                }

                $subs->activatePayment($locked);
            });
        } elseif ($normalizedStatus === 'failed') {
            $payment->update([
                'payment_status' => SubscriptionPayment::STATUS_FAILED,
                'gateway_response' => $data,
            ]);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Verifie que le montant/devise annonces par le callback (authentifie par signature)
     * correspondent a la commande. Best-effort : si le callback n'expose pas ces champs
     * (contrat variable selon la passerelle), on logue et on laisse passer.
     *
     * @param  array<string,mixed>  $data
     */
    protected function callbackAmountMatchesOrder(array $data, Order $order): bool
    {
        $amount = $data['amount'] ?? $data['total'] ?? $data['total_amount'] ?? $data['montant'] ?? null;
        $currency = $data['currency'] ?? $data['devise'] ?? null;

        if ($amount !== null && (int) $amount !== (int) $order->total) {
            Log::warning('Callback commande : montant divergent, ignore', [
                'order_id' => $order->id,
                'callback_amount' => $amount,
                'order_total' => $order->total,
            ]);

            return false;
        }

        if ($currency !== null && $order->currency && strtoupper((string) $currency) !== strtoupper((string) $order->currency)) {
            Log::warning('Callback commande : devise divergente, ignore', [
                'order_id' => $order->id,
                'callback_currency' => $currency,
                'order_currency' => $order->currency,
            ]);

            return false;
        }

        return true;
    }
}
