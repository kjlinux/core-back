<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LigdiCashService
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
        $items = array_map(function ($item) {
            return [
                'name' => $item['name'],
                'description' => $item['name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total_price' => $item['unit_price'] * $item['quantity'],
            ];
        }, $data['items'] ?? []);

        $payload = [
            'commande' => [
                'invoice' => [
                    'items' => $items,
                    'total_amount' => $data['amount'],
                    'devise' => config('ligdicash.currency', 'XOF'),
                    'description' => $data['description'] ?? 'Commande Core Tanga',
                    'customer' => '',
                    'customer_firstname' => $data['customer_firstname'] ?? '',
                    'customer_lastname' => $data['customer_lastname'] ?? '',
                    'customer_email' => $data['customer_email'] ?? '',
                    'external_id' => '',
                    'otp' => '',
                ],
                'store' => [
                    'name' => 'Core Tanga Group',
                    'website_url' => config('app.url'),
                ],
                'actions' => [
                    'cancel_url' => config('ligdicash.cancel_url'),
                    'return_url' => config('ligdicash.return_url'),
                    'callback_url' => config('ligdicash.callback_url'),
                ],
                'custom_data' => [
                    'order_id' => $data['order_id'],
                    'transaction_id' => $data['order_id'],
                ],
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
                ];
            }
            Log::error('LigdiCash payment creation failed', [
                'response_code' => $body['response_code'] ?? null,
                'response_text' => $body['response_text'] ?? null,
            ]);

            return [
                'success' => false,
                'message' => $body['response_text'] ?? 'Echec de creation du paiement',
            ];
        }

        Log::error('LigdiCash HTTP error', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return [
            'success' => false,
            'message' => 'Echec de creation du paiement',
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
     * Verifie la coherence minimale du payload de callback.
     * Si une signature HMAC est configuree (ligdicash.hmac_header / ligdicash.api_secret),
     * elle est verifiee contre le corps brut. Sinon on retombe sur la verification
     * de presence des champs critiques.
     *
     * @param  array<string,mixed>  $data
     */
    public function verifyCallback(array $data, ?string $rawBody = null, ?string $signatureHeader = null): bool
    {
        if (empty($data['token']) || empty($data['status'])) {
            return false;
        }

        if ($rawBody !== null && $signatureHeader !== null && $this->apiSecret !== '') {
            $expected = hash_hmac('sha256', $rawBody, $this->apiSecret);
            if (! hash_equals($expected, $signatureHeader)) {
                Log::warning('LigdiCash callback signature mismatch', [
                    'expected_prefix' => substr($expected, 0, 8),
                ]);

                return false;
            }
        }

        return true;
    }
}
