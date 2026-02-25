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
        $this->baseUrl = config('ligdicash.base_url');
        $this->apiKey = config('ligdicash.api_key');
        $this->apiSecret = config('ligdicash.api_secret');
    }

    public function createPayment(array $data): array
    {
        $payload = [
            'commande' => [
                'invoice' => [
                    'items' => $data['items'] ?? [],
                    'total_amount' => $data['amount'],
                    'devise' => config('ligdicash.currency', 'XOF'),
                    'description' => $data['description'] ?? 'Commande Core Tanga',
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
                    'order_number' => $data['order_number'] ?? '',
                ],
            ],
        ];

        $response = Http::withHeaders([
            'Apikey' => $this->apiKey,
            'Authorization' => 'Bearer ' . $this->apiSecret,
            'Accept' => 'application/json',
        ])->post($this->baseUrl . '/checkout/invoice/create', $payload);

        if ($response->successful()) {
            $body = $response->json();
            return [
                'success' => true,
                'payment_url' => $body['response_text'] ?? null,
                'token' => $body['token'] ?? null,
            ];
        }

        Log::error('LigdiCash payment creation failed', [
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
            'Authorization' => 'Bearer ' . $this->apiSecret,
            'Accept' => 'application/json',
        ])->get($this->baseUrl . '/checkout/invoice/confirm', [
            'invoiceToken' => $token,
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        return ['status' => 'unknown'];
    }

    public function verifyCallback(array $data): bool
    {
        return !empty($data['token']) && !empty($data['status']);
    }
}
