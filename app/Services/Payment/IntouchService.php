<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Integration InTouch (https://developers.intouchgroup.net/documentation/TRANSFER/1).
 *
 * Le contrat exact des endpoints depend des identifiants partenaire fournis par
 * InTouch (login/password/api_key). Cette classe expose un contrat stable au
 * reste de l'application ; les details de la requete HTTP sont localises ici
 * et seront ajustes a la mise en service avec les credentials reels.
 */
class IntouchService implements PaymentGatewayInterface
{
    protected string $baseUrl;
    protected ?string $loginAgent;
    protected ?string $passwordAgent;
    protected ?string $apiKey;
    protected ?string $partnerId;
    protected ?string $webhookSecret;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('intouch.base_url'), '/');
        $this->loginAgent = config('intouch.login_agent');
        $this->passwordAgent = config('intouch.password_agent');
        $this->apiKey = config('intouch.api_key');
        $this->partnerId = config('intouch.partner_id');
        $this->webhookSecret = config('intouch.webhook_secret');

        if ($this->baseUrl === '' || ! $this->apiKey || ! $this->partnerId) {
            Log::error('InTouch: configuration incomplete (base_url, api_key, partner_id requis)');
        }
    }

    public function createPayment(array $data): array
    {
        $reference = $data['reference'];
        $amount = (int) $data['amount'];
        $currency = $data['currency'] ?? config('intouch.currency', 'XOF');
        $type = $data['type'] ?? 'order';
        $customer = $data['customer'] ?? [];

        $payload = [
            'partner_id' => $this->partnerId,
            'service_code' => config('intouch.service_code', 'CASHIN'),
            'amount' => $amount,
            'currency' => $currency,
            'order_id' => $reference,
            'description' => $data['description'] ?? 'Paiement TangaFlow',
            'callback_url' => config('intouch.callback_url'),
            'return_url' => config('intouch.return_url'),
            'cancel_url' => config('intouch.cancel_url'),
            'customer' => [
                'firstname' => $customer['firstname'] ?? null,
                'lastname' => $customer['lastname'] ?? null,
                'email' => $customer['email'] ?? null,
                'phone' => $customer['phone'] ?? null,
            ],
            'custom_data' => array_merge([
                'type' => $type,
                'reference' => $reference,
            ], $data['custom_data'] ?? []),
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'X-Partner-Id' => (string) $this->partnerId,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->post($this->baseUrl . '/transfer/v1/payment/initiate', $payload);
        } catch (\Throwable $e) {
            Log::error('InTouch HTTP exception', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Service de paiement indisponible'];
        }

        if (! $response->successful()) {
            Log::error('InTouch HTTP error', ['status' => $response->status(), 'body' => $response->body()]);

            return ['success' => false, 'message' => 'Echec de creation du paiement'];
        }

        $body = $response->json();
        $status = $body['status'] ?? $body['response_code'] ?? null;

        if (in_array($status, ['SUCCESS', '00', 'OK'], true)) {
            return [
                'success' => true,
                'payment_url' => $body['payment_url'] ?? $body['redirect_url'] ?? null,
                'token' => $body['token'] ?? $body['transaction_id'] ?? null,
                'raw' => $body,
            ];
        }

        Log::warning('InTouch payment initiation refused', ['body' => $body]);

        return [
            'success' => false,
            'message' => $body['message'] ?? 'Echec de creation du paiement',
            'raw' => $body,
        ];
    }

    public function checkStatus(string $token): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'X-Partner-Id' => (string) $this->partnerId,
                'Accept' => 'application/json',
            ])
                ->timeout(15)
                ->get($this->baseUrl . '/transfer/v1/payment/status/' . $token);
        } catch (\Throwable $e) {
            return ['status' => 'unknown', 'error' => $e->getMessage()];
        }

        if (! $response->successful()) {
            return ['status' => 'unknown'];
        }

        return $response->json() ?? ['status' => 'unknown'];
    }

    public function verifyCallback(array $data, ?string $rawBody = null, ?string $signatureHeader = null): bool
    {
        $token = $data['token'] ?? $data['transaction_id'] ?? null;
        $status = $data['status'] ?? null;

        if (! $token || ! $status) {
            return false;
        }

        if ($this->webhookSecret && $rawBody !== null && $signatureHeader !== null) {
            $expected = hash_hmac('sha256', $rawBody, $this->webhookSecret);
            if (! hash_equals($expected, $signatureHeader)) {
                Log::warning('InTouch callback signature mismatch');

                return false;
            }
        }

        return true;
    }

    /**
     * Normalise le statut InTouch vers : 'completed' | 'failed' | 'pending'.
     */
    public function normalizeStatus(string $intouchStatus): string
    {
        $up = strtoupper($intouchStatus);

        return match (true) {
            in_array($up, ['SUCCESS', 'COMPLETED', 'PAID', '00'], true) => 'completed',
            in_array($up, ['FAILED', 'CANCELLED', 'REJECTED', 'ERROR'], true) => 'failed',
            default => 'pending',
        };
    }
}
