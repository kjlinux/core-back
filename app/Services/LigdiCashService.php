<?php

namespace App\Services;

use App\Services\Payment\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LigdiCashService implements PaymentGatewayInterface
{
    protected string $baseUrl;

    protected string $apiKey;

    protected string $apiSecret;

    public function __construct()
    {
        $this->baseUrl = config('ligdicash.base_url') ?? '';
        $this->apiKey = config('ligdicash.api_key') ?? '';
        $this->apiSecret = config('ligdicash.api_secret') ?? '';

        // Echec rapide en boot si la config est incomplete : evite de tomber
        // silencieusement plus tard avec une 500 cryptique au moment de payer.
        if ($this->baseUrl === '' || $this->apiKey === '' || $this->apiSecret === '') {
            Log::error('LigdiCash: configuration incomplete (base_url, api_key, api_secret requis)');
        }
    }

    public function createPayment(array $data): array
    {
        $reference = $data['reference'];
        $amount = (int) $data['amount'];
        $description = $data['description'] ?? 'Commande Tangaflow';
        $type = $data['type'] ?? 'order';
        $customer = $data['customer'] ?? [];

        $items = array_map(function ($item) {
            return [
                'name' => $item['name'],
                'description' => $item['name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['unit_price'] * $item['quantity'],
            ];
        }, $data['items'] ?? []);

        // LigdiCash exige un invoice.items non vide : pour les flux sans lignes
        // (abonnement), on construit un item unique a partir du montant total.
        if (empty($items)) {
            $items = [[
                'name' => $description,
                'description' => $description,
                'quantity' => 1,
                'unit_price' => $amount,
                'total_price' => $amount,
            ]];
        }

        $payload = [
            'commande' => [
                'invoice' => [
                    'items' => $items,
                    'total_amount' => $amount,
                    'devise' => config('ligdicash.currency', 'XOF'),
                    'description' => $description,
                    'customer' => '',
                    'customer_firstname' => $customer['firstname'] ?? '',
                    'customer_lastname' => $customer['lastname'] ?? '',
                    'customer_email' => $customer['email'] ?? '',
                    'external_id' => (string) $reference,
                    'otp' => '',
                ],
                'store' => [
                    'name' => 'Tangaflow',
                    'website_url' => config('app.url'),
                ],
                'actions' => [
                    'cancel_url' => $data['cancel_url'] ?? config('ligdicash.cancel_url'),
                    'return_url' => $data['return_url'] ?? config('ligdicash.return_url'),
                    'callback_url' => config('ligdicash.callback_url'),
                ],
                'custom_data' => array_merge([
                    'type' => $type,
                    'reference' => $reference,
                ], $data['custom_data'] ?? []),
            ],
        ];

        $response = Http::withHeaders([
            'Apikey' => $this->apiKey,
            'Authorization' => 'Bearer '.$this->apiSecret,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl.'/redirect/checkout-invoice/create', $payload);

        if ($response->successful()) {
            $body = $response->json();
            if (($body['response_code'] ?? '') === '00') {
                return [
                    'success' => true,
                    'payment_url' => $body['response_text'] ?? null,
                    'token' => $body['token'] ?? null,
                    'raw' => $body,
                ];
            }
            Log::error('LigdiCash payment creation failed', [
                'response_code' => $body['response_code'] ?? null,
                'response_text' => $body['response_text'] ?? null,
            ]);

            return [
                'success' => false,
                'message' => $body['response_text'] ?? 'Échec de création du paiement',
                'raw' => $body,
            ];
        }

        Log::error('LigdiCash HTTP error', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return [
            'success' => false,
            'message' => 'Échec de création du paiement',
        ];
    }

    public function checkStatus(string $token): array
    {
        $response = Http::withHeaders([
            'Apikey' => $this->apiKey,
            'Authorization' => 'Bearer '.$this->apiSecret,
            'Accept' => 'application/json',
        ])->get($this->baseUrl.'/redirect/checkout-invoice/confirm/', [
            'invoiceToken' => $token,
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        return ['status' => 'unknown'];
    }

    /**
     * Verifie l'authenticite du payload de callback.
     * Fail-closed : la signature HMAC (sur le corps brut, cle = api_secret) est
     * obligatoire. Sans secret configure ou sans signature dans la requete, le
     * callback est refuse — on ne fait jamais confiance a un callback non verifie,
     * qui pourrait marquer un paiement comme paye/echoue.
     *
     * @param  array<string,mixed>  $data
     */
    public function verifyCallback(array $data, ?string $rawBody = null, ?string $signatureHeader = null): bool
    {
        if (empty($data['token']) || empty($data['status'])) {
            return false;
        }

        if ($this->apiSecret === '') {
            Log::error('LigdiCash: api_secret non configure — callback refuse (fail-closed)');

            return false;
        }

        if ($rawBody === null || $signatureHeader === null) {
            Log::warning('LigdiCash callback sans signature — refuse');

            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $this->apiSecret);
        if (! hash_equals($expected, $signatureHeader)) {
            Log::warning('LigdiCash callback signature mismatch', [
                'expected_prefix' => substr($expected, 0, 8),
            ]);

            return false;
        }

        return true;
    }

    public function confirmTransaction(?string $token): string
    {
        if (! $token) {
            return 'unknown';
        }

        $response = $this->checkStatus($token);
        $raw = $response['status'] ?? $response['response_code'] ?? null;

        if ($raw === null || strtolower((string) $raw) === 'unknown') {
            return 'unknown';
        }

        return $this->normalizeStatus((string) $raw);
    }

    /**
     * Normalise le statut LigdiCash vers : 'completed' | 'failed' | 'pending'.
     */
    public function normalizeStatus(string $status): string
    {
        $up = strtoupper($status);

        return match (true) {
            in_array($up, ['COMPLETED', 'SUCCESS', 'PAID', '00'], true) => 'completed',
            in_array($up, ['FAILED', 'NOCOMPLETED', 'CANCELLED', 'REJECTED', 'ERROR'], true) => 'failed',
            default => 'pending',
        };
    }
}
